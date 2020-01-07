---
title: 微服务架构系列 - 框架篇：通过 HTTP 请求调用 Go Micro 提供的微服务接口
layout: post
category: blog
tags: |-
  PHP
  微服务架构系列
  Go Micro
---



# 微服务架构系列（七）



我们简单介绍了基于 Go Micro 框架构建第一个微服务接口，并且编写了简单的客户端测试代码，但是这个客户端代码也是集成在 Go Micro 项目中的，需要调用相应的 Go Micro SDK 才能向服务端接口发起请求，如果我们的服务消费者不是基于 Go 语言，而是基于其它语言，如 PHP，这个时候，要怎样从 PHP 项目中调用 Go Micro 提供的微服务接口呢？



我们可以基于 Go Micro 自带的 API 网关功能来实现通过 HTTP 请求从其他语言编写的系统中调用 Go Micro 提供的微服务接口，该 API 网关底层会通过服务发现、负载均衡、RPC 通信来实现对 HTTP 请求和路由的处理。下面我们来简单演示一下其实现流程。



**0、安装 Micro**



首先我们需要安装微服务开发运行时依赖 [Micro](https://github.com/micro/micro)，以便可以通过它来启动微服务 API 网关：



```
    go get github.com/micro/micro
```

  

安装成功后，可以在项目根目下的 `bin` 目录中看到 `micro` 可执行文件：



![img](/assets/post/68f83cdca775f5b11834aca4ca215451adcf4ab7a4da8f45dae780be982b3330.png)



**1、编写 API 接口代码**



接下来，我们来编写 API 网关接口代码，开始之前，我们打开之前编写的服务端文件 `~/go/hello/src/hello/main.go`，将底层服务名称调整为 `go.micro.srv.greeter` 以便于和 API 服务接口名做区分：



```go
    func main() {
        
        service := micro.NewService(
            micro.Name("go.micro.srv.greeter"),
        )
        
        ...
    }
```

  

然后在 `~/go/hello/src/hello` 目录下创建一个子目录 `api`，用于存放 API 接口相关代码，我们先创建一个 `api.go` 文件来存放此次服务接口的处理代码：



```go
    package main
    
    import (
        "context"
        "encoding/json"
        "github.com/micro/go-micro"
        api "github.com/micro/go-micro/api/proto"
        "github.com/micro/go-micro/errors"
        hello "hello/proto"
        "log"
        "strings"
    )
    
    type Say struct {
        Client hello.GreeterService
    }
    
    func (s *Say) Hello(ctx context.Context, req *api.Request, rsp *api.Response) error {
        log.Print("收到 Say.Hello API 请求")
        
        // 从请求参数中获取 name 值
        name, ok := req.Get["name"]
        if !ok || len(name.Values) == 0 {
            return errors.BadRequest("go.micro.api.greeter", "名字不能为空")
        }
        
        // 将参数交由底层服务处理
        response, err := s.Client.Hello(ctx, &hello.HelloRequest{
            Name: strings.Join(name.Values, " "),
        })
        if err != nil {
            return err
        }
        
        // 处理成功，则返回处理结果
        rsp.StatusCode = 200
        b, _ := json.Marshal(map[string]string{
            "message": response.Greeting,
        })
        rsp.Body = string(b)
    
        return nil
    }
    
    func main() {
        // 创建一个新的服务
        service := micro.NewService(
            micro.Name("go.micro.api.greeter"),
        )
    
        // 解析命令行参数
        service.Init()
    
        // 将请求转发给底层 go.micro.srv.greeter 服务处理
        service.Server().Handle(
            service.Server().NewHandler(
                &Say{Client: hello.NewGreeterService("go.micro.srv.greeter", service.Client())},
            ),
        )
     
        // 运行服务
        if err := service.Run(); err != nil {
            log.Fatal(err)
        }
    }
```



**2、启动 API 网关**



编写好 API 接口代码后，依次启动 `main.go` 和 `api.go`，最后通过 `micro api` 启动 API 网关等待客户端请求：



```
    cd ~/go/hello/src/hello
    go run main.go
    go run api/api.go  // API 依赖底层 go.micro.srv.greeter 服务
    micro api --handler=api // 启动 API 网关处理 HTTP 请求，--handle 参数不能为空，否则可能报错
```

  

`micro api` 启动过程中，可以看到注册了 RPC 处理器和 API 请求处理器，HTTP 请求监听端口默认是 `8080`：

  

![img](/assets/post/36515b4c57f22282d5c91e7a4613000ebf90e32d6852d7372bee08c59fed778e.png)



以上代码执行完毕，所有服务启动就绪后，可以在 Consul Web 页面中看到注册的服务信息如下：



![img](/assets/post/f3345ed3f044113f8dfcbe2854f233e7fe0abeae2d7854b3ce3b0fe5ea523464.png)



**3、调用微服务接口**



接下来，我们就可以从客户端调用注册到 Consul 的服务，具体有两种方式，一种是通过 API 接口，另一种是绕过 API 接口直接调用底层的 RPC 服务：



1）API 接口



我们可以通过 `http://localhost:8080/greeter/say/hello` 对 API 接口进行请求，该 URL 会被路由到 `go.micro.api.greeter` 服务的 `Say.Hello` 方法进行处理，如果不传递 `name` 参数，会报错：



![img](/assets/post/832bc4131256219ff3634eef139d7d1c9fef6c462ef391c71fdca9f5ea7202dc.png)



如果按照服务接口约定传递参数，则返回问候信息，该问候信息最终是通过 `go.micro.srv.greeter` 服务的 `Greeter.Hello` 方法生成并返回，再由 `go.micro.api.greeter` 服务的 `Say.Hello` 方法包装后返回给客户端：



![img](/assets/post/aadbc9adf5625e2c321509755786b2de49492e0d53df52f7b09a0509480c8504.png)



你可以在 PHP 中通过 [curl](https://www.php.net/manual/zh/book.curl.php) 扩展或者 [Guzzle](https://github.com/guzzle/guzzle) 包对上述 API 接口请求进行封装，从而实现在 PHP 中请求 Go Micro 微服务接口



2）RPC 接口



此外，我们还可以通过下面这种调用方式绕开 API 接口，直接调用 `go.micro.srv.greeter` 提供的 RPC 服务接口，此时需要通过 POST 方式对 `http://localhost:8080/rpc` 发起请求，并在请求实体中带上服务名、方法以及请求参数信息：



```
    curl -H 'Content-Type: application/json' \
        -d '{"service": "go.micro.srv.greeter", "method": "Greeter.Hello", "request": {"name": " 学院君"}}' \
        http://localhost:8080/rpc
```

​    

如果是在 Postman 中，则演示结果界面如下：



![img](/assets/post/be1b68e30cb3c3b3bd6a214c713c086b0ba6e674634648a2fdbedb76f6507db13.png)



好了，以上就是从其他语言实现的服务消费端调用 Go Micro 微服务接口的简单示例。现在，我们已经简单串起了客户端、API 网关和微服务接口调用这条完整链路，后续我们将就其中的底层实现细节以及实际生产环境中更复杂场景的优化进行介绍，此外，我们还将就服务治理、监控以及故障定位在 Go Micro 中的落地进行介绍。