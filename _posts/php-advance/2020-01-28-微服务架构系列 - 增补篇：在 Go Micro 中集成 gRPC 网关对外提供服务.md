---
title: 微服务架构系列 - 增补篇：在 Go Micro 中集成 gRPC 网关对外提供服务
layout: post
category: blog
tags: |-
  PHP
  微服务架构系列
  Go Micro
  gRPC
---

# 微服务架构系列（三十六）



[gRPC](https://grpc.io/) 是由一个 Google 公司开发的、基于 HTTP/2 和 [Protobuf](https://articles.zsxq.com/id_onhnam0pwp37.html) 的高性能开源通用 RPC 框架，且支持多种语言，如 Go、Java、Python、PHP、Node.js、C++、C#、Ruby、Dart、Android Java、Object-C 等，我们可以直接通过该框架构建微服务，也可以在 Go Micro 框架中集成它来提供 gRPC 网关对外提供服务，下面我们简单演示下如何在 Go Micro 框架中实现 gRPC 网关并通过 gRPC 编写后台服务。



**准备工作**



我们仍然在之前创建的示例项目 `hello` 中进行演示。开始之前，我们需要在系统安装 protobuf 工具以及 proto-gen-go 和 proto-gen-grpc-gateway 插件用于代码生成，protobuf 和 proto-gen-go 已经在[基于 Go Micro 框架构建一个简单的微服务接口](https://articles.zsxq.com/id_lmlud4cnsncw.html)这篇教程中安装过了，这里只需要安装下 proto-gen-grpc-gateway 即可，该插件会在 protobuf 编译器生成反向代理时使用，用于将 HTTP 请求转化为 gRPC 调用：



```
    go get -u github.com/grpc-ecosystem/grpc-gateway/protoc-gen-grpc-gateway
```

  

安装完成后还会在 `bin` 目录下生成对应的二进制可执行文件，不过我们一般不会直接调用它：



![img](/assets/post/FjWDaW_8YtMEicDqYLqpRQyMTdWm.png)



**编写 gRPC 服务**



接下来，我们将通过 `github.com/micro/go-micro/service/grpc` 包提供的方法来创建 gRPC 服务，在 `hello/src` 目录下创建 `greeter` 子目录来存放服务代码，和之前 `hello` 服务一样，我们也在 `greeter` 目录下创建一个 `proto` 目录，并在该目录下新增 `greeter.proto` 文件，初始化代码如下：



```go
    syntax = "proto3";
    package greeter;
    
    service Greeter {
        rpc Hello(Request) returns (Response) {}
    }
    
    message Request {
        string name = 1;
    }
    
    message Response {
        string msg = 1;
    }
```

  

然后我们在 `greeter` 目录下运行如下命令生成 gRPC 服务相关代码：



```
    protoc --proto_path=. --micro_out=. --go_out=. proto/greeter.proto
```

  

接下来，在 `greeter` 目录下创建 `main.go` 定义服务启动代码：



```go
    package main


    import (
        "context"
        "github.com/micro/go-micro"
        "github.com/micro/go-micro/service/grpc"
        greeter "greeter/proto"
        "log"
    )
    
    type Greeter struct {}
    
    func (g *Greeter) Hello(ctx context.Context, req *greeter.Request, rsp *greeter.Response) error {
        log.Println("获取 Greeter.Request 请求")
        rsp.Msg = "你好，" + req.Name
        return nil
    }
    
    func main()  {
        service := grpc.NewService(
            micro.Name("go.micro.srv.greeter.grpc"),
        )
    
        service.Init()
    
        greeter.RegisterGreeterHandler(service.Server(), new(Greeter))
    
        if err := service.Run(); err != nil {
            log.Fatalln(err)
        }
    }
```

  

基本代码和之前的 `hello/main.go` 一致，唯一不同的是这里通过 `grpc.NewService()` 方法初始化服务，所以对应 service 下的 client 实例是 `grpcClient` 对象实例，server 实例是 `grpcServer` 对象实例，底层客户端请求调用和服务端监听实现都是通过更底层的 [google.golang.org/grpc](https://github.com/grpc/grpc-go) 包完成。除此之外，其他逻辑和之前的分析都是一致的。



**实现 gRPC 网关**



完成服务端代码编写后，我们再来实现 gRPC 网关，客户端请求会首先到达 gRPC 网关，再由 gRPC 网关将请求转发给上面启动的服务（将 HTTP 请求转化为 gRPC 调用）。



仿照之前的 [API 网关实现](https://articles.zsxq.com/id_kwcbpk9joi7t.html)，我们在 `greeter` 目录下新增一个 `grpc` 子目录（这里完全是为了保持队形，其实 grpc 网关应该放到与 `greeter` 并列的位置，因为一个网关可能会处理多个微服务请求转发），然后在该目录下新增 `proto` 目录，并在 `proto` 目录下创建 `greeter.proto` 文件，初始化代码如下：



```go
    syntax = "proto3";
    package grpc.gateway.greeter;
    
    import "google/api/annotations.proto";
    
    service Greeter {
        rpc Hello(Request) returns (Response) {
            option (google.api.http) = {
                post: "/greeter/hello"
                body: "*"
            };
        }
    }
    
    message Request {
        string name = 1;
    }
    
    message Response {
        string msg = 1;
    }
```

  

接下来在 `greeter/grpc` 目录下运行如下命令生成 gRPC 的存根和反向代理文件：



```go
    protoc -I/usr/local/include -I. \
      -I../../ \
      -I../../github.com/grpc-ecosystem/grpc-gateway/third_party/googleapis \
      --go_out=plugins=grpc:. \
      proto/greeter.proto
    protoc -I/usr/local/include -I. \
      -I../../ \
      -I../../github.com/grpc-ecosystem/grpc-gateway/third_party/googleapis \
      --grpc-gateway_out=logtostderr=true:. \
      proto/greeter.proto
```



生成成功后在 `grpc` 目录下新增一个 `main.go` 文件，定义 gRPC 网关启动代码如下：



```go
    package main


    import (
        "flag"
        "github.com/golang/glog"
        "github.com/grpc-ecosystem/grpc-gateway/runtime"
        "golang.org/x/net/context"
        "google.golang.org/grpc"
        greeter "greeter/grpc/proto"
        "net/http"
    )
    
    var (
        // greeter 服务运行地址和端口
        endpoint = flag.String("endpoint", "localhost:9090", "greeter service address")
    )
    
    func run() error {
        ctx := context.Background()
        ctx, cancel := context.WithCancel(ctx)
        defer cancel()
    
        mux := runtime.NewServeMux()
        opts := []grpc.DialOption{grpc.WithInsecure()}
    
        err := greeter.RegisterGreeterHandlerFromEndpoint(ctx, mux, *endpoint, opts)
        if err != nil {
            return err
        }
    
        return http.ListenAndServe(":8080", mux)
    }
    
    func main() {
        flag.Parse()
    
        defer glog.Flush()
    
        if err := run(); err != nil {
            glog.Fatal(err)
        }
    }
```



**测试 gRPC 服务调用**



至此，我们就已经完成了 gRPC 后台服务和网关代码的编写，最后我们来简单测试下通过 gRPC 网关对 gRPC 服务进行调用。



我们基于 Consul 作为注册中心，所以要现在系统中确保 Consul 已经启动：



```
    consul agent -dev
```

  

然后我们通过如下命令启动 gRPC 后台服务，指定注册中心为 consul、服务运行地址为 `localhost:9090`（假设我们在 `greeter/grpc` 目录中运行该命令）：



```
    go run ../main.go --registry=consul --server_address=localhost:9090
```

  

![img](/assets/post/FkSbS6HMZVO8is-Uaq7wL3BuYfJI.png)



通过启动日志可以看到 Server 运行在 grpcServer 之上，服务也已经注册成功：



![img](/assets/post/FjI1NbMsngmZzzuYzKumH34MRPsk.png)



接下来，我们还是在 `greeter/grpc` 目录下启动 gRPC 网关：



```
    go run main.go
```



如果没有报错，即表示启动成功，最后我们可以新开一个 Terminal 窗口通过 curl 命令模拟客户端请求：



![img](/assets/post/FlhRtmNboUiVXxOQ7P_1bu997t4d.png)



返回结果符合预期，表示通过 gRPC 网关（监听 `localhost:8080` 端口）调用远程 gRPC 服务成功。