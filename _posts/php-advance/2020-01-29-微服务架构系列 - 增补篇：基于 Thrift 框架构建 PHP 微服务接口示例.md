---
title: 微服务架构系列 - 增补篇：基于 Thrift 框架构建 PHP 微服务接口示例
layout: post
category: blog
tags: |-
  PHP
  微服务架构系列
  Thrift
---



# 微服务架构系列（三十七）



**Thrift 简介和系统架构**



Thrift 是由 Facebook 开源的轻量级、跨语言 RPC 框架，为数据传输、序列化以及应用级程序处理提供了清晰的抽象和实现。我们可以通过中间语言 IDL 来定义 RPC 接口和数据类型，再通过编译器来生成不同语言对应的代码，最后基于这些自动生成的代码通过相应的编程语言来构建 RPC 客户端和服务端（基本流程和 Go Micro 框架通过 Protoc 生成代码再通过 Go 语言编写微服务代码差不多）。目前，Thrift 支持包括 C++、C#、Erlang、Java、PHP、Python、Go、NodeJS 在内的超过二十种编程语言，如果你的公司正在进行多语言微服务框架选型，Thrift 是一个不错的选择。



我们先来大致看一下 Thrift 框架的整体架构：



![img](/assets/post/Fvge3gpbcPzpdp9XFw-DZCBN8adW.png)



Thrift 框架主要包含以下组件：



- 代码生成器（Language）：根据 Thrift IDL 文件生成不同语言代码，位于 `compiler` 目录内。
- 传输层（Transport）：传输层细分为低级传输层和复写传输层，低级传输层更底层，靠近网络层，包括 TCP/IP、TLS、File、Pipe 等，作为框架接收报文的入口，提供各种底层实现如 Socket 创建、读写、连接等；复写传输层是基于低级传输层实现的高级传输层，包括 HTTP、Framed、Buffered、zlib 等实现，复写传输层可以被协议层直接使用，用户也可以通过重写低级传输层和复写传输层实现自己的传输层。
- 协议层（Protocol）：协议层主要负责解析请求、应答报文为具体的结构体、类实例，供处理层调用，目前支持的协议包括 Binary、JSON、Compact、Multiplexed 等。
- 处理层（Language）：由代码生成器生成，根据获取到的具体信息如方法名，进行具体的接口处理，处理层构造函数的入口包含一个处理器（Handler），处理器由业务方进行具体的实现，然后在处理层内被调用，并应答处理结果。
- 服务层（Client/Server）：融合传输层、协议层、处理层，自身包含各种不同类型的服务模型，如 Thread Pool Server、Simple Server、Non Blocking Server、Forking Server 等。



**基于 Thrift 框架编写微服务代码**



接下来，学院君将给大家演示借助 Thrift 框架实现基于 PHP 语言的问候微服务，在此之前，先要在系统中安装 Thrift，以 Mac 为例，我们通过 brew 来快速安装：



```
    brew install thrift
```



![img](/assets/post/FmWkhV_yi8zlyppnEmjixXF1N_fP.png)



安装完成后，我们在本地创建项目目录，比如我这里在 `~/Development/php` 目录下新建了一个 `thrift` 作为项目目录，然后在 VSCode 中打开这个目录。还可以安装下面这个 VSCode 扩展以支持 Thrift IDL 语法高亮：



![img](/assets/post/Fl4CLdlwFjyK6xkRHMOt0ghP3Gkj.png)



然后我们在 `thrift` 目录下创建一个 Thrift IDL 文件 `hello.thrift`：



```
    namespace php Greeter
    service Greeter 
    {
        string hello(1: string name)
    }
```

  

