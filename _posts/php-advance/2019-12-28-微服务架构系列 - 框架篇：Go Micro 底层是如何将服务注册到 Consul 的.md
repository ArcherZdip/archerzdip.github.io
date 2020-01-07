---
title: 微服务架构系列 - 框架篇：Go Micro 底层是如何将服务注册到 Consul 的
layout: post
category: blog
tags: |-
  PHP
  微服务架构系列
  Go Micro
  Consul
---

# 微服务架构系列（八）

简单介绍了基于 Go Micro 框架实现服务接口的发布和调用，接下来，我们以 Consul 作为注册中心为例，介绍 Go Micro 框架底层是如何将服务注册到 Consul 的。



**Go Micro 服务注册与发现流程概述**



服务的注册和发现是微服务架构中必不可少的重要一环，Go Micro 为此提供了专门的接口 Registry 来处理，实现该接口的组件即可作为微服务的注册中心用于服务注册与发现，在我们的示例中以 Consul 作为注册中心，所有微服务服务端启动时，会将服务信息注册到 Consul，然后客户端调用服务时，会从 Consul 中查询服务接口信息，再发起请求，对应流程图如下：



![img](/assets/post/e7635603f17d13c04bf9c2cfb3b66a5ead1d5d8cfdc8ac184135ab593e2b3b21.png)



以前面介绍的 Greeter Service 为例：



1. 当 Greeter Service 服务端启动时，会向 Consul 发送一个 POST 请求，告诉 Consul 自己的 IP 和端口；
2. Consul 接收到 Greeter Service 的注册请求后，每隔10s（默认）会向 Greeter Service 发送一个健康检查的请求，检验 Greter Service 是否有效（心跳检测）；
3. 当客户端以 HTTP 接口方式发送 GET 请求 `/greeter/say/hello` 到 Greeter Service 时，会先从 Consul 中拿到一个存储对应服务 IP 和端口的临时表，并从表中查询 Greeter Service 的 IP 和端口，再发送 GET 方式请求到 `/greeter/say/hello`。该临时表每隔 10s 会更新，只包含有通过了健康检查的 Service。此外，为了提高性能和系统可用性，往往会缓存服务信息到本地，如果服务部署在多个机器，还会使用负载均衡算法选择指定服务端通信。



**Go Micro 服务注册底层实现细节**



我们先来看看服务端启动时是如何将服务信息注册到 Consul 的，打开 `~/go/hello/src/hello/main.go` 这个服务端主入口文件，可以在 `main` 函数末尾看到这段代码：



```go
    // Run the server
    if err := service.Run(); err != nil {
        fmt.Println(err)
    }
```



`service.Run()` 是服务启动的入口函数，对应的源码位于 `go-micro/service.go`：



```go
    func (s *service) Run() error {
        if err := s.Start(); err != nil {
            return err
        }
    
        ch := make(chan os.Signal, 1)
        signal.Notify(ch, syscall.SIGTERM, syscall.SIGINT, syscall.SIGQUIT)
    
        select {
        // wait on kill signal
        case <-ch:
        // wait on context cancel
        case <-s.opts.Context.Done():
        }
    
        return s.Stop()
    }
```



这里面会调用 `service.Start()` 方法：



```go
    func (s *service) Start() error {
        for _, fn := range s.opts.BeforeStart {
            if err := fn(); err != nil {
                return err
            }
        }
    
        if err := s.opts.Server.Start(); err != nil {
            return err
        }
    
        for _, fn := range s.opts.AfterStart {
            if err := fn(); err != nil {
                return err
            }
        }
    
        return nil
    }
```

  

该方法会依次执行服务启动前逻辑、启动逻辑和启动后逻辑，我们重点关注服务启动过程中做了啥，追踪 `s.opts.Server.Start()` 方法，这段源码位于 `go-micro/server/rpc_server.go` 文件中的 `(s *rpcServer) Start()` 函数，该函数中的代码就是咱们服务启动的核心逻辑所在了，由于涉及到的代码量比较大，我们重点关注服务注册部分：



```
    // use RegisterCheck func before register
    if err = s.opts.RegisterCheck(s.opts.Context); err != nil {
        log.Logf("Server %s-%s register check error: %s", config.Name, config.Id, err)
    } else {
        // announce self to the world
        if err = s.Register(); err != nil {
            log.Logf("Server %s-%s register error: %s", config.Name, config.Id, err)
        }
    }
```



这里先对上下文环境做检测，没有问题才正式开始服务注册，对应的注册逻辑位于当前文件下的 `(s *rpcServer) Register()` 方法，在该方法中，会获取服务所在机器的 IP、端口以及服务名称、节点ID、元数据、处理器列表、订阅者等信息并将其注册到系统默认的注册中心：



```
    if err := config.Registry.Register(service, rOpts...); err != nil {
        return err
    }
```

  

这里我们通过系统环境变量 `MICRO_REGISTRY=consul` 指定默认注册中心为 Consul，所以我们直接去 `go-micro/registry/consul/consul.go` 中看 `(c *consulRegistry) Register` 方法的实现，这个方法代码量也比较大，前面一大堆逻辑都是做环境检测和变量的初始化，我们直接拉到后面的服务注册部分：



```go
    // register the service
    // 待注册服务信息
    asr := &consul.AgentServiceRegistration{
        ID:      node.Id,
        Name:    s.Name,
        Tags:    tags,
        Port:    node.Port,
        Address: node.Address,
        Check:   check,
    }
      
     // Specify consul connect
     if c.connect {
         asr.Connect = &consul.AgentServiceConnect{
            Native: true,
         }
     }


     // 注册服务信息到 Consul
     if err := c.Client.Agent().ServiceRegister(asr); err != nil {
         return err
     }
```



具体注册逻辑在 `c.Client.Agent().ServiceRegister` 对应的方法中



```go
    func (a *Agent) ServiceRegister(service *AgentServiceRegistration) error {
        r := a.c.newRequest("PUT", "/v1/agent/service/register")
        r.obj = service
        _, resp, err := requireOK(a.c.doRequest(r))
        if err != nil {
            return err
        }
        resp.Body.Close()
        return nil
    }
```

  

系统会发起一个 PUT 请求到 Consul 的服务注册接口 `/v1/agent/service/register`，并将服务注册信息作为请求实体传递过去，请求失败则返回错误信息，请求成功，则可以在 Consul 上看到注册的新服务。



以上，就是 Go Micro 框架服务注册的底层实现细节，基于其它组件的注册中心总体流程也是如此，只是不同注册中心实现服务注册的细节有所差异罢了，下一篇，学院君将给大家介绍客户端发现服务、调用服务的底层实现细节。