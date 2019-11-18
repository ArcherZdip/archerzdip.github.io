---
title: 我为什么推荐大家使用 Nginx 而不是 Apache？
layout: post
category: blog
tags: |-
  Apache
  Nginx
  面试
---



# 我为什么推荐大家使用 Nginx 而不是 Apache？



来源<https://www.imydl.com/linux/8252.html>



`最后Nginx和Apache的差异总结成一句话就是：“Nginx适合处理静态请求和反向代理，Apache适合处理动态请求”。但这个差异化只有在请求量达到一定的阈值时表现差异才能表现出来，对于 WordPress 、 Typecho 等等这里动态站点来说某一天流量达到这个阈值的时候，还可以部署LNMPA这样的生产环境来应对和解决。所以流量阈值需求不到的时候，选择Nginx是性价比最好的选择了。`



无论是 Nginx 还是 Apache 都是 Web 服务器应用，通俗点说我们的网站都是需要 Web 服务器应用来展现给客户的，而服务器是供 Web 服务器应用正常稳定的运行的基础。所以说选择好 Web 服务器应用是会影响到网站性能表现的，甚至会影响到用户的浏览体验。而目前比较主流的 Web 服务器应用也就是 Nginx 和 Apache 了，今天就给大家阐述一下为什么我一直都推荐大家使用 Nginx 而不是 Apache？

[![我为什么推荐大家使用 Nginx 而不是 Apache？](/assets/post/2018071108572898.png)](https://www.imydl.com/wp-content/uploads/2018/07/2018071108572898.png)

有关 Nginx 和 Apache 的介绍我就不做赘述了，大家自行百度、谷歌一下就可以了解了，废话不多说了，直奔主题：

1、作为 Web 服务器：相比 Apache，Nginx 使用更少的资源，支持更多的并发连接，体现更高的效率，这点使 Nginx 尤其受到虚拟主机提供商的欢迎。在高连接并发的情况下，Nginx 是 Apache 服务器不错的替代品；Nginx 在美国是做虚拟主机生意的老板们经常选择的软件平台之一。能够支持高达 50000 个并发连接数的响应，感谢 Nginx 为我们选择了 epoll and kqueue 作为开发模型。

Nginx 作为负载均衡服务器：Nginx 既可以在内部直接支持 Rails 和 PHP 程序对外进行服务，也可以支持作为 HTTP 代理服务器对外进行服务。Nginx 采用 C 进行编写，不论是系统资源开销还是 CPU 使用效率都比 Perlbal 要好很多。

[![我为什么推荐大家使用 Nginx 而不是 Apache？](/assets/post/2018071108573123.png)](https://www.imydl.com/wp-content/uploads/2018/07/2018071108573123.png)

2、Nginx 配置简洁,Apache 复杂，Nginx 启动特别容易,并且几乎可以做到 7*24 不间断运行，即使运行数个月也不需要重新启动。你还能够不间断服务的情况下进行软件版本的升级。Nginx 静态处理性能比 Apache 高 3 倍以上，Apache 对 PHP 支持比较简单，Nginx 需要配合其他后端来使用，Apache 的组件比 Nginx 多。

[![我为什么推荐大家使用 Nginx 而不是 Apache？](/assets/post/2018071108582973.jpg)](https://www.imydl.com/wp-content/uploads/2018/07/2018071108582973.jpg)

3、最核心的区别在于 Apache 是同步多进程模型，一个连接对应一个进程；Nginx 是异步的，多个连接（万级别）可以对应一个进程。

[![我为什么推荐大家使用 Nginx 而不是 Apache？](/assets/post/201807110857299.png)](https://www.imydl.com/wp-content/uploads/2018/07/201807110857299.png)

4、Nginx 的优势是处理静态请求，cpu 内存使用率低，Apache 适合处理动态请求，所以现在一般前端用 Nginx 作为反向代理抗住压力，Apache 作为后端处理动态请求。

#### Nginx 相对 Apache 的优点

- 轻量级，同样起 web 服务，比 Apache 占用更少的内存及资源
- 抗并发，Nginx 处理请求是异步非阻塞的，而 Apache 则是阻塞型的，在高并发下 Nginx 能保持低资源低消耗高性能
- 高度模块化的设计，编写模块相对简单
- 社区活跃，各种高性能模块出品迅速啊

#### Apache 相对 Nginx 的优点

- rewrite，比 Nginx 的 rewrite 强大
- 模块超多，基本想到的都可以找到
- 少 bug，Nginx 的 bug 相对较多
- 超稳定

存在就是理由，一般来说，需要性能的 web 服务，用 Nginx。如果不需要性能只求稳定，那就 Apache 吧。后者的各种功能模块实现得比前者，例如 ssl 的模块就比前者好，可配置项多。

[![我为什么推荐大家使用 Nginx 而不是 Apache？](/assets/post/2018071108583065.png)](https://www.imydl.com/wp-content/uploads/2018/07/2018071108583065.png)

这里要注意一点，epoll(freebsd 上是 kqueue)网络 IO 模型是 Nginx 处理性能高的根本理由，但并不是所有的情况下都是 epoll 大获全胜的，如果本身提供静态服务的就只有寥寥几个文件，Apache 的 select 模型或许比 epoll 更高性能。当然，这只是根据网络 IO 模型的原理作的一个假设，真正的应用还是需要实测了再说的。

最后 Nginx 和 Apache 的差异总结成一句话就是：“Nginx 适合处理静态请求和反向代理，Apache 适合处理动态请求”。但这个差异化只有在请求量达到一定的阈值时表现差异才能表现出来，对于 WordPress 、 Typecho 等等这里动态站点来说某一天流量达到这个阈值的时候，还可以部署 LNMPA 这样的生产环境来应对和解决。所以流量阈值需求不到的时候，选择 Nginx 是性价比最好的选择了。