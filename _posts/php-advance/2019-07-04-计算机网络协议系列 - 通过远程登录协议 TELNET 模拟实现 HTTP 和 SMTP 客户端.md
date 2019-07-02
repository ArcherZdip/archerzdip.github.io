---
title: 计算机网络协议系列 - 通过远程登录协议 TELNET 模拟实现 HTTP 和 SMTP 客户端

layout: post

category: blog

tags: |-

  PHP

  计算机网络协议系列
  
  应用层协议

  Telnet
---



# 计算机网络协议系列（二十五）

所谓远程登录指的是从本地计算机登录到网络另一端的计算机（通常是服务器或者云主机实例），远程登录成功后，就可以直接使用这些主机上的应用，还可以对这些计算机进行参数配置。

适用于远程登录的协议主要有两种：TELNET 和 SSH（Secure SHell）。我们首先来介绍 TELNET。

TELNET 基于 TCP 连接将向主机发送文字命令并在主机上执行，常用于登录路由器或高性能交换机等网络设备进行相应的设置。

TELNET 客户端是指利用 TELNET 协议实现远程登录的客户端程序，很多情况下，它的程序名就是 `telnet` 命令。TELNET 客户端通常与目标主机的 23 号端口连接，并与监听这个端口的服务端程序 `telnetd` 进行交互。

注：在 Windows 系统中，要启用 TELNET 客户端，需要在「控制面板->程序和功能->启用或关闭Windows功能」中勾选「TELNET客户端」并确定方能在命令行中使用。

`telnet` 命令格式如下：

​    telnet 主机名(或IP) TCP端口号

端口号为 80 时连接 HTTP、为 25 时连接 SMTP、为 21 时连接到 FTP。比如我们可以通过 `telnet` 连接到 `www.baidu.com` 的 `80` 端口：

![img](/assets/post/3915f585c15433644fceb712fc747f033795bffe7881adad2d0fba47d4b93502.png)

这种方法可用于测试服务器指定端口是否可用。我们还可以通过客户端 `telnet` 命令模拟 HTTP 请求，比如我们现在通过 `GET` 方法访问百度首页（输入请求头后两次回车才会返回响应）：

![img](/assets/post/9ac658c9d4b39de43a3a86b54d6a7a0063fb5ced31028a1c9b223c7313295e06.png)

还可以直接在 TELNET 客户端构建查询请求：

![img](/assets/post/76d489af22d2687646c334b36e64df0a2947adc1ec5ec5f5479da0d1fd495f3c.png)

理论上支持构建所有类型的 HTTP 请求，只要你构造的 HTTP 请求头和请求实体符合 HTTP 协议规范（关于 HTTP 协议我们后面会详细介绍）。

当然，TELNET 不仅限于构建 HTTP 请求，我们还可以通过它来模拟 SMTP 协议实现邮件发送。以 163 邮箱为例，在开始之前，我们先把用户名和密码通过 Base64 编码进行加密处理，然后通过客户端 `telnet` 命令构建 SMTP 请求发送邮件：

```bash
~ telnet smtp.163.com 25    // 通过 25 号端口登录 163 SMTP 服务器
Trying 220.181.12.12...
Connected to smtp.163.com.
Escape character is '^]'.
220 163.com Anti-spam GT for Coremail System (163com[20141201])
HELO localhost   // 通过 HELO 指令向服务器打招呼，并告知客户端机器的名字，可以随便取
250 OK
AUTH LOGIN       // 通过认证指令发起认证请求
334 dXNlcm5hbWU6
// 这里输入Base64编码后的用户名，注意用户名不要带 @163.com 后缀
334 UGFzc3dvcmQ6
// 这里输入Base64编码后的密码
235 Authentication successful
MAIL FROM:<yaojinbu@163.com>  // 通过 MAIL FROM 指令指定发件箱
250 Mail OK
RCPT TO:<yaojinbu@outlook.com>  // 通过 RCPT TO 指令指定收件箱
250 Mail OK
DATA            // 通过 DATA 指令声明开始编写邮件正文，最后一行必须是 .
354 End data with <CR><LF>.<CR><LF>
From:yaojinbu@163.com      
To:yaojinbu@outlook.com
Subject:TELNET MAIL
This is a mail from TELNET CLIENT!
.                             
250 Mail OK queued as smtp8,DMCowACXJ5aCjplcp0_eMg--.60339S2 1553567438
QUIT            // 邮件发送成功，断开连接
221 Bye
Connection closed by foreign host.
```

这个时候，到收件箱就能看到刚刚通过 TELNET 客户端发送出去的邮件了，关于上述命令及返回的 SMTP 响应码后面介绍 SMTP 协议的时候还会详细介绍，这里我们主要是为了熟悉 TELNET 客户端的使用。

此外，TELNET 客户端作为仿真客户端，理论上还可以用于模拟所有应用层协议通信，比如基于 FTP 进行文件传输等，这里我们可以看到，通过遵循相应的应用层协议规范，我们完全可以自己实现简单的 HTTP、电子邮件和文件传输客户端，日常在操作系统上所使用的浏览器、Outlook、邮箱大师、FileZilla 等客户端软件不过是在此基础上提供了更加丰富的功能和更好的用户体验而已，了解这些底层的协议规范并模拟其实现，可以帮助我们更好的理解日常使用软件、工具的底层原理，同时也能帮助我们理解很多框架和工具的底层源码，最终编写出更加健壮的代码。

TELNET 虽然很简单，功能也比较齐全，但是在传输过程中没有对数据进行加密，有被窃听的风险，所以下一篇我们来介绍一种更加安全的远程登录协议 —— SSH。