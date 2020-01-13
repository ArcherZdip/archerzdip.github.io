---
title: 微服务架构系列 - 注册中心篇：基于 Zookeeper 进行服务注册和发现的底层实现
layout: post
category: blog
tags: |-
  PHP
  微服务架构系列
  Go Micro
  注册中心篇
  Zookeeper
---

# 微服务架构系列（二十八）

介绍了基于 Zookeeper 集群作为 Go Micro 框架的注册中心进行服务发现，这篇分享我想结合 Go Micro 源码给大家分析下底层是如何实现的。



**服务注册**



由于 Go Micro 框架原生不支持 Zookeeper，所以和 Etcd 一样，Zookeeper 也是通过 Go Plugins 的形式引入的，对应的插件源码位于项目的 `src/github.com/micro/go-plugins/registry/zookeeper` 目录下，由于和 Etcd、Consul 一样都实现自同一个接口 `Registry`，所以它们的基本代码结构是一样的，引入方式也和 Etcd 插件一样，在服务端和客户端代码中引入 `zookeeper` 时，会通过初始化函数 `init` 将其注册到默认的注册中心数组：



```
    func init() {
        cmd.DefaultRegistries["zookeeper"] = NewRegistry
    }
```



然后在启动服务端或客户端时，根据系统变量或者命令行参数获取指定的默认注册中心设置，再到这个 `cmd.DefaultRegistries` 数组获取对应的注册中心实例，比如我们之前运行启动命令的时候带上了 `--registry=zookeeper` 选项，所以 Go Micro 在运行时会以 `zookeeper` 作为注册中心。



> 注：在调用 `zookeeper` 插件初始化方法 `NewRegistry` 进行初始化的时候，系统默认 Zookeeper 服务器 IP 和端口是 `127.0.0.1:2181`（支持在服务端/客户端启动代码中手动指定），默认根节点是 `/micro-registry`。



其他的初始化逻辑和 Consul、Etcd 一样，我们直奔主题，看下 `zookeeper` 的服务注册方法 `Register` 实现（对应源码位于 `src/github.com/micro/go-plugins/registry/zookeeper/zookeeper.go`）：



```go
    func (z *zookeeperRegistry) Register(s *registry.Service, opts ...registry.RegisterOption) error {
        if len(s.Nodes) == 0 {
            return errors.New("Require at least one node")
        }
    
        var options registry.RegisterOptions
        for _, o := range opts {
            o(&options)
        }
    
        // create hash of service; uint64
        h, err := hash.Hash(s, nil)
        if err != nil {
            return err
        }
    
        // get existing hash
        z.Lock()
        v, ok := z.register[s.Name]
        z.Unlock()
    
        // the service is unchanged, skip registering
        if ok && v == h {
            return nil
        }
    
        service := &registry.Service{
            Name:      s.Name,
            Version:   s.Version,
            Metadata:  s.Metadata,
            Endpoints: s.Endpoints,
        }
    
        for _, node := range s.Nodes {
            service.Nodes = []*registry.Node{node}
            exists, _, err := z.client.Exists(nodePath(service.Name, node.Id))
            if err != nil {
                return err
            }
    
            srv, err := encode(service)
            if err != nil {
                return err
            }
    
            if exists {
                _, err := z.client.Set(nodePath(service.Name, node.Id), srv, -1)
                if err != nil {
                    return err
                }
            } else {
                err := createPath(nodePath(service.Name, node.Id), srv, z.client)
                if err != nil {
                    return err
                }
            }
        }
    
        // save our hash of the service
        z.Lock()
        z.register[s.Name] = h
        z.Unlock()
    
        return nil
    }
```

  

在注册服务时，首先会查询 `z.register` 字典看是否已经注册（Go Micro 框架运行时级别），这个查询是同步阻塞的，如果已经注册过，则跳过后续操作，否则会依次将每个服务节点注册到 Zookeeper 集群。在这个 `for` 循环体中，依然会调用 `z.client.Exists` 方法判断节点是否已经注册（Zookeeper 级别），如果节点已经存在则调用 `z.client.Set` 更新该节点，否则调用 `z.client.Create` 方法创建新节点（对应逻辑位于 `createPath` 方法中）。



