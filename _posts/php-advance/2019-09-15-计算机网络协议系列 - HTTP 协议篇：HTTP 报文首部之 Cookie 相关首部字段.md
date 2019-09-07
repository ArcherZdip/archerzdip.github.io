---
title: 计算机网络协议系列 -  HTTP 协议篇：HTTP 报文首部之 Cookie 相关首部字段

layout: post

category: blog

tags: |-

  PHP

  计算机网络协议系列
  
  HTTP 协议篇

  Cookie
---



# 计算机网络协议系列（四十一） 



管理服务器与客户端之间状态的 Cookie，虽然没有被编入标准化 HTTP/1.1 的 [RFC2616](https://www.ietf.org/rfc/rfc2616.txt) 中，但在 Web  网站得到了广泛的应用。

由于 HTTP 协议本身是无状态的，引入 Cookie 的初衷就是实现客户端用户识别和状态管理。Web 网站为了管理用户的状态，通过浏览器把一些数据临时写入用户的计算机内，然后当用户继续访问该网站时，可通过通信方式取回之前发放的 Cookie，这样一来服务器就可以通过 Cookie「识别」该客户端。

从安全性上说，调用 Cookie 时，由于可以校验 Cookie 的有效期，以及发送方的域（Domain）、路径（URL Path）、协议等信息，所以正规发布的 Cookie 内的数据不会因来自其它 Web 站点和攻击者的攻击而泄露。

日常使用的 Cookie 首部字段主要是以下两个：

![img](/assets/post/c8beae9bc2933337a6a08043ea416ad6224599d8b97180080245ebcb932aca5b.png)

1）Set-Cookie

该字段用于响应首部，服务器通过该字段将需要设置的 Cookie 信息告知客户端，客户端接收到响应后将相应的 Cookie 信息存储到本地，以 Laravel 项目为例，默认的 Set-Cookie 字段及对应字段值如下所示：

```
Set-Cookie: laravel_session=eyJpdiI6InpDTWIxczdmK2hJZ1hPcWVsRU9uRUE9PSIsInZhbHVlIjoib244YVppNWFWdU04K3kxT3pUM3FmTWVqNkYxQXo3QUJHRzV0OWZnOEE1TzZKZGxxNHlpaXZnNlwvYzRrZ0RcL1lrIiwibWFjIjoiODZlZDgzNzlmNmNkZTJhNGFmNThmYTE2NGYxMTIyM2EwNGY5ZThkZmQ5MDU0NWQ0ZTJlY2M1ZTJmNjJmNDIzMiJ9; expires=Wed, 24-Apr-2019 04:03:23 GMT; Max-Age=7200; path=/; httponly
```

​    

在该字段中，我们可以设置多个属性，每个属性间通过分号分隔。

第一个属性通常是 Cookie 名称及对应值，比如这里的 `laravel_session` 是 Session ID，该属性用于实现基于 Cookie 的 Session 认证，我们在客户端及服务器可以通过 `laravel_session` 字段获取对应的 Cookie 值。

后面其它的属性则是描述该 Cookie 的其它附加属性，这些附加属性对所有 Cookie 来说都是通用的：

- expires：指定 Cookie 的有效期，省略的话默认在浏览器会话时间内有效（浏览器关闭失效），Cookie 一旦发送至客户端，就不能在服务器端显式删除，只能通过覆盖实现对客户端 Cookie 的「删除」；
- Max-Age：和 expires 作用类似，用于指定从现在开始该 Cookie 存在的秒数，如果同时指定了expires 和 Max-Age，那么 Max-Age 的值将优先生效；
- path：指定 Cookie 生效的路径，默认路径是根路径 /；
- domain：指定 Cookie 所属的域名，省略的话默认是当前域名，Cookie 只有在所属域名下才能获取，不能跨域名获取 Cookie，比如在 `laravelacademy.org` 对应应用下生成的 Cookie 所属域名是 `laravelacademy.org`，对应 Cookie 只能在 `laravelacademy.org` 域名应用下获取，在 `blog.laravelacademy.org` 对应应用下生成的 Cookie 所属域名是 `blog.laravelacademy.org`，对应 Cookie 只能在 `blog.laravelacademy.org` 域名应用下获取，依次类推。我们还可以通过通配符在生成 Cookie 时指定所属域名为 `*.laravelacademy.org`，这样一来，不管是 `laravelacademy.org` 还是 `blog.laravelacademy.org`，都可以获取到相应的 Cookie；
- secure：限制浏览器只有在页面启用 HTTPS 安全连接时才可以发送 Cookie，省略的话，无论 HTTP 还是 HTTPS 都可以发送；
- httponly：是 Cookie 的扩展功能，使 JavaScript 无法获取 Cookie，主要目的是为了防止跨站脚本攻击（XSS）对 Cookie 信息的窃取。

如果服务器设置了多个 Cookie，那么响应首部中会包含多个 Set-Cookie 首部字段：

![img](/assets/post/432edbbecdabb1a3fccaf1ada5153227cc89852fa6d06abecb9b987173b14a35.png)

2）Cookie

该字段用于请求首部，客户端通过该字段告知服务器当客户端想要获取 HTTP 状态管理支持时，就会在请求中包含从服务器接收到的 Cookie：

```
Cookie: hello=eyJpdiI6IktlV2RlQUhnbDBJN2Z0UUhFSHl3bkE9PSIsInZhbHVlIjoieElBdFpOV3crNm5IZytnRzlJUW1LUT09IiwibWFjIjoiNzFiZGEzMzg1MzgyYTMyYjM0YzcyNTViZWU2NGI2MDM2NzJhMGEwNmFkYWE5ZGY4N2I5ZDA4ZWQ0NmVkZjcyOCJ9; XSRF-TOKEN=eyJpdiI6IndOeWNWVmxXVEdpZkdlWFFkMENtckE9PSIsInZhbHVlIjoiYWJNb28yMlROWE1YOEVyTnhrbmJwYjRpdHB3S2diUDBcLzI2d1ViNXpRYkxzb2pMZEZWVll0cVFoejhlNG1jdEwiLCJtYWMiOiI1NzUwMWRjYzhjMjAwMDkwMWI4NDY0ZTIzMzY2NDYwMDY1NmYzZmMyOTA3ZjM2YTRmN2FmM2U1OGU3MWQyNTVkIn0%3D; laravel_session=eyJpdiI6ImpwcWx6SGttbUlCU2dCREVyRWp1WFE9PSIsInZhbHVlIjoiU0djd0Vjc3JRZzNuWUgyUWlRSStiUURcL2RPWFpxdjBjdXRrdVRjZ1hzdDZpTGNzZWtKNXpVTTJlXC9Fbms3ZWpqIiwibWFjIjoiMmI0NmJiZWYyOGViOGI5ZDVhY2EwMjI4NjAwODYwMzg1ZGZlODY0NjExNzIzMjczMGRiMjdjNDIyMTdiNzQ1MCJ9
```

​    

可以看到，该 Cookie 请求首部字段中包含了上一步从服务器响应首部中获取到的所有 Cookie。