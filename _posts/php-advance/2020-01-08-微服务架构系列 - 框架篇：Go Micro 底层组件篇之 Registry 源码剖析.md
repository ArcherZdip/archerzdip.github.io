---
title: 微服务架构系列 - 框架篇：Go Micro 底层组件篇之 Registry 源码剖析
layout: post
category: blog
tags: |-
  PHP
  微服务架构系列
  Go Micro
  Micro Bot
---



# 微服务架构系列（十九）

从今天开始，会花几篇教程的篇幅深入介绍 Go Micro 底层组件的实现原理，包括 Registry、Selector、Transport、Broker、Codec，首先从 Registry 开始。



前面在介绍 Go Micro 框架的服务注册及发现原理时，已经提到了与注册中心打交道的 Registry 组件的底层运作，今天我们来深入剖析其底层源码，让你知其然知其所以然。



Registry 组件的源码位于 `src/github.com/micro/go-micro/registry` 目录下：



![img](/assets/post/b1b867fe09249b114d0f4979773a34649630486d8ca796f9585496a3728e50f4.png)



根目录下定义的 Go 文件是 Registry 组件的一些基类，`registry.go` 中定义了 `Registry` 接口，所有注册中心插件都需要实现该接口提供的方法：



```go
    type Registry interface {
        Init(...Option) error
        Options() Options
        Register(*Service, ...RegisterOption) error
        Deregister(*Service) error
        GetService(string) ([]*Service, error)
        ListServices() ([]*Service, error)
        Watch(...WatchOption) (Watcher, error)
        String() string
    }
```



`Options` 是一个选项类，定义在同目录下单 `options.go` 文件中：



```
    type Options struct {
        Addrs     []string
        Timeout   time.Duration
        Secure    bool
        TLSConfig *tls.Config
        // Other options for implementations of the interface
        // can be stored in a context
        Context context.Context
    }
```

  

其中包含了注册中心地址列表、超时时间、安全设置、上下文信息等字段，如果以函数方式调用这些字段，则每个字段返回的类型都是 `Option`，我们可以通过将这些 `Option` 对象传入 `Init` 方法来初始化指定的注册中心。



`Register` 方法用于注册服务到注册中心，具体注册逻辑因注册中心选择的软件不同而不同，Go Micro 默认注册中心是 `mdns`，对应的注册逻辑在 `mdns.go` 的 `Register` 方法中，我们之前演示时选择的注册中心是 `consul`，对应的注册逻辑位于 `consul/consul.go` 的 `Register` 方法中。该方法第一个参数是一个 `Service` 指针，表示服务节点及端点信息，这些信息对应的类位于同目录下的 `service.go` 文件中：



```
    package registry
    
    type Service struct {
        Name      string            `json:"name"`
        Version   string            `json:"version"`
        Metadata  map[string]string `json:"metadata"`
        Endpoints []*Endpoint       `json:"endpoints"`
        Nodes     []*Node           `json:"nodes"`
    }
    
    type Node struct {
        Id       string            `json:"id"`
        Address  string            `json:"address"`
        Port     int               `json:"port"`
        Metadata map[string]string `json:"metadata"`
    }
    
    type Endpoint struct {
        Name     string            `json:"name"`
        Request  *Value            `json:"request"`
        Response *Value            `json:"response"`
        Metadata map[string]string `json:"metadata"`
    }
    
    type Value struct {
        Name   string   `json:"name"`
        Type   string   `json:"type"`
        Values []*Value `json:"values"`
    }
```



其中包含服务名、版本、元数据（字典类型）、端点（切片类型）、节点（切片类型）等字段。第二个参数是包含上下文信息的注册选项。



`Deregister` 方法是 `Register` 方法的反操作，需要传入一个 `Service` 指针，用于从注册中心删除一个服务节点。与 `Register` 方法类似，不同的注册中心都有各自的实现版本。



`GetService` 方法接收一个服务名作为参数，用于返回与给定服务名匹配的所有服务节点，并以切片形式返回对应的所有 Service 指针。当客户端通过服务名进行服务发现时会调用该方法。



顾名思义，`ListServices` 方法用于返回指定注册中心上提供的所有服务节点信息。



再来看 `Watch` 方法，该方法用于客户端监听指定服务名对应的服务节点状态，如果有节点变更（新增或删除），则注册中心会告知客户端，从而避免调用的服务节点不存在，同时可以及时感知到新增的服务节点。这种监听机制通常是通过协程以非阻塞方式实现的，具体可以参考 `consul.go` 或 `mdns_registry.go` 中的是实现。



最后是 `String` 方法，该方法用于获取当前系统默认的注册中心名称，这个方法比较简单，不同的注册中心直接返回对应的字符串格式名称即可。



Go Micro 默认支持的注册中心包括 `mdns`、`consul`、`gossip` 和 `memory`，这一点我们通过简单分析 Registry 组件源码目录和源文件即可得知，而我们在服务注册和发现时介绍过，注册中心的设置是在运行 `service.Init` 方法时完成的，该方法会解析命令行参数，如果命令行参数中包含 `--registry` 选项指定注册中心或者环境变量包含 `MICRO_REGISTRY`，则 Go Micro 会使用这两个配置项（配置任意一个即可）来设置注册中心，否则会使用 `mdns` 作为默认的注册中心（这些解析逻辑位于 `src/github.com/micro/go-micro/cmd/cmd.go` 中）。



除此之外，`registry` 组件目录下还包含了一个 `cache` 子目录，用于为注册中心提供一个缓存层，下一篇我们介绍 Selector 组件的时候会提到。



除了系统自带的 `mdns`、`consul`、`gossip`、`memory` 之外，Go Micro 还支持以插件方式提供对其它注册中心的支持，比如 `etcd`、`etcdv3`、`eureka`、`kubernetes`、`multi`、`nats`、`proxy`、`zookeeper` 等，这些组件可以在 Go-Plugins 仓库中找到：https://github.com/micro/go-plugins/tree/master/registry，要使用这些插件，需要先通过 `go get` 命令将其下载到本地，然后通过设置 `MICRO_REGISTRY` 或 `--registry` 选项来设置使用新的注册中心。



后面几篇分享，学院君将给大家介绍下 Consul、Etcd、Zookeeper 这三个常见的注册中心，并结合底层源码演示它们在 Go Micro 中的基本使用。