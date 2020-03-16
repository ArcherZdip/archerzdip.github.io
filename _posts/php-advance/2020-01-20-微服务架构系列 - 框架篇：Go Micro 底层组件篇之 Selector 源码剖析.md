---
title: 微服务架构系列 - 框架篇：Go Micro 底层组件篇之 Selector 源码剖析
layout: post
category: blog
tags: |-
  PHP
  微服务架构系列
  框架篇
  Go Micro
  Selector
---

# 微服务架构系列（ 二十九）



前面我们花了很多篇幅介绍常见的注册中心以及如何将它们集成到 Go Micro 框架中，接下来，我们继续探索 Go Micro 的底层组件 —— Selector。关于 Selector 的底层源码执行逻辑在介绍 Registry 的时候已经提到过：[服务节点查询和请求处理的底层实现剖析]，因为 Registry 依赖 Selector 对服务节点查询结果进行缓存、过滤、监听和负载均衡，下面我们来整体回顾和梳理下。



Selector 组件主要被 Go Micro 设计用来做客户端服务发现的负载均衡，当客户端调用服务端方法时，会根据 Selector 组件中定义的负载均衡策略来选择通过 Registry 注册的服务节点列表中的其中一个，默认使用的是随机算法，即从返回的服务节点中随机选择一个。下面我们来看下 Selector 组件的实现源码，首先看下 `Selector` 接口，对应的源码位于 `src/github.com/micro/go-micro/client/selector/selector.go`：



```go
    // Selector builds on the registry as a mechanism to pick nodes
    // and mark their status. This allows host pools and other things
    // to be built using various algorithms.
    type Selector interface {
        Init(opts ...Option) error
        Options() Options
        // Select returns a function which should return the next node
        Select(service string, opts ...SelectOption) (Next, error)
        // Mark sets the success/error against a node
        Mark(service string, node *registry.Node, err error)
        // Reset returns state back to zero for a service
        Reset(service string)
        // Close renders the selector unusable
        Close() error
        // Name of the selector
        String() string
    }
    
    // Next is a function that returns the next node
    // based on the selector's strategy
    type Next func() (*registry.Node, error)
    
    // Filter is used to filter a service during the selection process
    type Filter func([]*registry.Service) []*registry.Service
    
    // Strategy is a selection strategy e.g random, round robin
    type Strategy func([]*registry.Service) Next
    
    var (
        DefaultSelector = NewSelector()
    
        ErrNotFound      = errors.New("not found")
        ErrNoneAvailable = errors.New("none available")
    )
```

  

该接口声明的最重要的方法就是 `Select` 方法，用于返回一个函数，执行该函数即可返回指定的服务节点。在接口之外还定义了三个函数类型，`Next` 用于通过指定负载均衡策略返回某个服务节点，`Filter` 用于定义过滤器逻辑，`Strategy` 用于定义服务节点获取策略，比如随机、轮询等。最后还声明了几个全局变量，`DefaultSelector` 即默认 Selector 实例，该实例通过调用 `selector` 包中的 `NewSelector` 方法实现，对应的类实现了 Selector 接口，后面两个是节点不存在或无效时对应的默认错误消息。



`NewSelector` 方法定义在 `src/github.com/micro/go-micro/client/selector/default.go` 中：



```go
    func NewSelector(opts ...Option) Selector {
        sopts := Options{
            Strategy: Random,
        }
    
        for _, opt := range opts {
            opt(&sopts)
        }
    
        if sopts.Registry == nil {
            sopts.Registry = registry.DefaultRegistry
        }
    
        s := &registrySelector{
            so: sopts,
        }
        s.rc = s.newCache()
    
        return s
    }
```

  

可以看作是实现了 Selector 接口的 `registrySelector` 类的构造函数，`registrySelector` 的类属性和成员方法都定义在 `default.go` 文件中：



```go
    type registrySelector struct {
        so Options
        rc cache.Cache
    }
    
    func (c *registrySelector) newCache() cache.Cache {
        ropts := []cache.Option{}
        if c.so.Context != nil {
            if t, ok := c.so.Context.Value("selector_ttl").(time.Duration); ok {
                ropts = append(ropts, cache.WithTTL(t))
            }
        }
        return cache.New(c.so.Registry, ropts...)
    }
    
    func (c *registrySelector) Init(opts ...Option) error {
        for _, o := range opts {
            o(&c.so)
        }
    
        c.rc.Stop()
        c.rc = c.newCache()
    
        return nil
    }
    
    func (c *registrySelector) Options() Options {
        return c.so
    }
    
    func (c *registrySelector) Select(service string, opts ...SelectOption) (Next, error) {
        sopts := SelectOptions{
            Strategy: c.so.Strategy,
        }
    
        for _, opt := range opts {
            opt(&sopts)
        }
    
        // get the service
        // try the cache first
        // if that fails go directly to the registry
        services, err := c.rc.GetService(service)
        if err != nil {
            return nil, err
        }
    
        // apply the filters
        for _, filter := range sopts.Filters {
            services = filter(services)
        }
    
        // if there's nothing left, return
        if len(services) == 0 {
            return nil, ErrNoneAvailable
        }
    
        return sopts.Strategy(services), nil
    }
    
    func (c *registrySelector) Mark(service string, node *registry.Node, err error) {
    }
    
    func (c *registrySelector) Reset(service string) {
    }
    
    // Close stops the watcher and destroys the cache
    func (c *registrySelector) Close() error {
        c.rc.Stop()
    
        return nil
    }
    
    func (c *registrySelector) String() string {
        return "registry"
    }
```



