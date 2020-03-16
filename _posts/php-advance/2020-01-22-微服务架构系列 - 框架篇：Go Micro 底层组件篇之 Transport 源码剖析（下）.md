---
title: 微服务架构系列 - 框架篇：Go Micro 底层组件篇之 Transport 源码剖析（下）
layout: post
category: blog
tags: |-
  PHP
  微服务架构系列
  框架篇
  Go Micro
  Transport
---

# 微服务架构系列（三十一）

介绍了基于 Go Micro 的微服务服务端启动时 Transport 组件底层的功能，主要是启动底层的 HTTP 服务器监听并处理客户端请求，请求的具体处理逻辑还是在 Server 层完成，今天我们来看看客户端发起请求时 Transport 组件发挥了怎样的作用。



打开 `src/hello/client.go`，在 `main` 函数中，跳过新建服务和服务初始化的代码，接下来是调用官方 SDK 创建客户端服务的代码：



```
    greeter := proto.NewGreeterService("go.micro.srv.greeter", service.Client())
```

  

`greeter` 对应的是定义在 `hello.micro.go` 中的 `greeterService` 实例，其客户端属性 `c` 默认是 `rpcClient` （对应源码位于 `src/github.com/micro/go-micro/client/rpc_client.go`）实例，`name` 则是这里传入的要调用的服务名称 `go.micro.srv.greeter`。



然后，就是调用 `greeter` 实例上的 `Hello` 方法调用远程服务，并且传递了请求参数 `{Name: "学院君"}`：



```
    greeter.Hello(context.TODO(), &proto.HelloRequest{Name: "学院君"})
```

  

下面我们简单看下 `Hello` 方法的源码：



```go
    func (c *greeterService) Hello(ctx context.Context, in *HelloRequest, opts ...client.CallOption) (*HelloResponse, error) {
        req := c.c.NewRequest(c.name, "Greeter.Hello", in)
        out := new(HelloResponse)
        err := c.c.Call(ctx, req, out, opts...)
        if err != nil {
            return nil, err
        }
        return out, nil
    }
```

  

这里先初始化了远程请求对象 `req`，这里传入了远程服务名称和端点信息 `Greeter.Hello`（对应我们在上篇分享中在服务端注册的路由），以及包含请求参数信息的本地请求实例 `in`，然后实例化了响应实例，接着就是调用默认客户端 `rpcClient` 实例上的 `Call` 方法发起远程服务请求。



接下来的桥段我们前面介绍 Selector 和 Registry 组件的时候都已经分析过，先通过服务名称从 Selector 层获取服务节点，然后建立与服务节点的连接，再通过端点信息和请求参数访问服务节点对应路由，最后把处理结果返回给客户端，下面我们来重点关注下涉及到 Transport 组件的代码部分，这部分代码在获取到服务节点之后，主要逻辑封装在 `rpcClient` 的 `call` 方法中：



```go
    func (r *rpcClient) call(ctx context.Context, node *registry.Node, req Request, resp interface{}, opts CallOptions) error {
        address := node.Address
    
        msg := &transport.Message{
            Header: make(map[string]string),
        }
    
        md, ok := metadata.FromContext(ctx)
        if ok {
            for k, v := range md {
                msg.Header[k] = v
            }
        }
    
        // set timeout in nanoseconds
        msg.Header["Timeout"] = fmt.Sprintf("%d", opts.RequestTimeout)
        // set the content type for the request
        msg.Header["Content-Type"] = req.ContentType()
        // set the accept header
        msg.Header["Accept"] = req.ContentType()
    
        // setup old protocol
        cf := setupProtocol(msg, node)
    
        // no codec specified
        if cf == nil {
            var err error
            cf, err = r.newCodec(req.ContentType())
            if err != nil {
                return errors.InternalServerError("go.micro.client", err.Error())
            }
        }
    
        var grr error
        c, err := r.pool.getConn(address, r.opts.Transport, transport.WithTimeout(opts.DialTimeout))
        if err != nil {
            return errors.InternalServerError("go.micro.client", "connection error: %v", err)
        }
        defer func() {
            // defer execution of release
            r.pool.release(address, c, grr)
        }()
    
        seq := atomic.LoadUint64(&r.seq)
        atomic.AddUint64(&r.seq, 1)
        codec := newRpcCodec(msg, c, cf)
    
        rsp := &rpcResponse{
            socket: c,
            codec:  codec,
        }
    
        stream := &rpcStream{
            context:  ctx,
            request:  req,
            response: rsp,
            codec:    codec,
            closed:   make(chan bool),
            id:       fmt.Sprintf("%v", seq),
        }
        defer stream.Close()
    
        ch := make(chan error, 1)
    
        go func() {
            defer func() {
                if r := recover(); r != nil {
                    ch <- errors.InternalServerError("go.micro.client", "panic recovered: %v", r)
                }
            }()
    
            // send request
            if err := stream.Send(req.Body()); err != nil {
                ch <- err
                return
            }
    
            // recv request
            if err := stream.Recv(resp); err != nil {
                ch <- err
                return
            }
    
            // success
            ch <- nil
        }()
    
        select {
        case err := <-ch:
            grr = err
            return err
        case <-ctx.Done():
            grr = ctx.Err()
            return errors.Timeout("go.micro.client", fmt.Sprintf("%v", ctx.Err()))
        }
    }
```

  

