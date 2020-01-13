---
title: 微服务架构系列 - 注册中心篇：集成 Etcd 作为 Go Micro 的注册中心
layout: post
category: blog
tags: |-
  PHP
  微服务架构系列
  Go Micro
  注册中心篇
  Etcd
---

# 微服务架构系列（二十五）

**功能演示**



上篇分享我们简单介绍了 Etcd 的原理和本地安装，接下来，我们将其集成到 Go Micro 中作为注册中心实现服务注册与发现，Go Micro 默认并不支持 Etcd 作为注册中心，我们需要通过 Micro 生态提供的 Go Plugins 包将其引入，前面我们已经介绍过，Go Plugins 是 Micro 社区维护的插件集合，为 Go Micro 生态提供了丰富的第三方可选插件来完善框架本身的功能，以 Registry 组件为例，通过 Go Plugins，我们可以有更多选择，比如 Etcd（v2、v3）、Eureka、Kubernetes、Zookeeper 等。



下面我们以 Etcd 目前默认的版本 `etcd` 为例作为本次分享的演示版本，关于 `etcd` 软件的安装我们上篇已经演示过，这里我们要进入 `hello` 项目，通过如下命令完成 Go Micro 项目中 `etcd` 插件的引入：



```
    go get github.com/micro/go-plugins/registry/etcd
```

  

安装完成后，就可以在 Go Micro 框架中使用 Etcd 了，这里，我们仍然以通过系统环境变量设置默认的注册中心为例，将 `MICRO_REGISTRY` 修改为 `etcd` 并保存（当然，你还可以通过在启动服务时通过 `--registry=etcd` 选项来指定），然后打开 `src/hello/main.go` 文件，在引入包部分新增对 `etcd` 的引入：



```go
    import (
        "context"
        "fmt"
        "github.com/micro/go-micro"
        _ "github.com/micro/go-plugins/registry/etcd"
        proto "hello/proto"
    )
```

`src/hello/client.go` 也做同样的操作。



接下来，就可以运行 `go run main.go` 启动服务了（确保此时 `etcd` 已经运行，并监听 `localhost:2379` 端口）：



![img](/assets/post/FvwvMXTSr99Oj-zavL6KHVJVQWT9.png)



如果以 Debug 模式启动 Etcd 的话（`./etcd --debug`），可以在日志里看到服务注册日志：



![img](/assets/post/Fvixh1CzF6C37QrkCX8bkENvl5B8.png)



可以看到，服务已经成功注册到 Etcd 上，然后我们运行客户端测试代码：



![img](/assets/post/FmPwg5iUwSisi1sGJlILLQlHv8Bl.png)



调用成功。同样可以在 Etcd 运行终端看到服务发现日志：



![img](/Users/zhanglingyu/Sites/resource/archerzdip.github.io/assets/post/FmRkMdUi6E6RHHQL-lFQCcqzky2F.png)



**底层实现**



非常简单，作为开发者所要处理的工作非常少，下面我们来看看基于插件集成的注册中心底层是如何工作的。



进入 `src/hello/main.go`，从系统环境变量读取默认注册中心配置还是在 `Service.Init()` 中完成的，这也是为什么我们必须要在顶部引入如下这行代码的原因：



```
    _ "github.com/micro/go-plugins/registry/etcd"
```

  

在对应源文件的 `init()` 方法中包含对 `cmd.DefaultRegistries` 字典新增 `etcd` 对应构造函数的配置：



```
    func init() {
        cmd.DefaultRegistries["etcd"] = NewRegistry
    }
```

  

这样一来，当我们初始化 `Registry` 组件的时候，就可以根据获取的配置值 `etcd` 在根据对应的构造函数初始化 `ectd` 插件与 Etcd 代理/集群进行交互了。



