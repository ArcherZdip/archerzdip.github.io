---
title: 计算机网络协议系列 - HTTP 性能优化篇：WebSocket 服务器和客户端的简单实现
layout: post
category: blog
tags: |-
  PHP
  计算机网络协议系列
  HTTP 协议篇
  WebSocket
---



# 计算机网络协议系列（五十四）



上篇给大家介绍 WebSocket 的实现原理，简单来说，WebSocket 复用了 HTTP 协议来实现握手，通过 Upgrade 字段将 HTTP 协议升级到 WebSocket 协议来建立 WebSocket 连接，一旦 WebSocket 连接建立之后，就可以在这个长连接上通过 WebSocket 数据帧进行双向通信，客户端和服务端可以在任何时候向对方发送报文，而不是 HTTP 协议那种服务端只有在客户端发起请求后才能响应，从而解决了在 Web 页面实时显示最新资源的问题。

在本篇分享中学院君将在服务端基于 Swoole 实现简单的 WebSocket 服务器，然后在客户端基于 JavaScript 实现 WebSocket 客户端，通过这个简单的实现加深大家对 WebSocket 通信过程的理解。

在正式开始本篇分享之前，先要勘个误，WebSocket 协议对应的 scheme 是 ws，如果是加密的 WebSocket 对应的 scheme 是 wss，上篇笔误写成了 xs 和 xss，望知晓。

**WebSocket 服务器**

PHP 异步网络通信引擎 Swoole 内置了对 WebSocket 的支持，通过几行 PHP 代码就可以写出一个异步非阻塞多进程的 WebSocket 服务器：

```php
    <?php
    // 初始化 WebSocket 服务器，在本地监听 8000 端口
    $server = new Swoole\WebSocket\Server("localhost", 8000);
    
    // 建立连接时触发
    $server->on('open', function (Swoole\WebSocket\Server $server, $request) {
        echo "server: handshake success with fd{$request->fd}\n";
    });
    
    // 收到消息时触发推送
    $server->on('message', function (Swoole\WebSocket\Server $server, $frame) {
        echo "receive from {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}\n";
        $server->push($frame->fd, "this is server");
    });
    
    // 关闭 WebSocket 连接时触发
    $server->on('close', function ($ser, $fd) {
        echo "client {$fd} closed\n";
    });
    
    // 启动 WebSocket 服务器
    $server->start();
```

​    

将这段 PHP 代码保存到 websocket_server.php 文件。

​    

**WebSocket 客户端**

在客户端，可以通过 JavaScript 调用浏览器内置的 WebSocket API 实现 WebSocket 客户端，实现代码和服务端差不多，无论服务端还是客户端 WebSocket 都是通过事件驱动的，我们在一个 HTML 文档中引入相应的 JavaScript 代码：

```php
    <!DOCTYPE html>
    <html>
    <head>
       <meta charset="UTF-8">
       <title>Chat Client</title>
    </head>
    <body>
    <script>
       window.onload = function () {
           var nick = prompt("Enter your nickname");
           var input = document.getElementById("input");
           input.focus();
   
           // 初始化客户端套接字并建立连接
           var socket = new WebSocket("ws://localhost:8000");
           
           // 连接建立时触发
           socket.onopen = function (event) {
               console.log("Connection open ..."); 
           }
   
           // 接收到服务端推送时执行
           socket.onmessage = function (event) {
               var msg = event.data;
               var node = document.createTextNode(msg);
               var div = document.createElement("div");
               div.appendChild(node);
               document.body.insertBefore(div, input);
               input.scrollIntoView();
           };
           
           // 连接关闭时触发
           socket.onclose = function (event) {
               console.log("Connection closed ..."); 
           }


           input.onchange = function () {
               var msg = nick + ": " + input.value;
               // 将输入框变更信息通过 send 方法发送到服务器
               socket.send(msg);
               input.value = "";
           };
       }
    </script>
    <input id="input" style="width: 100%;">
    </body>
    </html>
```

​        

将这个 HTML 文档命名为 websocket_client.html。在命令行启动 WebSocket 服务器：

```shell
    php websocket.php
```

然后在浏览器中访问 websocket_client.html，首先会提示我们输入昵称：

![img](/assets/post/21d3b2914792af6ef42994c9faa75f2bfba235c8076802f1e32d9ce6fc413a1d.png)

输入之后点击确定，JavaScript 代码会继续往下执行，让输入框获取焦点，然后初始化 WebSocket 客户端并连接到服务器，这个时候通过开发者工具可以看到 Console 标签页已经输出了连接已建立日志：

![img](/assets/post/3520410e8ae2a096a072b8cadc7cd5e7d7f63a76ba2a22bd51bef84a139508a8.png)

在 Network 里面也可以看到 WebSocket 握手请求和响应：

![img](/assets/post/1dacff577b611e110c9ec22a4a54dc6495c4844f99f5b91143ad0777e4cf5238.png)

这个时候我们在输入框中输入「你好，WebSocket！」并回车，即可触发客户端发送该数据到服务器，服务器接收到消息后会将其显示出来：

![img](/assets/post/c5aced8c5acb41f2606399a5907fc2350e5f978c27b178405d0357ab46659efe.png)

同时将「This is server」消息推送给客户端，客户端通过 onmessage 回调函数将获取到的数据显示出来。在开发者工具的 Network->WS 标签页可以查看 WebSocket 通信细节：

![img](/assets/post/b6418dae2f721d7a80aaa188e969cc387cf00d0d096c090095722b4bf8e30ffc.png)

看起来，这个过程还是客户端触发服务器执行推送操作，但实际上，在建立连接并获取到这个客户端的唯一标识后，后续服务端资源有更新的情况下，仍然可以通过这个标识主动将更新推送给客户端，而不需要客户端发起拉取请求。WebSocket 服务器和客户端在实际项目中的实现可能会更加复杂，但是基本原理是一致的。