这里的 `z.client` 实例在 `zookeeper` 插件初始化的时候建立与 Zookeeper 集群连接成功时返回，该实例对应的类是 `src/github.com/samuel/go-zookeeper/zk/conn.go` 中的 `Conn` 类，前面我们提到过 Zookeeper 本身基于 Java 开发，如果要在 Go 程序中与其通信，需要借助针对 Go 语言的客户端 SDK，[samuel/go-zookeeper](https://github.com/samuel/go-zookeeper) 扩展包就是这样的一个 SDK，我们在这里就是基于该扩展包提供的 API 方法建立 Go Micro `zookeeper` 插件与 Zookeeper 集群之间的纽带进行通信的。前面提到的服务注册方法 `Register` 中 `z.client` 实例上的 `Exists`、`Set`、`Create` 方法都定义在 Conn 类中，感兴趣的同学可以去看下。



注册/更新完所有服务节点后，会再次以同步阻塞的方式将服务信息存储到 `z.register` 字典中，以避免重复注册带来的额外系统开销。



**服务删除**



服务删除对应的方法是 `zookeeper` 插件类中的 `Deregister` 方法：



```go
    func (z *zookeeperRegistry) Deregister(s *registry.Service) error {
        if len(s.Nodes) == 0 {
            return errors.New("Require at least one node")
        }
    
        // delete our hash of the service
        z.Lock()
        delete(z.register, s.Name)
        z.Unlock()
    
        for _, node := range s.Nodes {
            err := z.client.Delete(nodePath(s.Name, node.Id), -1)
            if err != nil {
                return err
            }
        }
    
        return nil
    }
```

  

删除之前会先从 `z.register` 字典中去掉对应服务，然后通过一个循环体一次将所有服务节点从 Zookeeper 集群中删除，调用 `z.client` 的 `Delete` 方法。



**服务发现**



最后我们来看下服务发现的实现，和 Consul、Etcd 共用了 Selector 层，所以底层的逻辑是一样的，当我们在客户端请求微服务接口时，首先会通过封装了 Registry 组件的 Selector 层查询服务节点，第一次查询的时候会通过 Registry 组件查询服务节点，这里会走到 `zookeeper` 插件的 `GetService` 方法：



```go
    func (z *zookeeperRegistry) GetService(name string) ([]*registry.Service, error) {
        l, _, err := z.client.Children(servicePath(name))
        if err != nil {
            return nil, err
        }
    
        serviceMap := make(map[string]*registry.Service)
    
        for _, n := range l {
            _, stat, err := z.client.Children(nodePath(name, n))
            if err != nil {
                return nil, err
            }
    
            if stat.NumChildren > 0 {
                continue
            }
    
            b, _, err := z.client.Get(nodePath(name, n))
            if err != nil {
                return nil, err
            }
    
            sn, err := decode(b)
            if err != nil {
                return nil, err
            }
    
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



这里会调用 `ServicePath` 方法根据服务名称获取 Zookeeper 对应的节点路径，然后再将这个节点路径传入 `z.client.Children` 方法去 Zookeeper 查询节点路径是否存在，如果存在则返回叶子节点信息（代表节点 ID 的字符串数组），然后遍历叶子节点，依次组合出服务节点的完整路径（由 `nodePath` 方法实现），再根据完整路径调用 `z.client.Get` 方法从 Zookeeper 中查询对应的节点数据，如果节点数据存在，则对其解码后按照版本作为键将经过处理的对应服务节点信息保存到 `serviceMap` 字典返回，然后在 Selector 层对返回的服务数据进行缓存，以便下次查询的时候直接从缓存获取数据提高查询性能，此外还会通过 `watcher` 监听指定服务，一旦有服务节点新增或删除及及时更新缓存中的数据，从而让客户端及时获悉服务节点的存活状态，始终访问的是有效的服务节点，最后 Selector 层通过一定的负载均衡算法从多个服务节点中选择一个，客户端与这个服务节点建立连接，最后根据服务名对应的处理器进行处理后将结果返回给客户端，完成服务发现与调用。



> 注：以上 Selector 层处理过程更多细节和源码实现请参考 [Consul 服务发现源码剖析](https://articles.zsxq.com/id_r1j6mxa8vvs6.html)这篇分享，不同的注册中心组件共用了一套负载均衡、缓存和监听机制，Go Micro 框架通过这种分层处理有效提高了代码的复用性和系统的扩展性。



关于注册中心篇我们先简单介绍到这里，在基于 Go Micro 的微服务系统中，优先考虑基于 Go 语言实现的 Consul 和 Etcd，如果你只是实现微服务架构，并且系统不是很大，一般 Etcd 就够用了。