> 注：更多 Thrift IDL 语法细节可参考官方[文档](http://thrift.apache.org/docs/idl)和[示例](https://github.com/apache/thrift/blob/master/tutorial/tutorial.thrift)。



再运行如下指令根据 `hello.thrift` 生成相应的针对 PHP 语言的服务代码：



```
    thrift -r --gen php:server hello.thrift
```

  

通过 Composer 安装 Apache Thrift PHP 依赖包：

  

```
    composer require apache/thrift
```

  

然后，我们在 `composer.json` 中维护命名空间 `Greeter` 与目录的映射关系如下：



```
    "autoload": {
        "psr-4": {
            "Greeter\\": "gen-php/Greeter/"
        }
    }
```

  

运行 `composer dumpautoload` 让上述修改生效，这样，我们就可以完全借助 Composer 帮我们管理类的自动加载了。此时，项目目录结构如下所示：



![img](/assets/post/FmGJFRTyVTSTVMV5el3rHAb36hI0.png)

  

接下来我们就可以编写微服务的服务端代码了，在项目根目录下创建 `server.php`，初始化服务端代码如下：



```php
    <?php
    
    error_reporting(E_ALL);
    
    require_once 'vendor/autoload.php';
    
    use Thrift\Exception\TException;
    use Thrift\Factory\TTransportFactory;
    use Thrift\Factory\TBinaryProtocolFactory;
    
    use Thrift\Server\TServerSocket;
    use Thrift\Server\TSimpleServer;
    
    class GreeterHandler implements \Greeter\GreeterIf
    {
        public function hello($name) 
        {
            return '你好, ' . $name;
        }
    }
    
    try {
        $handler = new GreeterHandler();
        $processor = new \Greeter\GreeterProcessor($handler);
    
        $transportFactory = new TTransportFactory();
        $protocolFactory = new TBinaryProtocolFactory(true, true);
    
        //作为cli方式运行，监听端口，官方实现
        $transport = new TServerSocket('localhost', 8080);
        $server = new TSimpleServer($processor, $transport, $transportFactory, $transportFactory, $protocolFactory, $protocolFactory);
        $server->serve();
    } catch (TException $tx) {
        print 'TException: ' . $tx->getMessage() . "\n";
    }
```

  

这里为了简化流程，我们把服务接口处理器类放到了 `server.php` 中，该类需要实现 `\Greeter\GreeterIf` 接口，这一点和前面 Go Micro 处理逻辑一致。



服务端服务启动步骤如下：将自定义的服务处理器（Handler）传入系统自动生成的 Processor 作为处理层，然后初始化协议层和传输层，再将这些初始化后的实例传入服务层，最后调用服务层 `serve` 方法启动服务，监听 `localhost:8080` 端口等待客户端连接。



接着我们来编写客户端代码测试服务端接口，在项目根目录下创建 `client.php` 并编写代码如下：



```php
    <?php

    error_reporting(E_ALL);
    
    require_once 'vendor/autoload.php';
    
    use Thrift\Protocol\TBinaryProtocol;
    use Thrift\Transport\TSocket;
    use Thrift\Transport\TBufferedTransport;
    use Thrift\Exception\TException;
    
    try {
        $transport = new TBufferedTransport(new TSocket('localhost', 8080));
        $protocol = new TBinaryProtocol($transport);
        $client = new \Greeter\GreeterClient($protocol);
    
        $transport->open();
    
        //同步方式进行交互
        $recv = $client->hello('学院君');
        echo "\n hello:" . $recv . " \n";
    
        //异步方式进行交互
        $client->send_hello('学院君');
        echo "\n send_hello \n";
        $recv = $client->recv_hello();
        echo "\n recv_hello:" . $recv . " \n";
    
        $transport->close();
    } catch (TException $tx) {
        print 'TException: ' . $tx->getMessage() . "\n";
    }
```



在客户端代码中，我们首先也要初始化传输层和协议层，并且这两者和服务端保持一致，以便和服务端建立连接并且可以对数据进行正常的编解码，然后我们调用系统自动生成的 `Greeter\GreeterClient` 类初始化客户端实例，打开传输层建立通信，之后以同步/异步方式调用服务端接口并输出结果。



测试这个问候服务很简单，先启动服务端：



```
    php server.php
```



然后新开一个终端窗口运行客户端代码：

  

```
    php client.php
```

  

就可以在终端看到输出结果：



![img](/assets/post/Fk8LRB8g3q6GRQZnCOIj0QZVlNmU.png)



这样，我们就成功完成了问候微服务端口的编写和远程调用，那么 Thrift 底层是如何运作的呢？下一篇分享我们来简单探讨下。