---
title: 计算机网络协议系列 -  HTTP 协议篇：Nginx 配置文件和虚拟主机介绍

layout: post

category: blog

tags: |-

  PHP

  计算机网络协议系列
  
  HTTP 协议篇

  Nginx
---





# 计算机网络协议系列（四十三） 



以一个简单的 Laravel 项目为例详细介绍服务器端如何基于 Nginx + PHP-FPM 驱动 PHP Web 应用。

在开始介绍 Nginx 处理客户端请求之前，我们先简单介绍下 Nginx 服务器的配置文件。为此需要准备好 Laravel 项目服务端运行环境（Nignx + PHP-FPM + Laravel），我们可以基于 Homestead、Laradock、Laragon 或 Valet 快速搭建起这样的环境。

做好上述准备工作后，打开 Nginx 的配置文件 `nginx.conf`（通常位于 `/etc/nginx/nginx.conf`）：

​    user vagrant;

​    worker_processes auto;

​    pid /run/nginx.pid;

​    include /etc/nginx/modules-enabled/*.conf;

​    

​    events {

​            worker_connections 768;

​            # multi_accept on;

​    }

​    

​    http {

​    

​            ##

​            # Basic Settings

​            ##

​    

​            sendfile on;

​            tcp_nopush on;

​            tcp_nodelay on;

​            keepalive_timeout 65;

​            types_hash_max_size 2048;

​            # server_tokens off;

​    

​            server_names_hash_bucket_size 64;

​            # server_name_in_redirect off;

​    

​            include /etc/nginx/mime.types;

​            default_type application/octet-stream;

​    

​            ##

​            # SSL Settings

​            ##

​    

​            ssl_protocols TLSv1 TLSv1.1 TLSv1.2; # Dropping SSLv3, ref: POODLE

​            ssl_prefer_server_ciphers on;

​    

​            ##

​            # Logging Settings

​            ##

​    

​            access_log /var/log/nginx/access.log;

​            error_log /var/log/nginx/error.log;

​    

​            ##

​            # Gzip Settings

​            ##

​    

​            gzip on;

​    

​            ##

​            # Virtual Host Configs

​            ##

​    

​            include /etc/nginx/conf.d/*.conf;

​            include /etc/nginx/sites-enabled/*;

​    }

​    

该配置文件中提供了 Nginx 服务器的一些基本配置，Nginx 是由模块驱动的，负责 HTTP 服务的是 `http` 模块，这里我们重点关注 `http` 模块中的虚拟主机配置（Virtual Host Configs）。

如果一台服务器上只能部署一个 Web 站点显然有点浪费，所以 HTTP/1.1 规范允许在一台 HTTP 服务器上搭建多个 Web 站点，这个功能叫做虚拟主机（Virtual Host）。所谓虚拟主机的意思是物理层面只有一台服务器，但是通过虚拟主机功能可以在该服务器上搭建多个站点，从而让访问者觉得配备了多台服务器。

基于 Nginx 驱动的所有 Web 站点都是通过 `server` 模块以虚拟主机的方式配置在各自的配置文件中，然后在 `nginx.conf` 中通过 `include /etc/nginx/sites-enabled/*;` 这行代码引入。我们看下 Nginx 自带的一个虚拟主机配置 `/etc/nginx/sites-enabled/default`：

​    server {

​            listen 80 default_server;

​            listen [::]:80 default_server ipv6only=on;

​    

​            root /usr/share/nginx/html;

​            index index.html index.htm;

​    

​            # Make site accessible from http://localhost/

​            server_name localhost;

​    

​            location / {

​                    # First attempt to serve request as file, then

​                    # as directory, then fall back to displaying a 404.

​                    try_files $uri $uri/ =404;

​                    # Uncomment to enable naxsi on this location

​                    # include /etc/nginx/naxsi.rules

​            }    

​    }

​    

如果 Nginx 服务器没有配置其它站点，则访问 IP 地址解析到该服务器上的所有域名都会指向这个配置文件，因为这个配置文件监听端口上指定了 `default_server`：

​    listen 80 default_server;

​    

由于是默认虚拟主机配置，所以一个 Nginx 服务器只允许配置一个标识为 `default_server` 的虚拟主机。如果配置了多个，启动 Nginx 的时候会报错。

对于我们测试的 Laravel 项目，可以为其配置一个独立的虚拟主机配置 `/etc/nginx/sites-enabled/laravel`：

​    server {

​    

​        listen 80;    // IPv4

​        listen [::]:80;  // IPv6

​    

​        server_name laravel.test;

​        root /var/www/laravel/public;

​        index index.php index.html index.htm;

​    

​        location / {

​             try_files $uri $uri/ /index.php$is_args$args;

​        }

​    

​        location ~ \.php$ {

​            try_files $uri /index.php =404;

​            fastcgi_pass unix:/run/php/php7.1-fpm.sock;

​            fastcgi_index index.php;

​            fastcgi_buffers 16 16k;

​            fastcgi_buffer_size 32k;

​            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;

​            #fixes timeouts

​            fastcgi_read_timeout 600;

​            include fastcgi_params;

​        }

​    

​        location ~ /\.ht {

​            deny all;

​        }

​    

​        error_log /var/log/nginx/laravel_error.log;

​        access_log /var/log/nginx/laravel_access.log;

​    }

Nginx 服务器支持几个 Web 站点，就配置几个虚拟主机，通常的做法是将虚拟主机配置到 `/etc/nginx/sites-available` 目录下，然后对于启用的站点，在 `/etc/nginx/sites-enabled` 目录下创建对应的软链接。

在这个基本的 Laravel 站点虚拟主机配置中，主要包含监听端口、站点域名、项目根目录、默认索引、日志信息、以及 `location` 配置块，我们大致介绍下这几个配置的含义及用途：

\+ 监听端口（listen）：本站点监听的端口，一般默认是 80；

\+ 站点域名（server_name）：本站点域名，由于一台服务器上搭建了多个站点，而 TCP 连接的标识中只有 IP 地址和端口号，服务器如何识别客户端访问的是哪个站点呢？HTTP/1.1 的做法是要求请求首部中必须包含 Host 字段来指定访问的域名，Nginx 在接收请求时，会将解析出来的 Host 首部字段值与虚拟主机中的 server_name 值进行匹配，匹配成功则应用该虚拟主机中的配置；

\+ 项目根目录（root）：站点部署的目录，一般是入口索引文件所在的目录；

\+ 索引文件：请求 URL 中未指定具体资源时默认的入口文件，可配置多个，然后以空格分隔。比如访问 Laravel 应用首页，一般请求起始行中的 URL 路径是 `/`，这个时候 Nginx 就会依次拼接 `index` 配置中的索引文件进行访问，比如 `/index.php`；

\+ `location` 配置块：会与请求起始行中的相对 URL 路径进行匹配，匹配成功则应用对应配置块中的配置，`location / {...}` 可以匹配所有请求，`try_files` 会依次访问后面配置的每个路径，如果通过对应 URL 可以直接访问（`$uri`），比如静态资源文件，则直接返回响应给客户端；否则尝试以目录方式访问（`$uri/`）；最后尝试访问 `/index.php$is_args$args`，即以 Laravel 入口文件 + 动态参数形式访问资源，由于该路径包含了 `.php`，所以会进入下一个匹配的 `location` 配置块 —— `location ~ \.php$ {...}`，然后通过 FastCGI 网关（PHP-FPM）让后端 PHP 程序来处理动态请求。指定 PHP-FPM 进程时，可以通过 Unix 套接字，比如 `unix:/run/php/php7.1-fpm.sock`，也可以通过 IP 地址+端口号的形式，比如 `http://127.0.0.1:9000`，前者仅适用于 PHP-FPM 与 Nginx 运行在一台服务器，后者适用于所有场景，不过前者直接读取本地文件，没有额外的网络开销，因此从性能上来说更优，然后我们将请求的路径、参数传递给 PHP-FPM，同时设置缓存和超时配置；

\+ 日志信息：可以通过 `error_log` 指定访问日志路径，`access_log` 指定错误日志路径。

新增虚拟主机配置后，需要重启 Nginx 让其生效（Nginx 启动过程中加载 `nginx.conf` 配置文件），有了以上基本知识储备后，下一篇我们将给大家介绍 Nginx + PHP-FPM 驱动 Laravel Web 应用的完整流程。