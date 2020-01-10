---
title: 微服务架构系列 - 框架篇：Go Micro 中的 API 网关底层实现原理（下）
layout: post
category: blog
tags: |-
  PHP
  微服务架构系列
  Go Micro
  API网关
---



# 微服务架构系列（十二）



上篇分享学院君介绍了 Micro API 底层如何解析命令行参数并设置全局初始值，接下来，我们来看看 Micro API 网关启动之后，是如何将 HTTP 请求映射到对应的 API 处理器进行处理的。



Micro API 底层基于 [gorilla/mux](https://github.com/gorilla/mux) 包实现 HTTP 请求路由的分发，首先我们基于 mux 的 `NewRouter` 函数创建一个路由器并将其赋值给 HTTP 处理器 `h`（`r` 是一个指针类型，所以 `h` 指向的是 `r` 的引用）：



```
    // create the router
    var h http.Handler
    r := mux.NewRouter()
    h = r
```

  

然后经过一些服务端全局参数的设置后，传入这些全局参数来初始化服务：



```
    service := micro.NewService(srvOpts...)
```

  

接下来，会注册 RPC 请求处理器：



```
    log.Logf("Registering RPC Handler at %s", RPCPath)
    r.HandleFunc(RPCPath, handler.RPC)
```

  

默认 RPC 请求路径是 `/rpc`，客户端需要通过这个路径发起 POST 请求，请求参数一般是 JSON 格式数据或者编码过的 RPC 表单请求（参考[文档示例](https://micro.mu/docs/api.html#query)），处理器底层会将 RPC 请求转化为对 Go Micro 底层服务的请求，对应的处理器源码位于 `micro/micro/internal/handler/rpc.go` 中。



接下来，会初始化解析器并注册 API 请求处理器（以我们启动 API 网关时传入 `--handler=api` 参数为例）：

  

```
    // 解析器参数
    ropts := []resolver.Option{
        resolver.WithNamespace(Namespace),
        resolver.WithHandler(Handler),
    }

    // 初始化默认解析器
    rr := rrmicro.NewResolver(ropts...)

    ...

    // 注册 API 请求处理器
    switch Handler {
    ...
    case "api":
        log.Logf("Registering API Request Handler at %s", APIPath)
        rt := regRouter.NewRouter(
            router.WithNamespace(Namespace),
            router.WithHandler(aapi.Handler),
            router.WithResolver(rr),
            router.WithRegistry(service.Options().Registry),
        )
        ap := aapi.NewHandler(
            ahandler.WithNamespace(Namespace),
            ahandler.WithRouter(rt),
            ahandler.WithService(service),
        )
        r.PathPrefix(APIPath).Handler(ap)
    ...
    }
```



默认的命名空间是 `go.micro.api`，默认的解析器是 `micro`（对应源码位于 `micro/go-micro/api/resolver/micro/micro.go`），然后会传入上述初始化的参数到 `regRouter.NewRouter` 函数来创建新的 API 路由器（对应源码位于 `micro/go-micro/api/router/registry/registry.go`），再通过这个路由器实例和之前初始化的服务实例（包含默认 Registry、Transport、Broker、Client、Server 配置，以便后续通过这些配置根据服务名和请求参数对底层服务发起请求）来创建 API 处理器（对应源码位于 `micro/go-micro/api/handler/api/api.go`），最后，把 API 请求路径前缀和 API 处理器设置到之前创建的路由器 `r` 上。



最后，我们初始化用作 API 网关的 HTTP 服务器并启动它（对应源码位于 `micro/go-micro/api/server/http/http.go`）：



```
    api := httpapi.NewServer(Address)
    api.Init(opts...)
    api.Handle("/", h)


    // Start API
    if err := api.Start(); err != nil {
        log.Fatal(err)
    }
```



当有 HTTP 请求过来时，该网关服务器就可以对其进行解析（通过上述初始化的 Resolver）和处理（通过 API 请求处理器处理），并将结果返回给客户端（相应源码位于 `micro/go-micro/api/handler/api/api.go` 的 `ServeHTTP` 方法，以协程方式启动服务器对客户端请求进行处理，底层服务调用逻辑和我们前面介绍的[客户端服务发现原理](https://articles.zsxq.com/id_q03mr8l3jvpp.html)一致），以上就是 Micro API 网关的底层实现源码，我们可以看到这个默认的 API 网关采用的是 [API 网关架构模式](https://articles.zsxq.com/id_ljgtftbax4ne.html)的第一种模式：单节点网关模式，所有的 API 请求都会经过这个单一入口对底层服务进行请求。