前面都是设置 Transport 组件请求头信息：



![img](/assets/post/9f1801b8c6ac8c497df873bed53b8b0fd226422d180e93b77597df986cec7b11.png)



其中比较重要的是 `Content-Type` 字段，用于设置内容编码格式，默认是 `application/protobuf`，然后我们会通过它调用 `rpcClient` 的 `newCodec` 方法设置 Codec 组件，关于这一组件的底层实现我们放到下一篇去讲，这里默认的 Codec 实现是 `proto`，对应源码位于 `src/github.com/micro/go-micro/codec/proto/proto.go`，我们通过该实现类对服务请求和响应信息进行编解码。



接下来从客户端连接池中获取一个连接，如果连接池为空的话，则调用 Transport 默认实现类（这里是 `httpTransport` ）的 `Dial` 方法新建连接并返回：



```go
    func (h *httpTransport) Dial(addr string, opts ...DialOption) (Client, error) {
        dopts := DialOptions{
            Timeout: DefaultDialTimeout,
        }
    
        for _, opt := range opts {
            opt(&dopts)
        }
    
        var conn net.Conn
        var err error
    
        // TODO: support dial option here rather than using internal config
        if h.opts.Secure || h.opts.TLSConfig != nil {
            config := h.opts.TLSConfig
            if config == nil {
                config = &tls.Config{
                    InsecureSkipVerify: true,
                }
            }
            config.NextProtos = []string{"http/1.1"}
            conn, err = newConn(func(addr string) (net.Conn, error) {
                return tls.DialWithDialer(&net.Dialer{Timeout: dopts.Timeout}, "tcp", addr, config)
            })(addr)
        } else {
            conn, err = newConn(func(addr string) (net.Conn, error) {
                return net.DialTimeout("tcp", addr, dopts.Timeout)
            })(addr)
        }
    
        if err != nil {
            return nil, err
        }
    
        return &httpTransportClient{
            ht:       h,
            addr:     addr,
            conn:     conn,
            buff:     bufio.NewReader(conn),
            dialOpts: dopts,
            r:        make(chan *http.Request, 1),
            local:    conn.LocalAddr().String(),
            remote:   conn.RemoteAddr().String(),
        }, nil
    }
```



