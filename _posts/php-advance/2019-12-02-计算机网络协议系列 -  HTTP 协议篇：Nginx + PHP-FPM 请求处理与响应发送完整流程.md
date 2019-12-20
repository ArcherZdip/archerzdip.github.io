---
title: 计算机网络协议系列 -   HTTP 协议篇：Nginx + PHP-FPM 请求处理与响应发送完整流程

layout: post

category: blog

tags: |-

  PHP

  计算机网络协议系列
  
  HTTP 协议篇

  Nginx
  PHP-FPM
---



# 计算机网络协议系列（四十四）



我们知道部署一个 LNMP（Linux+Nginx+MySQL+PHP）项目，首先要在 Linux 服务器安装相关的软件，比如 Nginx、MySQL、PHP 及相关扩展（包括 PHP-FPM），然后将 PHP 项目（比如 Laravel 应用）代码通过某种方式部署到服务器，然后在 Nginx 中为这个新项目配置一个虚拟主机，最后重启 Nginx 服务，让 Nginx 开始监听客户端连接和请求，这样，当用户在浏览器中访问应用域名时，就可以通过 Nginx 服务器将请求转发给对应的 PHP 项目进行处理。

假设我们已经准备好 LNMP 环境，并且部署好 Laravel 应用代码，配置好虚拟主机，Nginx、MySQL、PHP-FPM 等服务都已经启动并处于监听状态，下面我们来看看一个完整的 HTTP 事务在服务器端是如何实现的。

**建立连接**

Nginx 服务启动后就会启动一个 master 进程和多个 worker 进程（一般与 CPU 个数相同），master 主要负责处理 Nginx 主服务的启动、关闭与重载，以及维护 worker 进程的运行状态，具体的 HTTP 连接与请求处理工作由 worker 进程来完成，每个 worker 进程上都可以处理多个连接请求，底层实现的原理是事件驱动和多路 IO 复用，这一点和 Apache fork 多个子进程来处理请求不同，从而实现了 Nginx 的高并发，关于这一块不属于本篇教程的讨论范畴，后面我们介绍高性能 Nginx 系列时会详细讨论，这里我们只需要了解 master 进程和 worker 进程各自的分工即可，我们可以通过 `ps -ef | grep nginx` 查看 Nginx 服务运行状态：

![img](/assets/post/9f98838b28cc33fa1545eb2003faaac0982595f1490a117c592fcfa935f11cfc.png)

当我们在客户端浏览器输入应用 URL 进行访问时，在发送请求报文前，会先通过 DNS 查询域名对应的服务器 IP 地址（如果在本地 /etc/hosts 文件有定义，会直接从这里返回 IP 地址，不走 DNS 服务），对于 HTTP 应用来说，默认端口号是 80，有了对方的 IP 地址和端口号，就可以通过三次握手建立与对端 Web 服务器应用的 TCP 连接了，这个对端 Web 服务器应用正是 Nginx，Nginx 的 master 进程在接收客户端连接信号后会将这个网络事件发送给某个 worker 进程，由该 worker 进程来接管后续的连接建立和请求处理，经过这一步，就建立起了 Nginx 服务器与本地客户端的连接。

关于 Nginx 默认监听端口，也可以通过应用对应的 Nginx 虚拟主机配置文件进行修改，如果配置为其它端口号，需要在客户端访问该应用的时候手动指定，这样对用户来说不太方便，所以一般都使用默认值：

```
listen 80;
```

​    

> PS：如果是 HTTPS 连接，默认端口号是 443。

**接收请求**

Nginx 的 worker 进程在与客户端建立 HTTP 连接之后（这一步对应 Socket 编程中的 `accept` 操作），就开始从这条连接上读取请求报文数据（对应 Socket 编程中的 `read` 操作）并进行解析，Nginx 会遵循 HTTP 协议对起始行、报文首部及报文主体进行进行解析，并获取请求方法、请求 URL、请求参数、HTTP 协议版本等信息，然后将解析出来的请求数据保存到 Nginx 对应的数据结构 `ngx_http_request_s` 中（感兴趣的可以看下 Nginx 底层源码）。

在解析过程中，如果发现请求方法或请求首部字段不合法，则直接返回错误响应，比如不支持对应的请求方法，返回 405 Method Not Allowed 响应，对于 HTTP/1.1 而言，如果请求首部不包含 `Host` 字段，则返回 400 Bad Request 响应，请求超时也是在这一阶段检查的。

**处理请求**

解析出 HTTP 请求报文数据并且校验数据合法后，接下来，Nginx 开始对请求进行处理。

Nginx 是由模块驱动的，所以会通过配置文件中定义的 http 主模块及里面包含的 server 子模块以及更细粒度的 location 子模块依次对 HTTP 请求进行处理。这里我们重点看部署应用对应的虚拟主机配置文件中的 `server` 和 `location` 模块：

