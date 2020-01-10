---
title: 微服务架构系列 - 框架篇：Go Micro 中的 API 网关底层实现原理（中）
layout: post
category: blog
tags: |-
  PHP
  微服务架构系列
  Go Micro
  API网关
---



# 微服务架构系列（十一）



**Micro API 架构模式**



上篇分享给大家介绍了微服务中为什么需要 API 网关以及 API 网关的两种架构模式，今天我们以 Go Micro 框架为例，介绍其 API 网关的底层实现，在 Go Micro 中，API 网关通过内置的 Micro API 组件即可实现，该组件通过服务发现基于 HTTP 方式将请求动态路由到后台服务接口，对应的架构模式如下：



![img](/assets/post/1ff49d78c186d4738428a06b13277c6a206c405d7a2240894d87fdf373998d29.png)



Customer API 对应我们之前编写的 API 层 `Say.Hello` API，Customer Service 对应我们之前编写的后台服务层 `GreeterService.Hello` API，我们之前编写的示例比较简单，在 `Say.Hello` 中只是简单调用 `GreeterService.Hello` 并返回，实际上，我们可以在 API 层聚合更多后台服务然后返回给调用方，就像上面这个架构图展示的那样。



下面我们重点来看下 Micro API 底层是如何对客户端请求进行调度的。



**Micro API 启动命令参数解析**



Micro API 源码位于 [micro/api](https://github.com/micro/go-micro/tree/master/api)，API 层源码位于我们编写的应用代码包中，以之前的示例应用 `hello` 为例，位于根目录下 ` src/hello/api` 目录中，后台服务层代码原型则位于 `src/hello/proto` 中，有 Micro API 网关需要依赖后台 API 及底层服务，所以需要按照依赖关系依次启动后台服务和 API 接口，然后再通过 `micro api` 启动 API 网关，该命令运行时会执行 `micro/api/api.go` 文件中的源码，这也是基于 Go Micro 框架的微服务 API 网关的入口文件。



在 `micro/api/api.go` 源码文件中，首先定义了一些全局变量：



```
    var (
        Name         = "go.micro.api"
        Address      = ":8080"
        Handler      = "meta"
        Resolver     = "micro"
        RPCPath      = "/rpc"
        APIPath      = "/"
        ProxyPath    = "/{service:[a-zA-Z0-9]+}"
        Namespace    = "go.micro.api"
        HeaderPrefix = "X-Micro-"
    )
```



如果我们在运行 `micro api` 命令时没有手动指定这些参数，则使用上述默认参数值，你可以参照 [Micro API 官方文档](https://micro.mu/docs/api.html)进行一些自定义配置，比如启用 HTTPS、设置命名空间等，在之前的示例中，由于我们希望对外提供 HTTP 接口，所以带上了 `--handler=api` 这个参数，用于指定处理器为 API（默认是 `meta`），其它一般保持默认值即可。



执行 `micro api` 命令对应的核心逻辑都位于 `micro/api/api.go` 的 `run` 函数中，在该函数中，首先读取命令参数并将其赋值给全局变量，比如 `address`、`handler`、`server_name`、`resolver`、`namespace` 等：



```
    if len(ctx.GlobalString("server_name")) > 0 {
        Name = ctx.GlobalString("server_name")
    }


    if len(ctx.String("address")) > 0 {
        Address = ctx.String("address")
    }
    
    if len(ctx.String("handler")) > 0 {
        Handler = ctx.String("handler")
    }


    if len(ctx.String("namespace")) > 0 {
        Namespace = ctx.String("namespace")
    }
    
    if len(ctx.String("resolver")) > 0 {
        Resolver = ctx.String("resolver")
    }
    
    ...
    
    var opts []server.Option


    if ctx.GlobalBool("enable_acme") {
        hosts := helper.ACMEHosts(ctx)
        opts = append(opts, server.EnableACME(true))
        opts = append(opts, server.ACMEHosts(hosts...))
    } else if ctx.GlobalBool("enable_tls") {
        config, err := helper.TLSConfig(ctx)
        if err != nil {
           fmt.Println(err.Error())
           return
        }
        
        opts = append(opts, server.EnableTLS(true))
        opts = append(opts, server.TLSConfig(config))
    }
```



- `server_name`：用于设置 API 网关服务器的名称，默认是 `go.micro.api`；
- `address`：用于设置 API 网关监听端口，默认是 8080，客户端根据 API 网关的公网 IP 和这个端口号即可与这个 API 网关进行通信；`namespace`：用于设置 API 服务的命名空间，默认是 `go.micro.api`；
- `handler`：用于设置 API 网关的请求处理器，默认是 `meta`，这些处理器可用于决定请求路由如何处理，Go Micro 支持的所有处理器可以查看这里：`micro/go-micro/api/handler`，也可以查看[这篇文档](https://micro.mu/docs/go-api.html)了解；
- `resolver`：用于将 HTTP 请求路由映射到对应的后端 API 服务接口，默认是 `micro`，和 `handler` 类似，你可以到 `micro/go-micro/api/resolver` 路径下查看 Go Micro 支持的所有解析器，也可以通过[官方文档](https://micro.mu/docs/api.html#resolver)查看这些解析器的功能明细，`micro` 解析器文档中介绍的 `rpc` 解析器处理逻辑一致，这里就不再赘述了。



有了以上参数的简单介绍，想必你已经对 Micro API 网关的功能和实现有了大致的了解，我们继续往后看，接下来，会根据是否设置 `enable_acme` 或 `enable_tls` 参数对服务器进行初始化设置，决定是否要启用 HTTPS、以及为哪些服务器启用。



完成这些全局参数配置后，就会正式进入 Micro API 网关初始化、客户端请求监听以及动态路由的解析和处理工作，我们将在下一篇分享中给大家介绍这些底层实现细节。