---
title: 微服务架构系列 - 框架篇：Go Micro 底层组件篇之 Codec 源码剖析
layout: post
category: blog
tags: |-
  PHP
  微服务架构系列
  Go Micro
  Codec
---

# 微服务架构系列（三十二）



前面我们已经陆续介绍了 Go Micro 框架底层 Registry、Selector、Transport 组件的底层实现，并且在 Transport 组件中我们已经提到了 Codec 组件，如果说 Selector 是基于 Registry 查询服务节点，那么 Transport 就是基于 Codec 在发送请求和返回响应时对消息进行编码和解码，Go Micro 默认支持的编码格式包括 json、protobuf 等，还可以基于 Go Plugins 引入对 bson、msgpack 等编码格式的支持，与大多数其他编解码器不同的是，Codec 还提供了对 RPC 格式的支持，所以有对应的 jsonrpc、protorpc、bsonrpc、grpc 实现类。对应的实现源码位于 `src/github.com/micro/go-micro/codec` 目录下，不同编码格式实现位于相应的子目录中，并且都实现了 `Codec` 接口：



```
    type Codec interface {
        Reader
        Writer
        Close() error
        String() string
    }
    
    type Reader interface {
        ReadHeader(*Message, MessageType) error
        ReadBody(interface{}) error
    }
    
    type Writer interface {
        Write(*Message, interface{}) error
    }
```

  

其中，`Writer` 接口声明的方法用于对消息进行编码，`Reader` 接口方法声明的方法用于对消息进行解码，具体实现在实现 `Codec` 接口的类中完成。



对于通过 Go Micro SDK 实现的客户端 `src/hello/client.go` 发起的请求而言，默认 `Content-Type` 请求头是 `application/protobuf`，对应的 Codec 组件实现类是 `proto.Codec`，源码位于 `src/github.com/micro/go-micro/codec/proto/proto.go` 中，这个映射关系在上篇分享中已经介绍过，位于 `rpcClient` 的 `call` 方法中：



```
    cf, err = r.newCodec(req.ContentType())
```



查看 `r.newCodec` 方法实现即可看到如何通过 `Content-Type` 请求头映射对应的 Codec 实现类：



```go
    DefaultCodecs = map[string]codec.NewCodec{
        "application/grpc":         grpc.NewCodec,
        "application/grpc+json":    grpc.NewCodec,
        "application/grpc+proto":   grpc.NewCodec,
        "application/protobuf":     proto.NewCodec,
        "application/json":         json.NewCodec,
        "application/json-rpc":     jsonrpc.NewCodec,
        "application/proto-rpc":    protorpc.NewCodec,
        "application/octet-stream": raw.NewCodec,
    }
```



接下来，将 Codec 实现类、客户端连接和请求消息都封装到 `rpcCodec` 中，其中 `msg` 为请求消息类实例，`c` 为客户端连接实例，`cf` 为映射到的编码类实例：



```
    codec := newRpcCodec(msg, c, cf)
```



再将这个返回的 `rpcCodec` 实例封装到响应实例 `rpcResponse` 中以便后续返回响应时通过它对消息进行解码：



```
    rsp := &rpcResponse{
        socket: c,
        codec:  codec,
    }
```

  

最后将它们一起封装到 `rpcStream` 中，以便通过这个流实例发起请求、接收响应：



```go
    stream := &rpcStream{
        context:  ctx,
        request:  req,
        response: rsp,
        codec:    codec,
        closed:   make(chan bool),
        id:       fmt.Sprintf("%v", seq),
    }
```



请求和响应消息的编解码操作分别在后续发送请求操作 `stream.Send()` 和接收响应操作 `stream.Recv()` 中完成。



下面我们分别来看下编码和解码的底层实现，先进入 `stream.Send()` 方法查看请求信息的编码，对应的源码位于 `src/github.com/micro/go-micro/client/rpc_stream.go` 中：



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
```

  

首先，通过 `codec.Message` 构造服务请求信息，其中包含请求 ID、服务名称（`go.micro.srv.greeter`）、请求方法（`Greeter.Hello`）、服务端点（`Greeter.Hello`）以及类型，然后将构造的请求信息和 `Send` 方法中传入的 `msg` 参数（对应的是 `(*rpcRequest).Body()` 返回的请求实体字段，即 `src/hello/client.go` 中调用 `greeter.Hello` 方法传入的第二个参数）一起传入 `r.codec.Write` 方法，这里的 `r.codec` 对应的就是上述 `newRpcCodec` 方法返回的 `rpcCodec` 实例，`rpcCodec` 中的 `codec` 字段即 Codec 组件实现类实例，我们来看下 `rpcCodec` 的 `Write` 方法实现：



```go
    func (c *rpcCodec) Write(m *codec.Message, body interface{}) error {
        c.buf.wbuf.Reset()
    
        // create header
        if m.Header == nil {
            m.Header = map[string]string{}
        }
    
        // copy original header
        for k, v := range c.req.Header {
            m.Header[k] = v
        }
    
        // set the mucp headers
        setHeaders(m)
    
        // if body is bytes Frame don't encode
        if body != nil {
            b, ok := body.(*raw.Frame)
            if ok {
                // set body
                m.Body = b.Data
                body = nil
            }
        }
    
        if len(m.Body) == 0 {
            // write to codec
            if err := c.codec.Write(m, body); err != nil {
                return errors.InternalServerError("go.micro.client.codec", err.Error())
            }
            // set body
            m.Body = c.buf.wbuf.Bytes()
        }
    
        // create new transport message
        msg := transport.Message{
            Header: m.Header,
            Body:   m.Body,
        }
        // send the request
        if err := c.client.Send(&msg); err != nil {
            return errors.InternalServerError("go.micro.client.transport", err.Error())
        }
        return nil
    }
