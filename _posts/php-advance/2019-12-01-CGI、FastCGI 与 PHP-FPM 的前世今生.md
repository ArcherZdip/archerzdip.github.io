---
title: CGI、FastCGI 与 PHP-FPM 的前世今生

layout: post

category: blog

tags: |-

  PHP

  CGI
  
  FastCGI

  PHP-FPM
---



# CGI、FastCGI 与 PHP-FPM 的前世今生



在介绍基于 Nginx + PHP-FPM 实现 PHP Web 项目请求处理及响应发送完整流程之前，有必要先给大家简单科普下 PHP-FPM。

PHP-FPM 的全称是 PHP FastCGI Process Manager，即 PHP FastCGI 进程管理器，要了解 PHP-FPM ，首先要看看 CGI 与 FastCGI 的关系。

CGI 的英文全名是 Common Gateway Interface，即通用网关接口，是 Web 服务器调用外部程序时所使用的一种服务端应用的规范。

早期的 Web 通信只是按照客户端请求将保存在 Web 服务器硬盘中的数据转发过去而已，这种情况下客户端每次获取的信息也是同样的内容（即静态请求，比如图片、样式文件、HTML文档），而随着 Web 的发展，Web 所能呈现的内容更加丰富，与用户的交互日益频繁，比如博客、论坛、电商网站、社交网络等，这个时候仅仅通过静态资源已经无法满足 Web 通信的需求，所以引入 CGI 以便客户端请求能够触发 Web 服务器运行另一个外部程序，客户端所输入的数据也会传给这个外部程序，该程序运行结束后会将生成的 HTML 和其他数据通过 Web 服务器再返回给客户端（即动态请求，比如基于 PHP、Python、Java 实现的应用）。利用 CGI 可以针对用户请求动态返回给客户端各种各样动态变化的信息。

FastCGI 顾名思义，是 CGI 的升级版本，为了提升 CGI 的性能而生，CGI 针对每个 HTTP 请求都会 fork 一个新进程来进行处理（解析配置文件、初始化执行环境、处理请求），然后把这个进程处理完的结果通过 Web 服务器转发给用户，刚刚 fork 的新进程也随之退出，如果下次用户再请求动态资源，那么 Web 服务器又再次 fork 一个新进程，如此周而复始循环往复。而 FastCGI 则会先 fork 一个 master 进程，解析配置文件，初始化执行环境，然后再 fork 多个 worker 进程（与 Nginx 有点像），当 HTTP 请求过来时，master 进程将其会传递给一个 worker 进程，然后立即可以接受下一个请求，这样就避免了重复的初始化操作，效率自然也就提高了。而且当 worker 进程不够用时，master 进程还可以根据配置预先启动几个 worker 进程等着；当空闲 worker 进程太多时，也会关掉一些，这样不仅提高了性能，还节约了系统资源。

这样一来，PHP-FPM 就好理解了，FastCGI 只是一个协议规范，需要每个语言具体去实现，PHP-FPM 就是 PHP 版本的 FastCGI 协议实现，有了它，就是实现 PHP 脚本与 Web 服务器（通常是 Nginx）之间的通信，同时它也是一个 PHP SAPI，从而构建起 PHP 解释器与 Web 服务器之间的桥梁。

PHP-FPM 负责管理一个进程池来处理来自 Web 服务器的 HTTP 动态请求，在 PHP-FPM 中，master 进程负责与 Web 服务器进行通信，接收 HTTP 请求，再将请求转发给 worker 进程进行处理，worker 进程主要负责动态执行 PHP 代码，处理完成后，将处理结果返回给 Web 服务器，再由 Web 服务器将结果发送给客户端。这就是 PHP-FPM 的基本工作原理，

PHP-FPM 有自己独立的配置文件 php-fpm.conf 用于对 PHP-FPM 进行配置，感兴趣的同学可以去看看。