```
    server {
    
        listen 80;    // IPv4
        listen [::]:80;  // IPv6
    
        server_name laravel.test;
        root /var/www/laravel/public;
        index index.php index.html index.htm;
    
        location / {
             try_files $uri $uri/ /index.php$is_args$args;
        }
    
        location ~ \.php$ {
            try_files $uri /index.php =404;
            fastcgi_pass unix:/run/php/php7.1-fpm.sock;
            fastcgi_index index.php;
            fastcgi_buffers 16 16k;
            fastcgi_buffer_size 32k;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
            #fixes timeouts
            fastcgi_read_timeout 600;
            include fastcgi_params;
        }
    
        location ~ /\.ht {
            deny all;
        }
    
        error_log /var/log/nginx/laravel_error.log;
        access_log /var/log/nginx/laravel_access.log;
    }
```

​    

Nginx 能映射到对应的虚拟主机配置文件，主要依靠 Nginx 将从请求首部解析出来的 Host 字段值与所有虚拟主机配置文件中的 `server_name` 配置项做对比。

通过 root 配置项可以获取服务器上应用部署的本地根目录路径。在通过 URL 访问指定资源时，为了安全起见，我们并不会在请求中显式指定服务器资源的绝对路径，而是仅仅指定资源的相对 URL 路径，再与服务器上的这个 root 配置值拼接成对应资源的绝对路径，如果访问的是静态资源，比如 `http://laravel58.test/favicon.ico`，则 Nginx 会通过 `location / {...}` 中的配置直接从 `/var/www/laravel/public/favicon.ico` 获取对应资源，如果对应资源存在，则返回 200 OK 响应，否则返回 404 响应。因此，通过 root 配置可以建立起资源 Web URL 与资源本地绝对路径的映射，从而将 Web 请求转化为本地文件资源的访问。

如果用户请求的是非静态资源，比如 `http://laravel58.test`，通过 `location / {...}` 配置，Nginx 首先将请求 URL 转化为 `http://laravel58.test/` 并再次发起请求（对用户而言是透明无感知的），这一次，`index` 配置生效，Nginx 会尝试通过 `/var/www/laravel/public/index.php` 匹配资源是否存在，资源存在则将访问该资源，由于该资源以 `.php` 结尾，所以 `location ~ \.php$ {...}` 配置生效，Nginx 会继续通过该配置对进行处理。这里面的配置其实是个反向代理配置，Nginx 本身的高性能高并发设计更适用于作为静态资源服务器，对应 PHP 脚本文件这种动态资源请求，Nginx 的处理方式是通过反向代理的方式将其转发给真正的 PHP 脚本处理进程，通常是 PHP-FPM：

![img](/assets/post/bbfb131a35a8d110feab355cea8c0f38c33d7d884f2e6850f5841fe72d04816c.png)

**访问资源**

在请求处理中我们已经介绍了资源的访问方式，如果是静态资源，如 HTML、图片、CSS、JS 文件，Nginx 作为静态资源服务器可以直接通过 URL 路径与本地文件目录映射直接从文件系统返回对应的静态文件，如果是动态资源，比如 PHP 脚本，Nginx 作为反向代理服务器会将请求转发给相应的 CGI 后台进程，在 PHP 中，一般是 PHP-FPM（PHP FastCGI 进程管理器），然后再由 PHP-FPM 将请求转交给对应的 PHP 脚本，并由 PHP 解释器来执行相应的代码，在 Laravel 框架中，对应的处理流程就是从入口文件 `public/index.php` 开始，经过应用初始化->中间件过滤->路由匹配->请求验证->业务逻辑执行->返回响应这一系列步骤最终将处理结果即最终获取到的动态资源返回给 Nginx，对于 API 接口请求来说，一般返回的资源是 JSON 格式数据，对于 Web 浏览器请求来说，通常返回的是动态构建的 HTML 文档。

另外，为了加速用户对静态资源访问的速度，现在很多中大型网站会普遍使用 CDN 技术让终端用户就近从附近运营商数据中心获取缓存的静态资源，对于这部分资源访问，不在自己服务器部署的 Nginx 服务器管控范围之内，后面学院君在讲 CDN 技术时再单独介绍。

**构建&发送响应**

这里的构建响应指的是 Nginx 服务器构建响应，获取到 URL 指定的资源之后，Nginx 就可以开始准备构建返回给客户端的响应了。

Nginx 通过 `ngx_http_send_header` 方法构造 HTTP 响应的起始行、响应首部，并将响应头信息保存在 `ngx_http_request_s` 的 `headers_out` 数据结构中，然后通过 `ngx_http_header_filter` 方法按照 HTTP 规范将其序列化为字节流缓冲区，最后通过 `ngx_http_write_filter` 方法将响应头部发送出去。

> PS：我们在 PHP 代码中通过 header、set_cookie 等网络函数设置的响应头也会通过 PHP-FPM 发送给 Nginx。

HTTP 响应实体保存在 `ngx_http_request_s` 的 `out` 链表中（对于响应头部过大无法一次性发送完的响应，也会将剩余的响应头部放到 out 链表），经由 `ngx_http_out_filter` 过滤处理之后，最后也是通过 `ngx_http_write_filter` 方法将响应实体发送出去（对应 Socket 编程中的 `write` 操作）。

当然在此过程中，Nginx 底层做了大量底层网路操作的封装，比如校验缓冲区、控制发送速度、限制发送数据大小等，以后我们在 Nginx 系列会详细介绍底层的实现细节。