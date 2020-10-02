---
title: Openrestry配合Redis实现简单灰度发布系统
layout: post
category: blog
tags: |-
  Openrestry
  redis
  灰度
---

# Openrestry配合Redis实现简单灰度发布系统


## 安装前的准备
```bash
sudo yum install -y pcre-devel openssl-devel gcc curl
```

## 构建 OpenResty
`CentOS 8 或以上版本，应将下面的 yum 都替换成 dnf`  
参考：http://openresty.org/cn/linux-packages.html

```bash
# add the yum repo:
wget https://openresty.org/package/centos/openresty.repo
sudo mv openresty.repo /etc/yum.repos.d/

# update the yum index:
sudo yum check-update

# 安装软件 openresty 
sudo yum install -y openresty

# 安装命令行工具 resty
sudo yum install -y openresty-resty

# 列出所有 openresty 仓库里头的软件包
sudo yum --disablerepo="*" --enablerepo="openresty" list available

```

## 启动
```bash
## 设置开机启动
sudo systemctl enable openresty

## 启动openrestry
sudo systemctl start openresty

```

## 配置openresty-nginx配置文件

配置一个demo.com域名的配置文件，并指定两个upstream，分别为9511和9521.

```bash

upstream demo.com {
    server 127.0.0.1:9511;
}

## gray
upstream demo-gray.com {
    server 127.0.0.1:9521;
}

server {
    server_name  demo.com;

    access_log /usr/local/openresty/nginx/logs/demo.com.access.log main if=$loggable;
    access_log /usr/local/openresty/nginx/logs/demo.com.access_trace.log logtrace if=$loggable;
    error_log /usr/local/openresty/nginx/logs/demo.com.error.log;
    client_max_body_size 10M;
    charset utf-8;

    location / {
        proxy_set_header Host $http_host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header  X-Forwarded-Proto $scheme;
        proxy_cookie_path / "/; secure; HttpOnly; SameSite=strict";
        proxy_ignore_client_abort on;
	    set $proxy_pass 'demo.com';
	    access_by_lua_file /usr/local/openresty/nginx/conf/conf.d/demo.grayscale.lua;
        proxy_pass http://$proxy_pass;
    }

    listen 80;
    listen [::]:80;
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    ssl_certificate /usr/local/openresty/nginx/conf/certs/demo.com.pem;
    ssl_certificate_key /usr/local/openresty/nginx/conf/certs/demo.com.key;
    include /usr/local/openresty/nginx/conf/certs/options-ssl-nginx.conf;

    include maintenance;
    include protected;

    error_page 404 /index.php;
}

```

请求 `127.0.0.1:9511`和请求`127.0.0.1:9521`结果：
```bash
% curl 127.0.0.1:9511
From production env%         
 
% curl 127.0.0.1:9521
From gray env.%
```

## grayscale.lua 代码

将员工工号写入Redis集合，使用sismember判断Redis中是否存在，存在则设置prixy_pass=灰度环境，否则则为正式环境。

```lua

local redis = require('resty.redis')
local red = redis:new()
red:connect("127.0.0.1",6379)
red:auth('password')
red:select(3)

-- 获取header里工号
local Workcode = ngx.req.get_headers()["X-Workcode"]
if (Workcode ~= nil)
then
    -- 灰度区间
    local isExist = red:sismember("attendance:gray_scale_workcodes", Workcode)
    if (isExist == 1)
    then
        ngx.var.proxy_pass = "demo-gray.com"
    end
end

```

## 写入Redis数据
```bash
127.0.0.1:6379[3]> sadd attendance:gray_scale_workcodes 123456
(integer) 1
```

## 验证
```bash
[20-10-02 15:47] ➜  archerzdip.github.io (master) ✗ curl -XGET -H "X-Workcode:123456" https://demo.com/
From gray env%  

[20-10-02 15:47] ➜  archerzdip.github.io (master) ✗ curl -XGET -H "X-Workcode:1" https://demo.com/
From production env%  

[20-10-02 15:47] ➜  archerzdip.github.io (master) ✗ curl -XGET https://demo.com/
From production env%
```

使用工号123456则访问的是灰度环境，其他的请求则访问的正式环境。

至此，大功告成

## 说明
Openresty真的太强大，还有很多功能等待去挖掘。

