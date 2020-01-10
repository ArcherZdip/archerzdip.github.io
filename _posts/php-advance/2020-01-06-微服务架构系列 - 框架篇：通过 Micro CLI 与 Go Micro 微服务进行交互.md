---
title: 微服务架构系列 - 框架篇：通过 Micro CLI 与 Go Micro 微服务进行交互
layout: post
category: blog
tags: |-
  PHP
  微服务架构系列
  Go Micro
---



# 微服务架构系列（十七） 

**基本使用**



除了前面介绍的 Web 仪表盘之外，Micro 生态系统还提供了命令行接口与 Go Micro 微服务进行交互，开启命令行模式很简单，运行如下命令即可：



  micro cli

  

![img](/assets/post/454c965a2babe22e9f19f3c3cef018c0ff29c493ee5194d418b0419f5474abe9.png)



然后我们可以通过 `help` 指令查看命令行模式支持的所有指令，其中的部分指令在 Micro Web 的 CLI 界面中也可以执行，或者通过 Registry、Call  这两个可视化的页面查看、测试，但是显然 Micro CLI 模式支持的指令更多，包括服务的注册和反注册、所有服务的列举、单个服务接口信息查看和调用、服务接口健康检测和状态查看等，下面我们就来简单演示下这些指令的执行。



首先我们演示下服务的注册与反注册（除了在命令行交互模式中运行指定指令外，还可以在外部直接通过 `micro` + 命令的方式调用它们）：



![img](/assets/post/63c970e479c3a2d273699f0d13dbbc5c279725526a56f7613fbdf7efa0badfb1.png)



返回 ok 表示操作成功，然后我们来看看某个服务接口的查看、调用、健康、状态指令的执行：



![img](/assets/post/bc4d4eab2c75a95cc2b2a44e8c4be9d292629f186611c97004736d9d7b77020b.png)



我们可以看到 `go.micro.srv.greeter` 服务节点的注册信息，以及服务接口的端点、请求和响应数据结构，下面我们根据这些接口信息通过 `micro call` 指令来调用这个服务：



![img](/assets/post/bb2f71888cb0baf100747314ae1e75baa92fb28229029c17b2a80bdfd39a2e05.png)



返回结果与接口文档描述一致，我们还可以通过 `health` 和 `stats` 来窗口服务的健康、状态信：



![img](/assets/post/cb039a5ba2f85340bf7dca7ec428d558151c7a527f1491f4a4f02134b8d6ced0.png)



`health` 主要用于检查服务节点是否可用（是否健康），`stats` 主要用于检查服务节点的状态信息（启动时间、内存占用、进程及 gc 等信息）。



**源码分析**



`micro cli` 命令行模式支持的指令对应的底层源码位于 `github.com/micro/micro/cli/cli.go` 中：



```go
    var (
        prompt = "micro> "
    
        commands = map[string]*command{
            "quit":       &command{"quit", "Exit the CLI", quit},
            "exit":       &command{"exit", "Exit the CLI", quit},
            "call":       &command{"call", "Call a service", callService},
            "list":       &command{"list", "List services", listServices},
            "get":        &command{"get", "Get service info", getService},
            "stream":     &command{"stream", "Stream a call to a service", streamService},
            "publish":    &command{"publish", "Publish a message to a topic", publish},
            "health":     &command{"health", "Get service health", queryHealth},
            "stats":      &command{"stats", "Get service stats", queryStats},
            "register":   &command{"register", "Register a service", registerService},
            "deregister": &command{"deregister", "Deregister a service", deregisterService},
        }
    )
    
    type command struct {
        name  string
        usage string
        exec  exec
    }
    
    func runc(c *cli.Context) {
        commands["help"] = &command{"help", "CLI usage", help}
        ...
```



`micro cli` 启动后，会开启一个无限循环读取用户输入，然后通过与 `commands` 字典的映射来执行对应的指令：



```go
    for {
        args, err := r.Readline()
        if err != nil {
            fmt.Fprint(os.Stdout, err)
            return
        }


        args = strings.TrimSpace(args)


        // skip no args
        if len(args) == 0 {
            continue
        }


        parts := strings.Split(args, " ")
        if len(parts) == 0 {
            continue
        }


        name := parts[0]


        // get alias
        if n, ok := alias[name]; ok {
            name = n
        }


        if cmd, ok := commands[name]; ok {
            rsp, err := cmd.exec(c, parts[1:])
            if err != nil {
                println(err.Error())
                continue
            }
            println(string(rsp))
        } else {
            println("unknown command")
        }
    }
```

  

`exec` 是一个函数类型，对应的函数已经在上面初始化 `commands` 字典的时候指定好了，每个指令对应的函数定义在 `github.com/micro/micro/cli/helpers.go` 中（`help` 和 `quit` 指令函数定义在 `github.com/micro/micro/cli/commands.go` 中），最终调用的代码位于 `github.com/micro/micro/internal/command/cli/command.go` 里面，具体实现逻辑感兴趣的同学可以自己去看看。



如果是通过 `micro` + 命令的方式直接调用，而不是通过命令行模式交互的话，底层最终调用的函数也是一样的，只是这些指令注册的方式不同，但还是在 `github.com/micro/micro/cli/cli.go` 中：



```go
    func registryCommands() []cli.Command {
        return []cli.Command{
            {
                Name:  "list",
                Usage: "List items in registry",
                Subcommands: []cli.Command{
                    {
                        Name:   "services",
                        Usage:  "List services in registry",
                        Action: printer(listServices),
                    },
                },
            },
            ...
        }
    }
    
    func Commands() []cli.Command {
        commands := []cli.Command{
            ...
            {
                Name:   "call",
                Usage:  "Call a service",
                Action: printer(callService),
            },
            ...
            {
                Name:   "health",
                Usage:  "Query the health of a service",
                Action: printer(queryHealth),
            },
            {
                Name:   "stats",
                Usage:  "Query the stats of a service",
                Action: printer(queryStats),
            },
        }
    
        return append(commands, registryCommands()...)
    }
```



并且在注册时，通过 Action 参数指定对应的底层执行函数，这些函数的定义和命令行交互模式一样，定义在 `github.com/micro/micro/cli/helpers.go` 中，然后当我们在命令行执行某个命令，比如 `micro call`，则 Micro 底层会执行 Action 对应的函数来处理该命令。