顾名思义，`registrySelector` 是依赖于 Registry 组件的 Selector 实现类，其中的 `so` 对应的是 `Options` 类，包含了该 Selector 对应的 Registry（注册中心）和 Strategy（负载均衡策略，即上面声明的 Strategy 函数类型），`rc` 对应的是 Cache 层，用于对服务节点查询结果进行缓存以提高性能。`registrySelector` 将这两个组件类组合进来，相当于继承了它们的属性和方法。然后在 `NewSelector` 构造函数中，对 so 属性的 Registry、Strategy 字段以及 rc 属性都做了初始化，默认的 Registry 即我们前面介绍的，如果是 Consul，则对应的是 `consulRegistry`，依次类推；Strategy 默认对应的是定义在 `selector` 包中的 `Random` 函数；rc 属性初始值则通过 `registrySelector` 的 `newCache` 方法返回，对应值是 cache.Cache 接口指向的 cache 类实例，这个类定义在 `src/github.com/micro/go-micro/registry/cache/rcache.go` 中，其中也包含了默认的 Registry 实例，这个实例和 so 属性中的 Registry 实例一致。



`registrySelector` 实例初始化完成后，接下来，我们重点关注下 `Select` 方法的实现，该方法会调用 `registrySelector` 属性 `rc` 对应实例上的 `GetService` 方法通过服务名称 `service` 获取服务节点数组，`GetService` 方法最终调用的是上述 cache 类中的 `get` 方法，关于其底层的查询和健康检查实现我们在前面介绍 [Consul 服务发现底层实现](https://articles.zsxq.com/id_r1j6mxa8vvs6.html)时已经详细介绍过，这里就不展开了。



然后调用过滤器对服务节点数组进行过滤，这里我们默认没有定义过滤器函数（实际开发时可以支持过滤指定 IP、端点、服务名、版本等远程服务节点，默认支持的过滤器定义在 `src/github.com/micro/go-micro/client/selector/filter.go` 中），所以会跳过这个 for 循环体，最后再将经过过滤的服务节点传入 Strategy 对应函数 `Random`，该函数会返回一个通过负载均衡算法获取有效服务节点的匿名函数：



```go
    // Random is a random strategy algorithm for node selection
    func Random(services []*registry.Service) Next {
        var nodes []*registry.Node
    
        for _, service := range services {
            nodes = append(nodes, service.Nodes...)
        }
    
        return func() (*registry.Node, error) {
            if len(nodes) == 0 {
                return nil, ErrNoneAvailable
            }
    
            i := rand.Int() % len(nodes)
            return nodes[i], nil
        }
    }
```

  

关于 Selector 组件里面的底层实现逻辑就是这样，并不复杂，我们再回到上一层调用 Selector 组件进行服务发现的地方，这段代码位于默认的客户端组件 `rpcClient` 中，当我们在客户端发起远程服务调用时，最终会执行到 `rpcClient` 的 `Call` 方法（源码位于 `src/github.com/micro/go-micro/client/rpc_client.go`），在这个方法中，通过执行 `rpcClient` 的 `next` 方法返回服务节点获取函数：



```go
    func (r *rpcClient) next(request Request, opts CallOptions) (selector.Next, error) {
        ...
        next, err := r.opts.Selector.Select(service, opts.SelectOptions...)
        ...
    }
```

  

其中 `r.opt.Selector` 对应的就是 `registrySelector` 类对应的实例，Select 方法执行的自然也是该类中的 Select 方法，所以 `next` 也是一个 `selector.Next` 类型的函数，在 `Call` 方法中最终执行 `next()` 时才返回对应服务节点：



```go
    ...
    
    // select next node
    node, err := next()
    if err != nil && err == selector.ErrNotFound {
        return errors.NotFound("go.micro.client", "service %s: %v", request.Service(), err.Error())
    } else if err != nil {
        return errors.InternalServerError("go.micro.client", "error getting next %s node: %v", request.Service(), err.Error())
    }


    // make the call
    err = rcall(ctx, node, request, response, callOpts)
    r.opts.Selector.Mark(request.Service(), node, err)
    
    ...
```



如果服务节点存在则调用 `rcall` 方法（即 `rpcClient` 的 `call` 方法）与该服务节点建立连接并调用远程服务方法，通过指定的处理器处理后返回响应给客户端。



调用成功后，还会调用 `registrySelector` 类的 `Mark` 方法进行标记，不过这里是空方法，所以什么也不做。以上就是 Selector 组件及其上下文环境执行的所有业务逻辑。