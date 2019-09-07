---
title: 计算机网络协议系列 - HTTP 协议篇：URL 与 Web 资源

layout: post

category: blog

tags: |-

  PHP

  计算机网络协议系列
  
  HTTP 协议篇

  URL

  Web

---



# 计算机网络协议系列（三十三）



前面花了三篇的篇幅介绍了 Web 的由来与兴起，以及承载 Web 通信的 HTTP 协议的总体概述，接下来我们将围绕 HTTP 协议的细节具体展开讨论，包括 HTTP 报文、请求、响应、Web 服务器、HTTPS、认证、构建 Web 内容的技术以及 Web 安全等，首先我们从 Web 资源的入口 —— URL 开始。

**浏览互联网资源**

前面在[概述篇](<https://archerzdip.github.io/%E8%AE%A1%E7%AE%97%E6%9C%BA%E7%BD%91%E7%BB%9C%E5%8D%8F%E8%AE%AE%E7%B3%BB%E5%88%97-HTTP-%E5%8D%8F%E8%AE%AE%E7%AF%87-HTTP-%E6%A6%82%E8%BF%B0-%E4%B8%8A/>)中我们已经提到，URL 是统一资源定位符（Uniform Resource Location）的英文缩写，是浏览器寻找信息时所需的资源位置描述，通过 URL，才能找到、使用并共享互联网上大量的数据资源。

URI（统一资源标识符）是一类更通用的资源标识符，URL 实际上是它的一个子集。URI 是一个通用的概念，有两个主要的子集 URL 和 URN 构成，URL 是通过描述资源的位置来标识资源的，而 URN 则是通过名字来识别资源的，与它们当前所处的位置无关。

HTTP 规范将更通用的概念 URI 作为其资源标识符，但实际上，HTTP 应用程序处理的只是 URI 的 URL 子集。

以 https://laravelacademy.org/programmer-internal-skills-series 为例，URL 分为以下三个部分：

\- URL 的第一个部分是方案（scheme），这里是 https（怎样访问资源）。

\- URL 的第二个部分是域名（host），告知资源位于何处（服务器的位置），这里是 laravelacademy.org。

\- URL 的第三个部分是资源路径（path），说明了请求服务器上哪个特定的本地资源，这里是 /programmer-internal-skills-series。

**URL 语法**

URL 的语法因方案不同而略有差异，大多数 URL 方案的 URL 语法都建立在这个由 9 个部分组成的通用格式上：

```
<scheme>://<user>:<password>@<host>:<port>/<path>;<params>?<query>#<frag>
```

​    

以下是上述每个部分的描述信息：

![img](/assets/post/ef172ca26ce58a379e0834173e28b1b4da473fc58ce08793b6b1229bc59830d7.png)

我们日常看到的 URL 大多是 <scheme>://<host>/<path>?<query>#<frag> 这种格式，包含其他组件的 URL 很少见，因为 Web 资源大多是免费的，即使需要认证也是通过服务器端技术基于 Cookie + Session 来实现，显示指定不安全，存在诸多问题，关于认证部分，我们后面会单独讲到；而「端口」一般都是隐藏的，对于 http 而言，默认端口号是 80，对于 https 而言，默认端口号是 443；至于「参数」，通常我们将其放到「查询」字符串中，比如 https://laravelacademy.org/search?keyword=laravel；片段用于在当前文档中定位到某个片段，比如 https://laravelacademy.org/programmer-internal-skills-series#data-structure-and-algorithm，要结合 HTML 锚点才能实现。

**URL 编码**

URL 通常使用 ASCII 字符集，对于不安全的字符则使用「%+两个ASCII码的十六进制数」进行编码，以便在任何系统中都可以被解析，从而实现可移植性和完整性。

客户端应用程序在向其他应用程序发送任意 URL 之前最好把所有不安全或受限字符进行编码。在 PHP 中，我们通常使用 urlencode 函数对 URL 进行编码。

**URL scheme**

URL 方案（scheme）除了常见的 http 和 https 之外，还有很多其他方案，比如 ftp、mailto、file 等，这些我们平时偶尔也会在浏览器地址栏中用到，不同方案的 URL 格式基本一致：

- ftp：从 FTP 服务器上传或下载文件，一般格式为 ftp://<user>:<password>@<host>:<port>/<path>;<params>；
- rtsp、rtspu：可以通过实时流传输协议解析的音视频媒体资源的标识符，一般格式为 rtsp://<user>:<password>@<host>:<port>/<path>；
- mailto：指向的是电子邮箱地址，比如 mailto:yaojinbu@outlook.com，访问该 URL 会向指定邮箱用户发送邮件；
- file：表示一台指定主机上可以直接访问的文件，一般用于本地、网络文件系统或其他文件共享系统（比如网络邻居）文件的访问，一般格式为 `file://<host>/<path>`；
- news：可用于访问特定的文章或新闻组；
- telnet：用于访问交互式业务，表示的并不是对象自身，而是可通过 TELNET 访问的交互式应用程序

**相关资源**

更多关于 URL 的信息，可以参考以下资源：

- [W3C 有关 URI 和 URL 命名和寻址的页面](https://www.w3.org/Addressing/)
- [统一资源定位符 RFC 1738](https://www.ietf.org/rfc/rfc1738)
- [URL 通用语法 RFC 2396](https://www.ietf.org/rfc/rfc2396)
- [URN 语法 RFC 2141](https://www.ietf.org/rfc/rfc2141)