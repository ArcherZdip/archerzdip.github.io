---
title: 微服务架构系列 - 框架篇：Go Micro 底层组件篇之 Broker 源码剖析
layout: post
category: blog
tags: |-
  PHP
  微服务架构系列
  Go Micro
  Broker
---

# 微服务架构系列（三十四）



**Broker接口**



今天这篇分享我们来看看 Go Micro 框架中 Broker 组件的底层实现。和其他组件一样，Go Micro 也是通过抽象接口的方式提供了 `Broker` 接口：



```
    type Broker interface {
        Init(...Option) error
        Options() Options
        Address() string
        Connect() error
        Disconnect() error
        Publish(topic string, m *Message, opts ...PublishOption) error
        Subscribe(topic string, h Handler, opts ...SubscribeOption) (Subscriber, error)
        String() string
    }
```

  

从而方便开发者按照自己的业务需求去实现和扩展 Broker 接口，系统默认的 Broker 组件实现类是点对点的 HTTP 系统，以便最小化对其他工具的依赖，此外还开箱支持 NATS，你还可以通过 Go Plugins 引入其它消息系统插件，比如 Redis、gRPC、RabbitMQ、SQS、Kafka、Google Pub/Sub 等：



![img](/assets/post/Fo-gDQds0naDhFijA6kmpOINKH69.png)



**Broker 默认实现类初始化**



我们以默认的 HTTP 系统为例介绍其底层实现，打开 `user/main.go`，在通过 `micro.NewService` 创建新的服务时，就包含了对 Broker 组件的初始化，这个初始化动作在 `micro.newService` 方法中完成（源码位于 `src/github.com/micro/go-micro/service.go`）：



```go
    func newService(opts ...Option) Service {
        options := newOptions(opts...)
    
        options.Client = &clientWrapper{
            options.Client,
            metadata.Metadata{
                HeaderPrefix + "From-Service": options.Server.Options().Name,
            },
        }
    
        return &service{
            opts: options,
        }
    }
```

我们点开 `newOptions` 方法，就可以看到 Broker 组件和 Cmd、Client、Server、Registry、Transport 等其他组件一起被初始化：



```go
    func newOptions(opts ...Option) Options {
        opt := Options{
            Broker:    broker.DefaultBroker,
            Cmd:       cmd.DefaultCmd,
            Client:    client.DefaultClient,
            Server:    server.DefaultServer,
            Registry:  registry.DefaultRegistry,
            Transport: transport.DefaultTransport,
            Context:   context.Background(),
        }
    
        for _, o := range opts {
            o(&opt)
        }
    
        return opt
    }
```

  

被通过 `Options` 类封装起来设置到 Service 的 `opts` 属性上，以便后续调用。这里的 `broker.DefaultBroker` 即 `src/github.com/micro/go-micro/broker/broker.go` 中通过如下这行代码返回的 `httpBroker` 实例：



```
    var (
        DefaultBroker Broker = newHttpBroker()
    )
```

  

而 `httpBroker` 就是实现了 Broker 接口的 HTTP Broker 实现类，具体的初始化逻辑感兴趣的可以自行去查看，这就是默认的 Broker 组件初始化逻辑。



接下来，回到 `user/main.go`，接下来执行 `srv.Init()` 方法，该方法源码仍然位于 `src/github.com/micro/go-micro/service.go` 中：



```go
    func (s *service) Init(opts ...Option) {
        // process options
        for _, o := range opts {
            o(&s.opts)
        }
    
        s.once.Do(func() {
            // Initialise the command flags, overriding new service
            _ = s.opts.Cmd.Init(
                cmd.Broker(&s.opts.Broker),
                cmd.Registry(&s.opts.Registry),
                cmd.Transport(&s.opts.Transport),
                cmd.Client(&s.opts.Client),
                cmd.Server(&s.opts.Server),
            )
        })
    }
```

  

可以看到在这里，Broker 组件会和 Registry、Transport、Client、Server 一起从系统环境变量/命令行参数读取配置来覆盖默认的 Broker 实现实例，相应的实现逻辑和之前介绍的 Registry 和 Transport 一样，只不过对应的系统环境变量名是 `MICRO_BROKER`，参数选项名是 `--broker`，关于其具体实现可以在 `src/github.com/micro/go-micro/config/cmd/cmd.go` 中查看：



