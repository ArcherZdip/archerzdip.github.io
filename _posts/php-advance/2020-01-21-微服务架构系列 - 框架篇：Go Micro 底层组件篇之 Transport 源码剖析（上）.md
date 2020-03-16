---
title: 微服务架构系列 - 框架篇：Go Micro 底层组件篇之 Transport 源码剖析（上）
layout: post
category: blog
tags: |-
  PHP
  微服务架构系列
  框架篇
  Go Micro
  Transport
---

# 微服务架构系列（三十）



介绍了 Go Micro 底层的 Registry 和  Selector 组件底层实现，这两个组件是用于服务注册和发现的，即找到微服务部署的节点进行通信，接下来我们来看下在部署微服务的每个节点上是如何监听客户端请求并进行处理的，我们先从 Transport 组件开始。



顾名思义，Transport 组件用于实现微服务的传输层，Go Micro 默认支持基于 HTTP 和 gRPC 协议进行通信，此外还可以通过 Go Plugins 引入 RabbitMQ、TCP、UDP、NATS 等协议支持，和 Registry 组件类似，我们可以通过系统环境变量 `MICRO_TRANSPORT` 或者命令行参数 `--transport` 指定传输协议，如果不设置的话默认使用 HTTP 传输协议。



我们先从服务端开始分析 Transport 组件是如何工作的，打开 `src/hello/main.go`，在 `main` 方法中，调用 `micro.NewService` 方法初始化服务的时候，就会通过 newOptions 设置默认的传输实现：



```go
    opt := Options{
        Broker:    broker.DefaultBroker,
        Cmd:       cmd.DefaultCmd,
        Client:    client.DefaultClient,
        Server:    server.DefaultServer,
        Registry:  registry.DefaultRegistry,
        Transport: transport.DefaultTransport,
        Context:   context.Background(),
    }
```

  

对应的 `transport.DefaultTransport` 指向 `src/github.com/micro/go-micro/transport/transport.go` 的这一行代码：



```
    DefaultTransport Transport = newHTTPTransport()
```

  

接下来就是调用 `newHTTPTransport` 初始化默认 Transport 组件 —— `httpTransport`，对应源码位于同目录下的 `http_transport.go` 文件中。



然后回到 `main.go`，如果我们在服务端系统环境变量中设置了 `MICRO_TRANSPORT` 或者在启动微服务时通过命令行参数 `--transport` 设置了指定传输协议，则会在执行 `service.Init()` 时通过指定设置初始化对应的 Transport 组件来覆盖默认设置，相关原理和 Registry 类似，这里不再重复展开，下面都以默认的 `httpTransport` 为例进行介绍。



再往后，服务端通过 `proto.RegisterGreeterHandler` 方法将实现了 `GreeterHandler` 接口的 `Greeter` 类注册到默认的 Server 接口实现类 `rpcServer` 的路由器中（对应源码位于 `src/github.com/micro/go-micro/server/rpc_router.go` 中），作为客户端请求 `Greeter` 类中方法的处理器。在具体注册时会循环遍历处理器类（Greeter）中的所有方法，然后以处理器名称（Greeter）+ 方法的形式组合成多个服务端点（Endpoint），比如这里只定义了一个 `Hello` 方法，那就是 `Greeter.Hello`，当客户端通过 `go.micro.srv.greeter` 服务名称从 Selector 组件中获取到指定的服务节点并建立连接后，调用 `Greeter.Hello` 服务端点时，最终会执行到 `Greeter` 类的 `Hello` 方法并将结果返回给客户端。



最后走到 `main.go` 中最关键的一行，服务运行代码 `service.Run()`，启动服务的逻辑最终会走到 `src/github.com/micro/go-micro/service.go` 的 `Start` 方法，这里又会调用 Server 组件默认实现 `rpcServer` 的 `Start` 方法，接下来有微服务启动、注册、监听的所有核心逻辑都在这个 `Start` 方法中了，我们只截取与 Transport 有关的部分进行解析：



```go
    func (s *rpcServer) Start() error {
        
        ...
    
        // start listening on the transport
        ts, err := config.Transport.Listen(config.Address)
        if err != nil {
            return err
        }
    
        log.Logf("Transport [%s] Listening on %s", config.Transport.String(), ts.Addr())
    
        ...
    
        exit := make(chan bool)
    
        go func() {
            for {
                // listen for connections
                err := ts.Accept(s.ServeConn)
    
                ...
    
                select {
                // check if we're supposed to exit
                case <-exit:
                    return
                // check the error and backoff
                default:
                    if err != nil {
                        log.Logf("Accept error: %v", err)
                        time.Sleep(time.Second)
                        continue
                    }
                }
    
                // no error just exit
                return
            }
        }()
    
        go func() {
            ...
    
            // close transport listener
            ch <- ts.Close()
    
            ...
        }()
    
        return nil
    }
```

  