后面服务注册和监听的逻辑和[之前分析 Consul 的基本流程](https://articles.zsxq.com/id_u80jrj77p5p2.html)一致，我们重点来看下 `etcd` 插件是如何与 Etcd 集群进行交互的。



通过日志我们可以看到，和 Consul 一样，这也是通过 HTTP API 实现的，在 `etcd` 插件的源文件 `src/github.com/micro/go-plugins/registry/etcd/etcd.go` 中，通过 `etcdRegistry` 来实现 `Registry` 接口，对应的服务注册方法是 `Register`：



```go
    func (e *etcdRegistry) Register(s *registry.Service, opts ...registry.RegisterOption) error {
        if len(s.Nodes) == 0 {
            return errors.New("Require at least one node")
        }
    
        var options registry.RegisterOptions
        for _, o := range opts {
            o(&options)
        }
    
        service := &registry.Service{
            Name:      s.Name,
            Version:   s.Version,
            Metadata:  s.Metadata,
            Endpoints: s.Endpoints,
        }
    
        ctx, cancel := context.WithTimeout(context.Background(), e.options.Timeout)
        defer cancel()
    
        _, err := e.client.Set(ctx, servicePath(s.Name), "", &etcd.SetOptions{PrevExist: etcd.PrevIgnore, Dir: true})
        if err != nil && !strings.HasPrefix(err.Error(), "102: Not a file") {
            return err
        }
    
        for _, node := range s.Nodes {
            service.Nodes = []*registry.Node{node}
            _, err := e.client.Set(ctx, nodePath(service.Name, node.Id), encode(service), &etcd.SetOptions{TTL: options.TTL})
            if err != nil {
                return err
            }
        }
    
        return nil
    }
```

  

这里面会对服务数据进行处理和编码然后通过 `e.client.Set` 方法将注册信息发送给 Etcd 集群，对应的实现源码位于 `github.com/coreos/etcd/client` 包中实现了 `KeysAPI` 接口的 `httpKeysAPI` 类的 `Set` 方法，可以在 `src/github.com/coreos/etcd/client/keys.go` 中查看相应的实现代码。



在注册每个服务节点之前，先会创建对应的服务目录，这一点通过日志上的两条更新记录可以看出来。注册成功后，还可以通过在浏览器中访问 `http://localhost:2379/v2/keys/micro-registry/go.micro.srv.greeter` 查看服务节点信息：



![img](/assets/post/FpDrSlGo2Zm_y9jSf_GO2TpmBdAV.png)



接下来，我们就可以通过注册中心查询服务节点信息进行远程服务调用了。



和 Consul 一样，最外层也是通过 Selector 组件选取服务节点，先通过 Selector 封装的 Cache 层获取所有服务节点信息，然后通过负载均衡策略选取其中的一个服务节点进行连接，如果缓存中没有数据的话，会调用 Registry 组件的 `GetService` 方法进行查询，这里对应的 Registry 组件是 `etcdRegistry`，对应的 `GetService` 方法实现如下：



```go
    func (e *etcdRegistry) GetService(name string) ([]*registry.Service, error) {
        ctx, cancel := context.WithTimeout(context.Background(), e.options.Timeout)
        defer cancel()
    
        rsp, err := e.client.Get(ctx, servicePath(name), &etcd.GetOptions{})
        if err != nil && !strings.HasPrefix(err.Error(), "100: Key not found") {
            return nil, err
        }
    
        if rsp == nil {
            return nil, registry.ErrNotFound
        }
    
        serviceMap := map[string]*registry.Service{}
    
        for _, n := range rsp.Node.Nodes {
            if n.Dir {
                continue
            }
            sn := decode(n.Value)
    
            s, ok := serviceMap[sn.Version]
            if !ok {
                s = &registry.Service{
                    Name:      sn.Name,
                    Version:   sn.Version,
                    Metadata:  sn.Metadata,
                    Endpoints: sn.Endpoints,
                }
                serviceMap[s.Version] = s
            }
    
            for _, node := range sn.Nodes {
                s.Nodes = append(s.Nodes, node)
            }
        }
    
        var services []*registry.Service
        for _, service := range serviceMap {
            services = append(services, service)
        }
        return services, nil
    }
```

  

这里会调用底层 Etcd 包的 `httpKeysAPI` 类的 `Get` 方法通过 HTTP API 与 Etcd 集群进行交互，查询指定服务名称对应的所有服务节点，通过解码和处理后返回给 Selector 的缓存层进行后续处理，其它逻辑（包括服务监控和调用）与 Consul [共用一套上层实现](https://articles.zsxq.com/id_r1j6mxa8vvs6.html)，这里就不赘述了。



同样和 Consul 一样，当我们通过 Ctrl+C 中止服务时， 会调用 `etcd` 插件的 `DeDeregister` 方法从 Etcd 集群中删除对应的服务节点信息：



```go
    func (e *etcdRegistry) Deregister(s *registry.Service) error {
        if len(s.Nodes) == 0 {
            return errors.New("Require at least one node")
        }
    
        ctx, cancel := context.WithTimeout(context.Background(), e.options.Timeout)
        defer cancel()
    
        for _, node := range s.Nodes {
            _, err := e.client.Delete(ctx, nodePath(s.Name, node.Id), &etcd.DeleteOptions{Recursive: false})
            if err != nil {
                return err
            }
        }
    
        e.client.Delete(ctx, servicePath(s.Name), &etcd.DeleteOptions{Dir: true})
        return nil
    }
```

  

对应的与 Etcd 集群交互的源码还是在 `github.com/coreos/etcd/client` 包的 `httpKeysAPI` 类里面，对应其中的 `Delete` 方法，在删除服务时除了删除所有服务节点外，最后还会删除服务目录，这一点通过监控日志也可以看到：



![img](/assets/post/FrKzG3-DTB-MM7LVBTY646pTCdUN.png)



关于 Etcd 及其与 Go Micro 的集成我们就简单介绍到这里，关于其本地集群的模拟搭建，可以参考 Consul 去实现，下一篇我们将给大家分享基于 Paxos 算法的老牌注册中心实现 —— Zookeeper。