---
title: 微服务架构系列 - 框架篇：通过 Micro Proxy 实现微服务之间的接口调用
layout: post
category: blog
tags: |-
  PHP
  微服务架构系列
  Go Micro
---



# 微服务架构系列（十四）



前面我们介绍了从微服务内部（直接通过相应的 SDK）及客户端（通过 API 网关）调用微服务接口的实现，如果要从一个微服务调用另一个微服务的服务接口，该怎么做呢？



Go Micro 为我们提供了 Micro Proxy 组件来实现这个功能，Micro Proxy 是一个代理服务器，可以看作是一个微服务到另一个微服务的请求中介，对应的系统架构图如下所示：



![img](/assets/post/fc09e64431f3f1091118b6951ea2e65d7fef912d26bdbb0f1f70e0e334c06171.png)



当我们从微服务 A 中请求微服务 B 中的某个接口时，请求会先到达 Micro Proxy 这一层，在这个代理层中会从注册中心获取服务接口的注册信息，然后向指定服务节点发起请求，请求处理完毕后，再经由 Micro Proxy 返回给调用方。



Micro Proxy 提供了 Go Micro 框架的代理实现，通过 Micro Proxy 可以把 Go Micro 框架中的各种特性整合到一起，比如服务发现、负载均衡、容错、插件、包装器等，这样一来，你就不需要修改每个 Go Micro 应用的底层代码，通过代理就可以组合上述功能组件对外提供服务，通过该功能，还可以支持使用其它语言实现瘦客户端。



启用这个代理很简单，只需要在终端运行如下命令即可：



  micro proxy 

  

![img](https://file.zsxq.com/378/27/78271fa27f383c3e188190826491e141819aab8d3a6f1d053ca1ec9d0b248a81)



我们可以通过 `http://localhost:8081` 访问到这个代理，例如，如果我们要访问后端的 `go.micro.srv.greeter` 服务提供的 `Greeter.Hello` 端点，可以这么做：



```
    curl \
    -H 'Content-Type: application/json' \
    -H 'Micro-Service: go.micro.srv.greeter' \
    -H 'Micro-Endpoint: Greeter.Hello' \
    -d '{"name": "学院君"}' \
    http://localhost:8081
```

  

相应的输出结果是：



```
    {"greeting":" 你好, 学院君"}
```

  

Micro Proxy 默认会基于 `micro/go-micro/proxy/mucp/mucp.go` 这个代理将请求路由到最终的服务端点（Endpoint），服务的初始化、启动、注册和监听请求逻辑对应的源码位于 `micro/micro/proxy/proxy.go`，感兴趣的同学可以自行查看，相应的逻辑和我们之前介绍的服务注册和引用类似。



此外，在启动 Micro Proxy 的时候，还可以设置更多参数来指定代理的名称、地址等信息，具体可以参考[官方文档](https://micro.mu/docs/proxy.html)了解。



看到这里，你可能觉得通过 Micro API 也可以实现类似的功能，如果仅仅是用作远程服务调用的话，确实如此，但 Micro Proxy 的强大之处，在于还支通过其它语言实现服务端和客户端，因为 Micro Proxy 提供的整个 Go Micro 框架功能的代理，我们可以通过 `http://localhost:8081/registry` 这个地址注册服务，然后通过 `http://localhost:8081` 加请求参数对服务进行消费，具体示例可以参考 Go Micro 官方提供的示例代码：https://github.com/micro/examples/tree/master/proxy。这其实就是微服务架构中的 Sidecar 设计模式，下一篇学院君将给大家简单介绍下这个设计模式及其在 Go Micro 框架中的具体落地。