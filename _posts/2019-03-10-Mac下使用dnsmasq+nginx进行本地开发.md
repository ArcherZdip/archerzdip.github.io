---
title: Mac下使用dnsmasq+nginx进行本地开发
layout: post
category: blog
tags: |-
  nginx
  dnsmasq
---
<!-- TOC -->

- [1. 前言](#1-前言)
- [2. 安装dnsmasq](#2-安装dnsmasq)
- [3. 配置dnsmasq](#3-配置dnsmasq)
- [4. 配置macOS](#4-配置macos)
- [5. 测试](#5-测试)
- [6. nginx](#6-nginx)

<!-- /TOC -->
# 1. 前言
最近刚刚开始使用Mac进行开发，有点不熟练，借着部署环境分享下使用Dnsmasq进行域名管理。大多数web开发者可能都是使用hosts 或者 端口号进行项目访问，这就有以下问题：
1. 需要你在添加项目或者删除项目时每次对配置文件进行修改
2. 需要管理员权限

DNSmasq是一个小巧且方便地用于配置DNS和DHCP的工具，适用于小型网络，它提供了DNS功能和可选择的DHCP功能。它服务那些只在本地适用的域名，这些域名是不会在全球的DNS服务器中出现的。DHCP服务器和DNS服务器结合，并且允许DHCP分配的地址能在DNS中正常解析，而这些DHCP分配的地址和相关命令可以配置到每台主机中，也可以配置到一台核心设备中（比如路由器），DNSmasq支持静态和动态两种DHCP配置方式。（来自百度百科）

# 2. 安装dnsmasq
我的开发环境是PHP7.2 mysql5.7 nginx， 这些基础环境的部署这里就不进行说明了。
Mac下的dnsmasq还是很有很多安装方式的，因为刚刚接触我目前熟练的只有[HomeBrew](https://brew.sh/)。
安装方法：
```javascript
/usr/bin/ruby -e "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/master/install)"
```

```
#安装
brew install dnsmasq
```
建议使用brew services管理服务
官网：https://github.com/Homebrew/homebrew-services

# 3. 配置dnsmasq

使用brew安装的软件默认路径在/etc/local 目录下，其中配置文件在/etc/local/etc目录中：
![在这里插入图片描述](https://img-blog.csdnimg.cn/20190110120540296.png)

Dnsmasq 可以做的很多事情之一是将 DNS 请求与模式数据库进行比较，并以此来确定正确的应答。我使用这个功能来匹配以 .devel 结尾的任何请求，并发送 <kbd>127.0.0.1</kbd> 作为应答。Dnsmasq 配置指令非常容易,打开dnsmasq.conf配置，修改：
```
address=/devel/127.0.0.1
```
详细配置可参考：https://cloud.tencent.com/developer/article/1174717
启动dnsmasq
```
brew services start dnsmasq
```
可查看服务：
![在这里插入图片描述](https://img-blog.csdnimg.cn/20190110121308943.png)

# 4. 配置macOS
现在你已经有了一个可以工作的 DNS 服务器，你可以在自己的操作系统上配置来使用它。有使用两种方法：

1. 发送所有 DNS 请求到 Dnsmasq
2. 只发送 .devel 的请求到 Dnsmasq

第一种方法非常简单，只要在系统偏好中改变你的 DNS 设置——但是可能在 Dnsmasq 配置文件不添加额外的修改的时候并不会生效。

第二种方法显得有点微妙，但并没有非常。大多数类 Unix 的操作系统有叫做 /etc/resolv.conf 的配置文件，用以控制 DNS 查询的执行方式，包括用于 DNS 查询的默认服务器（这是连接到网络或者在系统偏好中修改 DNS 服务器时自动设置的）。

macOS 也允许你通过在 /etc/resolver 文件夹中创建新的配置文件来配置额外的解析器。这个目录可能还不存在于你的系统中，所以你的第一步应该是创建它：

```
sudo mkdir /etc/resolver
```
在此目录创建devel文件，并写人<kbd>nameserver 127.0.0.1</kbd>
![在这里插入图片描述](https://img-blog.csdnimg.cn/20190110121843213.png)
在这里，<kbd>devel </kbd>是我配置 Dnsmasq 来响应的顶级域名，<kbd>127.0.0.1 </kbd>是要使用的服务器的 IP 地址。

一旦你创建了这个文件，macOS 将会自动读取并完成。
ps: 目前现在只发现配置在<kbd>/etc/resolver</kbd>下可以，没搞懂配置在<kbd>/etc/resolv.conf</kbd>为什么没生效？

# 5. 测试
至此，你ping任何以.devel结尾的域名就会解析到本地，无论地址是否存在：
![在这里插入图片描述](https://img-blog.csdnimg.cn/20190110122347860.png?x-oss-process=image/watermark,type_ZmFuZ3poZW5naGVpdGk,shadow_10,text_aHR0cHM6Ly9ibG9nLmNzZG4ubmV0L3pkaXAxMjM=,size_16,color_FFFFFF,t_70)

# 6. nginx
当然直到目前为止感觉没有太大作用，dnsmasq只是作为dns解析，其他就需要借助nginx来完成了。
nginx.conf配置
```
#user  nobody;
worker_processes  1;  

#error_log  logs/error.log;
#error_log  logs/error.log  notice;
#error_log  logs/error.log  info;

#pid        logs/nginx.pid;


events {
    worker_connections  1024;
}


http {
    include       mime.types;
    default_type  application/octet-stream;

    #log_format  main  '$remote_addr - $remote_user [$time_local] "$request" '
    #                  '$status $body_bytes_sent "$http_referer" '
    #                  '"$http_user_agent" "$http_x_forwarded_for"';

    #access_log  logs/access.log  main;

    sendfile        on; 
    #tcp_nopush     on;

    #keepalive_timeout  0;
    keepalive_timeout  65; 

    #gzip  on;

    include servers/*;
}
```
servers/dynamic.conf配置
```
server {
    set $basepath "/Users/dev/Sites"; #网站目录
    set $rootpath ""; 
    set $domain $host;
    set $servername "localhost";

    ## check one name domain for simple application
    if ($domain ~ "^(.[^.]*)\.(devel)$") {  #一级域名解析
        set $domain $1; 
        set $cname $2; 
        set $rootpath "${domain}";
        set $servername "${domain}.${cname}";
    }  

    ##二级域名解析  略

    listen 80; 
    server_name $servername;
    root $basepath/$rootpath;
    index  index.html index.htm index.php;

    access_log "/usr/local/var/log/nginx/access-${servername}.log";
    error_log  "/usr/local/var/log/nginx/error-${servername}.log";

    include cors;

    location / { 
        try_files $uri $uri/ /index.html /index.php?$args;
        autoindex on; 
    }   

    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;

        fastcgi_pass   127.0.0.1:9000;
        fastcgi_index  index.php;
        fastcgi_param  SCRIPT_FILENAME    $document_root$fastcgi_script_name;
        fastcgi_buffers 4 64k;
        fastcgi_buffer_size 64k;
        fastcgi_connect_timeout 300s;
        fastcgi_read_timeout 300s;
        include fastcgi_params;
    }   

    location = /favicon.ico {
        try_files $uri =204;
        log_not_found off;
        access_log off;
    }   

    location = /robots.txt {
        allow all;
        log_not_found off;
        access_log off;
    }   

    location ~ /\.(ht|git) {
        deny all;
    }   
}
```
至此你只需在你的网站根目录建立项目文件夹，如demo，然后在浏览器上访问<kbd>http://demo.devel </kbd> 即可访问该项目。 
![在这里插入图片描述](https://img-blog.csdnimg.cn/20190110123551962.png?x-oss-process=image/watermark,type_ZmFuZ3poZW5naGVpdGk,shadow_10,text_aHR0cHM6Ly9ibG9nLmNzZG4ubmV0L3pkaXAxMjM=,size_16,color_FFFFFF,t_70)
![在这里插入图片描述](https://img-blog.csdnimg.cn/20190110123620397.png?x-oss-process=image/watermark,type_ZmFuZ3poZW5naGVpdGk,shadow_10,text_aHR0cHM6Ly9ibG9nLmNzZG4ubmV0L3pkaXAxMjM=,size_16,color_FFFFFF,t_70)

![在这里插入图片描述](https://img-blog.csdnimg.cn/20190110123735609.png?x-oss-process=image/watermark,type_ZmFuZ3poZW5naGVpdGk,shadow_10,text_aHR0cHM6Ly9ibG9nLmNzZG4ubmV0L3pkaXAxMjM=,size_16,color_FFFFFF,t_70)

ps: 
1. 因为顶级域名devel 不存在，所以访问记得加上http:// 。
2. 使用brew启动dnsmasq时，一定要使用root权限启动

pps： 第一次写文章，写的很烂，见谅！！！