该方法的核心是调用 `newConn` 建立与指定服务节点（即从注册中心获取的部署微服务服务端代码的节点）的连接，这里用到了 [net](https://golang.google.cn/pkg/net/) 包提供的 `DialTimeout` 方法建立网络连接。连接建立成功后会返回一个 `httpTransportClient` 实例，其中包含了客户端连接的所有信息，在 `rpc_pool.go` 的 `getConn` 方法中，又将其封装到 `poolConn` 对象中并返回。



回到 `rpcClient` 的 `call` 方法中，接下来是一个 `defer` 语句，用于将本次新建立的连接信息添加到客户端连接池以便下次复用。再往后是编码对象和响应对象的初始化，以及初始化一个流对象，流对象中包含了请求实例、响应实例、请求上下文、编码实例、请求序列号以及关闭信号，我们将通过这个流对象在指定连接上发送请求、接收响应，对应的实现在后面的 go 协程语句中，意味着我们可以在客户端并发请求远程服务，最后 `call` 方法执行完毕调用，调用 `defer stream.Close()` 关闭这个流。



下面我们深入分析下在指定连接上发送请求、接收响应的源码，Go Micro 框架将这两个操作封装到了 Codec 层，我们可以看下 `stream.Send` 和 `stream.Recv` 的源码：



```go
    func (r *rpcStream) Send(msg interface{}) error {
        r.Lock()
        defer r.Unlock()
    
        if r.isClosed() {
            r.err = errShutdown
            return errShutdown
        }
    
        req := codec.Message{
            Id:       r.id,
            Target:   r.request.Service(),
            Method:   r.request.Method(),
            Endpoint: r.request.Endpoint(),
            Type:     codec.Request,
        }
    
        if err := r.codec.Write(&req, msg); err != nil {
            r.err = err
            return err
        }
    
        return nil
    }
    
    func (r *rpcStream) Recv(msg interface{}) error {
        r.Lock()
        defer r.Unlock()
    
        if r.isClosed() {
            r.err = errShutdown
            return errShutdown
        }
    
        var resp codec.Message
    
        if err := r.codec.ReadHeader(&resp, codec.Response); err != nil {
            if err == io.EOF && !r.isClosed() {
                r.err = io.ErrUnexpectedEOF
                return io.ErrUnexpectedEOF
            }
            r.err = err
            return err
        }
    
        switch {
        case len(resp.Error) > 0:
            // We've got an error response. Give this to the request;
            // any subsequent requests will get the ReadResponseBody
            // error if there is one.
            if resp.Error != lastStreamResponseError {
                r.err = serverError(resp.Error)
            } else {
                r.err = io.EOF
            }
            if err := r.codec.ReadBody(nil); err != nil {
                r.err = err
            }
        default:
            if err := r.codec.ReadBody(msg); err != nil {
                r.err = err
            }
        }
    
        return r.err
    }
```



这样做的好处是统一对请求和响应进行编码、解码操作，关于编码和解码的细节我们放到 Codec 中去介绍，发送请求操作定义在 codec 实现类的 `Write` 方法中，这里就是 `codec/proto` 包里的 `Write` 方法：



```go
    func (c *Codec) Write(m *codec.Message, b interface{}) error {
        p, ok := b.(proto.Message)
        if !ok {
            return nil
        }
        buf, err := proto.Marshal(p)
        if err != nil {
            return err
        }
        _, err = c.Conn.Write(buf)
        return err
    }
```

  

这里的 `c.Conn` 就是我们在前面初始化 Codec 对象时传入的 `poolConn` 实例，对应的 `Write` 方法实现则在 `httpTransportClient` 类中，最终追溯到 [net](https://golang.google.cn/pkg/net/) 包的 `(*Conn).Write` 方法，通过 HTTP 协议从客户端将编码后的请求信息发送给服务端。



服务端如果已经启动则会监听客户端请求，收到请求后进行处理并返回响应，客户端通过 `stream.Recv` 方法接收响应，该方法先读取响应头，如果没有报错则继续读取响应实体，`codec/proto` 的 `ReadHeader` 实现为空，默认永远不会报错，然后我们来看下 `ReadBody` 的实现：



```go
    func (c *Codec) ReadBody(b interface{}) error {
        if b == nil {
            return nil
        }
        buf, err := ioutil.ReadAll(c.Conn)
        if err != nil {
            return err
        }
        return proto.Unmarshal(buf, b.(proto.Message))
    }
```

对应的响应获取逻辑主要在 `ioutil.ReadAll(c.Conn)` 中，这段代码调用了 [ioutil](https://golang.google.cn/pkg/io/ioutil/) 包的 `ReadAll` 方法从指定客户端连接 `httpTransportClient` 实例上读取所有响应数据，如果没有错误的话将缓冲数据解码后返回给客户端。



最后，`greeter.Hello` 方法将最终响应信息返回，交给客户端打印出来：



```
    fmt.Println(rsp.Greeting)
```

  

就是我们在终端看到的结果了：



![img](/assets/post/93a1b2382f682e84e3aec267fa8454fed2c866ca21242ea4293af250d71de1e4.png)