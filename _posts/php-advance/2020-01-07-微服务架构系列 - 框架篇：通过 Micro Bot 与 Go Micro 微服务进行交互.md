---
title: 微服务架构系列 - 框架篇：通过 Micro Bot 与 Go Micro 微服务进行交互
layout: post
category: blog
tags: |-
  PHP
  微服务架构系列
  Go Micro
  Micro Bot
---

# 微服务架构系列（十八） 

介绍了通过 Micro CLI 与 Go Micro 微服务进行交互，除此之外，Micro 生态还支持通过机器人与 Go Micro 进行交互，我们可以在 Slack、HipChat、XMPP 之类的协作工具中通过发送消息的方式查看或 Go Micro 提供的服务，就像是背后是个机器人一样。这里我们以 Slack 为例进行演示。



**Micro Bot 的基本使用**



首先我们在 `hello` 项目根目录下运行 `micro bot` 命令启动服务：



```
    micro bot --inputs=slack --slack_token=SLACK_TOKEN
```



> 注：将 SLACK_TOKEN 替换成你自己的 token 值。

![img](/assets/post/8570282ee94d84285213efda631ef1ae45a86cf15cbc18972621c7ecfc7175d7.png)

然后我们到自己的 Slack Workspace 输入指令即可与 Go Micro 微服务进行交互，首先通过 `help` 查看可以使用哪些指令：



![img](/assets/post/f504eaf07902e05405b886e6081ee4586d78ded473b74252f19f274d070e2dbd.png)



这些指令和我们上篇分享介绍的 Micro CLI 差不多，我们可以简单测试下：



![img](/assets/post/25c9f78d84416540722d41ca73d1815cd60fdd41aafaf9ab0d1938aa96c66d94.png)



可以看到，我们在输入框输出指令后回车，就会从 Go Micro 微服务处返回对应的信息并显示在屏幕上。



Micro 还支持通过 HipChat 实现类似的功能，具体可以参考[官方文档](https://micro.mu/docs/bot.html)，我们可以在这些协作工具中通过发送消息的方式到 Go Micro 的注册中心注册/反注册服务，也可以查看或调用 Go Micro 对外提供的微服务接口，此时，Micro Bot 所承担的角色和 Micro CLI、Micro API、Micro Web 一样，只不过请求输入方式变成了消息而已。



![img](/assets/post/c26fec33adfb9ec0bbba9fd98f3af6c2750d0474c91c54ecd1b9fe6127f1effa.png)



**注册新指令**



除了使用系统内置的指令之外，还可以自己编写指令注册到 Micro Bot 中，在 `src/github.com/micro/micro/main.go` 中，可以这么定义一个 `greeting` 指令：



```
    import "github.com/micro/go-micro/agent/command"
    
    func Greeting() command.Command {
        usage := "greeting"         // 指令名
        desc := "Returns greetings"  // 指令说明
    
        // 定义这个指令并返回
        return command.NewCommand("greeting", usage, desc, func(args ...string) ([]byte, error) {
            return []byte("你好,主人"), nil
        })
    }
```

  

然后在该文件中定义一个 `init` 方法注册 `ping` 指令（`init` 在 `main` 方法之前运行）：



```
    func init()  {
        command.Commands["^greeting$"] = Greeting()
    }
```

  

然后我们需要到 bin 目录下重构 `micro` 使新指令生效：



```
    cd bin
    go build -i -o micro ../src/github.com/micro/micro/main.go
```

  

然后重新启动 `micro bot`，到 Slack 中输入 `help` 就可以看到 `greeting` 指令了：



![img](/assets/post/f3b2ae7277cc37535d9fe1618c08db7daecb2331076a7a659a2e9c2025b9c617.png)



**扩展 Micro Bot 支持的终端**



此外，你还可以扩展系统支持的协作工具，这需要实现 Input 接口（位于 `src/github.com/micro/go-micro/agent/input/input.go`），你可以参考 Slack 的实现来构建通过微信与 Go Micro 微服务交互的消息输入终端：



```
    // Input is an interface for sources which
    // provide a way to communicate with the bot.
    // Slack, HipChat, XMPP, etc.
    type Input interface {
        // Provide cli flags
        Flags() []cli.Flag
        // Initialise input using cli context
        Init(*cli.Context) error
        // Stream events from the input
        Stream() (Conn, error)
        // Start the input
        Start() error
        // Stop the input
        Stop() error
        // name of the input
        String() string
    }
```



然后在 `src/github.com/micro/micro/main.go` 的 `init` 方法中注册它：



```
    import "github.com/micro/go-micro/agent/input"
    
    func init() {
        ...
        input.Inputs["wechat"] = WeChat
    }
```

  

最后和新增指令一样，需要重新构建 `micro` 二进制文件并启动 `--input` 选项值为 `wechat` 的 `micro bot` 命令来测试是否生效。



`micro bot` 命令的底层执行逻辑入口文件位于 `src/github.com/micro/micro/bot/bot.go` 中，感兴趣的同学可以去看看，其代码结构和之前介绍的 `micro cli` 和 `micro api` 一样，只是消息源处理有所不同而已。