```
    cli.StringFlag{
            Name:   "broker",
            EnvVar: "MICRO_BROKER",
            Usage:  "Broker for pub/sub. http, nats, rabbitmq",
      },
```



比如我们在系统环境变量中设置 `MICRO_BROKER=nats`，或者在启动 UserService 的时候执行的命令是 `go run main.go --broker=nats`，则对应的 Broker 实例则变成 `nats.NewBroker` 返回的 `natsBroker` 实例并覆盖默认的 `httpBroker` 实现，当然要使用 NATS 作为 Broker 消息系统还要安装并运行 [NATS](https://nats.io/) 软件。



**发布事件底层实现**



接下来，我们就可以在服务中通过 `srv.Server().Options().Broker` 获取系统的 Broker 组件实例了，该实例以 `srv.Init()` 运行之后为准。然后我们再把它注册到服务处理处理器上：



```
    proto.RegisterUserServiceHandler(srv.Server(), &service{repo, pubsub})
```

  

以便我们在服务端口中通过该实例来发布或者订阅指定主题消息：



```
    srv.PubSub.Publish(topic, msg);
```

  

其中 `topic` 是字符串类型的消息主题，`msg` 是 `broker.Message` 类型的经过编码的消息头和消息主体，通常，我们以 JSON 格式对消息进行编码。



以 `httpBroker` 类为例，对应的 `Publish` 方法实现源码如下：



```go
    func (h *httpBroker) Publish(topic string, msg *Message, opts ...PublishOption) error {
        // create the message first
        m := &Message{
            Header: make(map[string]string),
            Body:   msg.Body,
        }
    
        for k, v := range msg.Header {
            m.Header[k] = v
        }
    
        m.Header[":topic"] = topic
    
        // encode the message
        b, err := h.opts.Codec.Marshal(m)
        if err != nil {
            return err
        }
    
        // save the message
        h.saveMessage(topic, b)
    
        // now attempt to get the service
        h.RLock()
        s, err := h.r.GetService("topic:" + topic)
        if err != nil {
            h.RUnlock()
            // ignore error
            return nil
        }
        h.RUnlock()
    
        pub := func(node *registry.Node, t string, b []byte) error {
            scheme := "http"
    
            // check if secure is added in metadata
            if node.Metadata["secure"] == "true" {
                scheme = "https"
            }
    
            vals := url.Values{}
            vals.Add("id", node.Id)
    
            uri := fmt.Sprintf("%s://%s%s?%s", scheme, node.Address, DefaultSubPath, vals.Encode())
            r, err := h.c.Post(uri, "application/json", bytes.NewReader(b))
            if err != nil {
                return err
            }
    
            // discard response body
            io.Copy(ioutil.Discard, r.Body)
            r.Body.Close()
            return nil
        }
    
        srv := func(s []*registry.Service, b []byte) {
            for _, service := range s {
                // only process if we have nodes
                if len(service.Nodes) == 0 {
                    continue
                }
    
                switch service.Version {
                // broadcast version means broadcast to all nodes
                case broadcastVersion:
                    var success bool
    
                    // publish to all nodes
                    for _, node := range service.Nodes {
                        // publish async
                        if err := pub(node, topic, b); err == nil {
                            success = true
                        }
                    }
    
                    // save if it failed to publish at least once
                    if !success {
                        h.saveMessage(topic, b)
                    }
                default:
                    // select node to publish to
                    node := service.Nodes[rand.Int()%len(service.Nodes)]
    
                    // publish async to one node
                    if err := pub(node, topic, b); err != nil {
                        // if failed save it
                        h.saveMessage(topic, b)
                    }
                }
            }
        }
    
        // do the rest async
        go func() {
            // get a third of the backlog
            messages := h.getMessage(topic, 8)
            delay := (len(messages) > 1)
    
            // publish all the messages
            for _, msg := range messages {
                // serialize here
                srv(s, msg)
    
                // sending a backlog of messages
                if delay {
                    time.Sleep(time.Millisecond * 100)
                }
            }
        }()
    
        return nil
    }
```

在此之前，我们在 `user/main.go` 中通过 `srv.run()` 启动服务过程中，会启动 Transport 和 Broker 监听请求，对应代码位于 `rpcServer.Start()` 方法中：



```go
    func (s *rpcServer) Start() error {
        ...
        ts, err := config.Transport.Listen(config.Address)
        ...
        log.Logf("Transport [%s] Listening on %s", config.Transport.String(), ts.Addr())
        
        // connect to the broker
        if err := config.Broker.Connect(); err != nil {
            return err
        }
        bname := config.Broker.String()
        log.Logf("Broker [%s] Connected to %s", bname, config.Broker.Address())
        ...
    }
```

​    

对应的 `Connect()` 方法实现位于 httpBroker 中，主要是启动 HTTP 服务并初始化 `httpBroker.r` 属性，即通过缓存封装的默认 Registry 组件实现实例，以便后续查询指定 `topic` 对应服务。



我们回到 `Publish()` 方法，在该方法中首先对消息进行编码并保存到 httpBroker 的 inbox 中，inbox 是一个字典结构，以 topic 作为键，以消息数组作为值，新增的消息都会追加到这个数组上。



然后通过 `h.r.GetService("topic:" + topic)` 从  Registry 获取部署 topic 服务的节点信息，我们前面提到 `h.r` 是通过 `cache.New` 返回的封装了默认 Registry 实现的 `cache` 类实例，这里的 `h.r` 类似我们前介绍 Selector 组件时提到的 `registrySelector.rc` 属性，对应的 `GetService` 方法定义在 `src/github.com/micro/go-micro/registry/cache/rcache.go` 中，关于其具体实现可以参考 [Selector 组件中的介绍](https://articles.zsxq.com/id_r1j6mxa8vvs6.html)，这里不再赘述了。



对于 httpBroker 而言，`"topic:" + topic` 对应服务节点是在运行 `httpBroker.httpBroker` 时注册的，所以在没有运行 EmailService 服务时，这里返回的 `s` 值为空并且 `err` 值不为空，所以直接退出了，但是在 cache 层 `GetService` 实现中会监听这个 key 对应的服务。



假设我们启动了订阅该 topic 的服务，则会注册对应服务，此时，再运行 `Publish()` 方法时，会运行到末尾的协程代码，从 httpBroker 的 inbox 中读取所有消息，然后调用 `srv` 方法将每条消息推送到服务节点去处理。所以，如果发布事件方未主动再次触发 Publish 方法，则堆积在 inbox 中的消息不会被订阅方拉取消费。



**订阅事件底层实现**



接下来，我们来到 `mail/main.go` 文件，看看订阅方的处理逻辑底层实现。`srv.Init()` 及之前的 Broker 组件实例初始化逻辑和 `user` 完全一致， 这里也是 httpBroker，下面我们重点来看一下这段订阅代码：



```go
    pubSub := srv.Server().Options().Broker
    if err := pubSub.Connect(); err != nil {
        fmt.Errorf("broker connect error: %v\n", err)
    }


    // 订阅消息
    _, err := pubSub.Subscribe(topic, func(pub broker.Event) error {
        var user *userProto.User
        if err := json.Unmarshal(pub.Message().Body, &user); err != nil {
            fmt.Errorf("process message failed: %v\n", err)
            return err
        }
        fmt.Printf("[User Registered]: %v\n", user)
        go sendEmail(user)
        return nil
    })
```

  

首先我们从 `srv` 中获取默认的 Broker 实现实例，并调用其 `Connect()` 方法启动 HTTP 消息系统服务并初始化 `httpBroker.r`，注意这里的 `httpBroker` 实例不同于上一个 `user` 服务中的 `httpBroker` 实例，是一个新的实例，接下来我们调用这新实例的 `Subscribe()` 方法，其底层源码如下所示：



```django
    func (h *httpBroker) Subscribe(topic string, handler Handler, opts ...SubscribeOption) (Subscriber, error) {
        var err error
        var host, port string
        options := NewSubscribeOptions(opts...)
    
        // parse address for host, port
        host, port, err = net.SplitHostPort(h.Address())
        if err != nil {
            return nil, err
        }
    
        addr, err := maddr.Extract(host)
        if err != nil {
            return nil, err
        }
    
        // create unique id
        id := h.id + "." + uuid.New().String()
    
        var secure bool
    
        if h.opts.Secure || h.opts.TLSConfig != nil {
            secure = true
        }
    
        // register service
        node := &registry.Node{
            Id:      id,
            Address: mnet.HostPort(addr, port),
            Metadata: map[string]string{
                "secure": fmt.Sprintf("%t", secure),
            },
        }
    
        // check for queue group or broadcast queue
        version := options.Queue
        if len(version) == 0 {
            version = broadcastVersion
        }
    
        service := &registry.Service{
            Name:    "topic:" + topic,
            Version: version,
            Nodes:   []*registry.Node{node},
        }
    
        // generate subscriber
        subscriber := &httpSubscriber{
            opts:  options,
            hb:    h,
            id:    id,
            topic: topic,
            fn:    handler,
            svc:   service,
        }
    
        // subscribe now
        if err := h.subscribe(subscriber); err != nil {
            return nil, err
        }
    
        // return the subscriber
        return subscriber, nil
    }
```



首先，我们将 `email` 启动的 HTTP 消息系统服务节点信息封装后设置到 `httpSubscriber` 实例中，该实例还包含了订阅主题、传入 Subscribe 方法的事件处理器、当前的 httpBroker 实例等信息，然后将其作为参数传递到当前 httpBroker 实例的 `subscribe` 方法：



```go
    func (h *httpBroker) subscribe(s *httpSubscriber) error {
        h.Lock()
        defer h.Unlock()
    
        if err := h.r.Register(s.svc, registry.RegisterTTL(registerTTL)); err != nil {
            return err
        }
    
        h.subscribers[s.topic] = append(h.subscribers[s.topic], s)
        return nil
    }
```

  

该方法会调用 `h.r.Register` 注册 `"topic:" + topic` 对应的 HTTP 消息系统服务节点信息，并将传入的 `httpSubscriber` 实例追加到 `h.subscribers[s.topic]` 数组中。



当 `user` 服务中指定 topic 对应事件 `user.registered` 发布，会通过 httpBroker 的 `Publish` 方法调用其中的匿名函数 `pub` 将消息推送到订阅该 topic 的 `email` 服务中来：



```go
    uri := fmt.Sprintf("%s://%s%s?%s", scheme, node.Address, DefaultSubPath, vals.Encode())
    r, err := h.c.Post(uri, "application/json", bytes.NewReader(b))
```

  

其中的 `node.Address` 即通过 Registry 获取到的 `email` 服务 HTTP 消息系统 IP + 端口号，然后在 email 服务中，通过 httpBroker 实现的 `ServeHTTP()` 方法处理请求（`httpBroker` 同时也实现了 `http.Handler` 接口）：



```go
    func (h *httpBroker) ServeHTTP(w http.ResponseWriter, req *http.Request) {
        if req.Method != "POST" {
            err := merr.BadRequest("go.micro.broker", "Method not allowed")
            http.Error(w, err.Error(), http.StatusMethodNotAllowed)
            return
        }
        defer req.Body.Close()
    
        req.ParseForm()
    
        b, err := ioutil.ReadAll(req.Body)
        if err != nil {
            errr := merr.InternalServerError("go.micro.broker", "Error reading request body: %v", err)
            w.WriteHeader(500)
            w.Write([]byte(errr.Error()))
            return
        }
    
        var m *Message
        if err = h.opts.Codec.Unmarshal(b, &m); err != nil {
            errr := merr.InternalServerError("go.micro.broker", "Error parsing request body: %v", err)
            w.WriteHeader(500)
            w.Write([]byte(errr.Error()))
            return
        }
    
        topic := m.Header[":topic"]
        delete(m.Header, ":topic")
    
        if len(topic) == 0 {
            errr := merr.InternalServerError("go.micro.broker", "Topic not found")
            w.WriteHeader(500)
            w.Write([]byte(errr.Error()))
            return
        }
    
        p := &httpEvent{m: m, t: topic}
        id := req.Form.Get("id")
    
        h.RLock()
        for _, subscriber := range h.subscribers[topic] {
            if id == subscriber.id {
                // sub is sync; crufty rate limiting
                // so we don't hose the cpu
                subscriber.fn(p)
            }
        }
        h.RUnlock()
    }
```

  

该方法只接受 POST 请求，对于读取到的异步消息会通过 httpBroker 实现的解码器进行解码后，从 `h.subscribers[topic]` 中获取所有的 `httpSubscriber` 实例，并调用其中的 fn 属性指向的处理器方法对消息进行处理，这里的处理器就是我们在调用 `pubSub.Subscribe` 时传入的第二个匿名函数，这里的 topic 就是第一个参数 `user.registered`。



以上就是 Broker 组件默认实现类 httpBroker 底层的实现逻辑，你可以参考此流程分析下集成其他消息系统的 Broker 组件实现类，比如 NATS，我这里就不一一介绍了。