首先调用 `config.Transport.Listen` 方法监听请求数据传输，这里的 `config.Transport` 即默认的 Transport 实现 `httpTransport` 实例，对应的 `Listen` 方法会调用 [net](https://golang.org/pkg/net/) 包的 `Listen` 方法监听指定 IP 地址和端口上的 TCP 请求（默认地址为空，默认端口号为0，在执行 Listen 方法过程中会分配一个 Transport 监听端口），并最终返回 `httpTransportListener` 实例赋值给 `ts`， 接下来会打印 Transport 监听日志，就是我们运行 `go run main.go` 打印的第一行日志：

![image-20200113112800157](/assets/post/image-20200113112800157.png)

然后会初始化消息组件 Broker（后面会单独介绍）并将 rpcServer 注册到注册中心（对应实现已经在[服务注册](https://articles.zsxq.com/id_u80jrj77p5p2.html)中介绍过）以便被客户端调用。



接下来，启动一个协程，以无限循环方式调用 `ts.Accept` 在当前 rpcServer 服务连接 `s.ServeConn` 上接收客户端请求并进行处理，`ts.Accept` 对应 `httpTransportListener` 的 `Accept` 方法：



```go
    func (h *httpTransportListener) Accept(fn func(Socket)) error {
        // create handler mux
        mux := http.NewServeMux()
    
        // register our transport handler
        mux.HandleFunc("/", func(w http.ResponseWriter, r *http.Request) {
            var buf *bufio.ReadWriter
            var con net.Conn
    
            // read a regular request
            if r.ProtoMajor == 1 {
                b, err := ioutil.ReadAll(r.Body)
                if err != nil {
                    http.Error(w, err.Error(), http.StatusInternalServerError)
                    return
                }
                r.Body = ioutil.NopCloser(bytes.NewReader(b))
                // hijack the conn
                hj, ok := w.(http.Hijacker)
                if !ok {
                    // we're screwed
                    http.Error(w, "cannot serve conn", http.StatusInternalServerError)
                    return
                }
    
                conn, bufrw, err := hj.Hijack()
                if err != nil {
                    http.Error(w, err.Error(), http.StatusInternalServerError)
                    return
                }
                defer conn.Close()
                buf = bufrw
                con = conn
            }
    
            // save the request
            ch := make(chan *http.Request, 1)
            ch <- r
    
            fn(&httpTransportSocket{
                ht:     h.ht,
                w:      w,
                r:      r,
                rw:     buf,
                ch:     ch,
                conn:   con,
                local:  h.Addr(),
                remote: r.RemoteAddr,
            })
        })
    
        ...
    
        // default http2 server
        srv := &http.Server{
            Handler: mux,
        }
    
        // insecure connection use h2c
        if !(h.ht.opts.Secure || h.ht.opts.TLSConfig != nil) {
            srv.Handler = h2c.NewHandler(mux, &http2.Server{})
        }
    
        // begin serving
        return srv.Serve(h.listener)
    }
```

在该方法中，会先调用 [net/http](https://golang.org/pkg/net/http/) 包的 `NewServeMux` 方法初始化一个 ServeMux 实例来注册请求处理器函数，这里注册的请求路由模式是 `/`，即所有请求都会走到这里，处理器函数是一个匿名函数，在这个函数中，会从请求中获取请求信息，并将请求的生命周期交给 Hijacker 去管理，在所有请求数据接收完毕后，会将上述初始化的变量设置到 `httpTransportSocket` 实例，并将这个实例传入 `Accept` 方法接收的函数 `rpcServer.ServeConn` 中进行请求信息获取和编码、处理器匹配和业务逻辑执行以及响应编码和发送等（后两块具体实现细节位于 `rpc_router.go` 文件中）。这里的实现可类比 Laravel 中通过匿名函数注册路由，这里服务启动的时候并没有真正执行，而是要等到用户请求过来时才会真正执行。



> 注：[net/http](https://golang.org/pkg/net/http/) 包可用于在 Go 语言中实现 HTTP 客户端和服务端。



接下来，`httpTransport` 会通过默认 HTTP 服务器监听客户端请求，并且将上述已注册请求处理器的 `mux` 实例设置到服务器的 Handler 属性上来，如果启用了 HTTPS 的话，则会通过 HTTP/2 服务器监听请求。最后调用服务器的 `Serve` 方法并传入 `httpTansportListener` 的监听器实例（与前面 `rpc_server.go` 中的 `ts.listener` 是一个对象实例），该方法是最终的 HTTP（HTTP/2） 服务器底层服务监听和请求处理实现，同样以协程方式运行。



所以具体到每个远程服务路由匹配和执行存在着多层封装，在业务层我们只要关心最上册的实现即可，即 `Greeter` 类的 `Hello` 方法，该处理器方法通过 Protobuf 生成的 `hello.micro.go` 提供的 `RegisterGreeterHandler` 方法注册到上层 Server 层，然后在服务启动时，通过 Server 实现类的 `Start` 方法聚合 Transport 实现类的 `Accept` 方法监听客户端连接和请求，在 `Accept` 方法底层会根据不同实现传输协议实现的组件不同而启动不同的服务器，以 `httpTransport` 为例，启动的是 HTTP 服务器，在 `httpTransport` 组件中，我们将 Server 实现类的连接处理函数调用封装到请求处理器中，之所以把连接处理放到 Server 层，是因为需要在 Server 层做编解码处理，而且之前的业务层处理器也注册到 Server 层路由器了，这样一来实际上是解耦了 Transport 层对编解码的依赖，方便扩展。但是最终的请求处理和响应发送逻辑还是在对应的最底层 HTTP 服务器中完成，只是把具体实现层层往上抛，方便了开发者去实现具体的业务逻辑。



下面我们回到 `rpc_server.go` 的 `Start` 方法，在 `ts.Accept` 所在的协程语句中，如果收到退出信号（主动退出或者异常退出），则会退出这个协程，否则该协程会一直运行。



再往下，还有另一个协程，这个协程负责监听服务节点本身健康状态，如果设置了间隔指定时间重新注册的话，则每隔指定时间后重新注册该服务节点，如果收到退出信号则停掉计时器，退出循环，如果还有请求在处理，则等待这些请求处理完再关闭 Transport 监听器、Broker 连接、设置服务节点地址端口信息为空，最后退出协程。



以上就是 Transport 组件默认实现 `httpTransport` 类在服务端的底层实现和处理逻辑，下篇分享我们来看看客户端通过查询注册中心获取到服务节点后是如何访问到具体的服务端点的。