```

  

在我们的客户端请求示例代码中，该方法中目前传入的 `m` 和 `body` 数据信息如下：



![img](/assets/post/FmEaXNa7YG9isP8t20Ixir2tsu1n.png)



其中已经包含了完整的服务名称、端点（路由）、请求参数信息，在 `Write` 方法中，首先清除当前连接上缓冲数据，然后把 `c.req` （即上述传入 `newRpcCodec` 方法的第一个参数 `msg`）上的数据添加到 `m` 的 `Header` 字段中：



![img](/assets/post/Fhu9o25WKicG6RhRlPrKT-hlEVT4.png)



接下来将 `body` 参数中的数据设置到 `m` 的 `Body` 字段中，如果 `body` 是字节帧的话不再进行额外编码，否则的话要通过 `c.codec.Write` 方法对其进行指定格式的编码，这里的 `c.codec` 即通过 `Content-Type` 请求头映射到的 Codec 组件实现类实例，这里默认是 `proto`，`Write` 方法即对应 Codec 实现类的编码方法实现：



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

在这里我们最终调用更底层的 [protobuf/proto](https://github.com/golang/protobuf) 包提供的 API 对请求参数进行编码，最终将完成编码的信息设置到 `m.Body` 上。



最后我们将 `m.Header` 和编码后的 `m.Body` 传递到 `transport.Message` 完成 Transport 层请求消息队最终构造并赋值给 `msg`，最后将这个 `msg` 传入 `c.client.Send` 方法用于发起对服务端的请求。



至此，通过指定格式编码的请求操作就完成了。可以看到，在请求编码过程中，我们实际上是在 Client 层通过一个封装了 Transport 连接、Codec 编码实现以及请求参数信息的 `rpcCodec` 类统一完成编码和请求发送操作，这样做的好处是把具体的实现交给具体的底层组件，然后在上层 `rpcCodec` 进行统筹，从而将整个过程串联起来，同时也提高了系统对扩展性，避免在 Client 层出现对底层实现的依赖，如果要切换到不同的编码，只需要在请求头 `Content-Type` 进行设置即可，不用修改任何代码。



对应的响应接收解码过程也是类似，下面我们看下 `(*rpcStream).Recv` 方法实现：



```go
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

上面的 `r.codec` 对应的同样是 `rpcCodec` 类实例，对应的 `ReadHeader()` 和 `ReadBody()` 方法实现代码都位于 `rpcCodec` 类中，分别用于对响应头和响应实体进行解码操作，其中获取完整的响应信息是在 `ReadHeader()` 中通过调用 `c.client.Recv()` 方法完成的，响应报文和请求报文共用了一个 `transport.Message` 数据结构：



```go
    func (c *rpcCodec) ReadHeader(m *codec.Message, r codec.MessageType) error {
        var tm transport.Message
    
        // read message from transport
        if err := c.client.Recv(&tm); err != nil {
            return errors.InternalServerError("go.micro.client.transport", err.Error())
        }
    
        c.buf.rbuf.Reset()
        c.buf.rbuf.Write(tm.Body)
    
        // set headers from transport
        m.Header = tm.Header
    
        // read header
        err := c.codec.ReadHeader(m, r)
    
        // get headers
        getHeaders(m)
    
        // return header error
        if err != nil {
            return errors.InternalServerError("go.micro.client.codec", err.Error())
        }
    
        return nil
    }
    
    func (c *rpcCodec) ReadBody(b interface{}) error {
        // read body
        if err := c.codec.ReadBody(b); err != nil {
            return errors.InternalServerError("go.micro.client.codec", err.Error())
        }
        return nil
    }
```



和编码操作类似，在 `rpcCodec` 的上述两个解码方法中都是通过 `c.codec` 调用底层 Codec 实现组件（这里是 `proto`）的 `ReadHeader()` 和 `ReadBody()` 进行真正的解码操作：



```go
    func (c *Codec) ReadHeader(m *codec.Message, t codec.MessageType) error {
        return nil
    }
    
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

  

响应头是个空实现，响应实体同样调用了底层 protobuf/proto 包提供的 API 实现解码操作。



以上就是基于 `proto` 的 Codec 组件编解码底层实现，如果你要实现其他编码，只需要在 `Content-Type` 请求头中设置对应的编码格式即可，比如 JSON 编码可以设置为 `application/json`，正如我们在 [API 网关]中所做的那样。