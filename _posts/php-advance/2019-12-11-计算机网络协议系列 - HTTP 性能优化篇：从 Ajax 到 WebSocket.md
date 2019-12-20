---
title: 计算机网络协议系列 - HTTP 性能优化篇：从 Ajax 到 WebSocket

layout: post

category: blog

tags: |-
  PHP
  计算机网络协议系列
  HTTP 协议篇
  Ajax
  WebSocket
---



# 计算机网络协议系列（五十三）



**背景**

在建立 HTTP 标准规范的时候，设计者的初衷主要是想把 HTTP 当做传输静态 HTML 文档的协议，但是随着互联网的发展，Web 应用的用途更加多样性，逐渐诞生了电商网站（如淘宝、亚马逊）、社交网络（如Facebook、Twitter）等功能更加复杂的应用，这些网站的功能单纯靠静态 HTML 显然是实现不了的，因此又产生了通过 CGI 将 Web 服务器与后台动态应用连接起来，从而通过后台脚本语言实现的应用驱动网站功能，这些脚本语言包括 PHP、Python、Ruby、Node、JSP、ASP 等，通过这种方式虽然解决了 Web 应用的功能扩展问题，但是 HTTP 协议本身的限制和性能问题却没有得到有效解决。

HTTP 功能和性能上的问题虽然可以通过创建一套新的协议来彻底解决，但是目前基于 HTTP 的服务端和客户端应用遍布全球，完全抛弃不太现实，但问题却要解决，因此，诞生了很多基于 HTTP 协议的新技术和新协议来补足 HTTP 协议本身的缺陷。

**Ajax**

随着网站功能的复杂，对资源实时性的要求也越来越高，但是 HTTP 本身无法做到实时显示服务器端更新的内容，要获取服务器端的最新内容，就得频繁从客户端发起新的请求（比如刷新页面），如果服务器上没有更新，就会造成通信的浪费，而且从用户体验来说也不够友好。

为了解决这个问题，诞生了 Ajax 技术，其全称是 Asynchronous JavaScript And XML，即异步 Javascript 与 XML 技术，它是一种可以有效利用 JavaScript 与 DOM 操作，实现 Web 页面局部刷新，而不用重新加载页面的异步通信技术。其核心技术是一个名为 XMLHttpRequest 的 API， 通过 JavaScript 的调用就可以实现与服务器的通信，以便在已加载成功的页面发起请求，再通过 DOM 操作实现页面的局部刷新，在早期返回的数据格式是 XML，但是随着更加轻量级的 JSON 出现，现在 Ajax 调用多返回 JSON 格式数据，与返回完整 HTML 文档不同，局部刷新返回的数据体量更小。

Ajax 虽好，但是仍然没有从根本上解决 HTTP 的问题，请求还是得从客户端发起，而且客户端也感知不到服务器上资源的更新，如果想要获取某个部分的实时数据，还是得频繁发起 Ajax 请求，造成通信的浪费，只是这个工作不用用户做，可以交给 JavaScript 定时器去做，而且基于 Ajax 获取资源也不会刷新页面，对用户来说，体验上已经好很多。

为了彻底解决实时显示服务端资源的问题，必须有一种机制能够在服务器资源有更新的时候能够将更新实时推送到客户端，而为了实现这种机制，诞生了 WebSocket 技术。

**WebSocket**

WebSocket 本来是作为 HTML5 的一部分，而现在却变成了一个独立的协议，它是 Web 客户端与服务器之间实现全双工通信的标准。既然是全双工，就意味着不是之前那种只能从客户端向服务器发起请求的单向通信，服务端在必要的时候也可以推送信息到客户端，而不是被动接收客户端请求再返回响应。

一旦客户端与服务器之间建立起了基于 WebSocket 协议的通信连接，之后所有的通信都依靠这个协议进行，双方可以互相发送 JSON、XML、HTML、图片等任意格式的数据。由于 WebSocket 是基于 HTTP 协议的，所以连接的发起方还是客户端，而一旦建立起 WebSocket 连接，不论是服务器还是客户端，都可以直接向对方发送报文。

为了实现 WebSocket 的通信，在 HTTP 连接建立之后，还需要完成一次「握手」的步骤：

1）请求阶段

WebSocket 复用了 HTTP 的握手通道，要建立 WebSocket 通信，需要在连接发起方的 HTTP 请求报文中通过 Upgrade 字段告知服务器通信协议升级到 websocket，然后通过 Sec-WebSocket-* 扩展字段提供 WebSocket 的协议、版本、键值等信息：

![img](/assets/post/793035b0b3e3fc67462d0c3d0bc14731a560c5ac501b5d2c9220e14bb887ec90.png)

2）响应阶段

对于上述握手请求，服务器会返回 101 Switching Protocols 响应表示协议升级成功：

![img](/assets/post/1586c826d2c2e056efbb41e9ea2ab73985bf20ff153050f1ffd916a53d918360.png)

响应头中 Sec-WebSocket-Accept 字段的值是根据请求头中 Sec-WebSocket-Key 的字段值生成的，两者结合起来用于防止恶意连接和意外连接。

成功握手确立 WebSocket 连接后，后续通信就会使用 WebSocket 数据帧而不是 HTTP 数据帧。下面是 WebSocket 通信的时序图：

![img](/assets/post/e4c6275cd3550ef01878f9e37c50c9b016a1342b74e2f21fff11947fca24bf3f.png)

WebSocket 协议对应的 scheme 是 xs，如果是加密的 WebSocket 对应的 scheme 是 xss，域名、端口、路径、参数和 HTTP 协议的 URL 一样。

介绍完 WebSocket 的基本原理，下篇分享学院君将会给大家介绍 WebSocket 的客户端和服务器简单实现，客户端部分基于 JavaScript 的 WebSocket API 即可，服务器将基于 Swoole 实现。