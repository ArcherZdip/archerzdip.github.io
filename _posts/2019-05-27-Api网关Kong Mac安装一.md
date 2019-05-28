---
title: Api网关Kong Mac安装一
layout: post
category: blog
tags: |-
  API网关
  Kong
---



# Api网关Kong Mac安装一

## Kong 安装

1. **Install Kong**

   Use the [Homebrew](https://brew.sh/) package manager to add Kong as a tap and install it:

   ```
    $ brew tap kong/kong
    $ brew install kong
   ```

2. **Add kong.conf**

   **Note**: This step is **required** if you are using Cassandra; it is **optional** for Postgres users.

   By default, Kong is configured to communicate with a local Postgres instance. If you are using Cassandra, or need to modify any settings, download the [`kong.conf.default`](https://raw.githubusercontent.com/Kong/kong/master/kong.conf.default) file and [adjust](https://docs.konghq.com/1.1.x/configuration#database) it as necessary. Then, as root, add it to `/etc`:

   ```
    $ sudo mkdir -p /etc/kong
    $ sudo cp kong.conf.default /etc/kong/kong.conf
   ```

3. **Prepare your database**

   [Configure](https://docs.konghq.com/1.1.x/configuration#database) Kong so it can connect to your database. Kong supports both [PostgreSQL 9.5+](http://www.postgresql.org/) and [Cassandra 3.x.x](http://cassandra.apache.org/) as its datastore.

   If you are using Postgres, provision a database and a user before starting Kong:

   ```sql
    CREATE USER kong; 
    CREATE DATABASE kong OWNER kong;
   ```

   Next, run the Kong migrations:

   ```
    $ kong migrations bootstrap [-c /path/to/kong.conf]
   ```

   **Note for Kong < 0.15**: with Kong versions below 0.15 (up to 0.14), use the `up` sub-command instead of `bootstrap`. Also note that with Kong < 0.15, migrations should never be run concurrently; only one Kong node should be performing migrations at a time. This limitation is lifted for Kong 0.15, 1.0, and above.

4. **Start Kong**

   ```
    $ kong start [-c /path/to/kong.conf]
   ```

5. **Use Kong**

   Verify that Kong is running:

   ```
    $ curl -i http://localhost:8001/
   ```

   Quickly learn how to use Kong with the [5-minute Quickstart](https://docs.konghq.com/latest/getting-started/quickstart).



### Q&A

- Q1

```bash
2019/05/27 16:29:42 [warn] ulimit is currently set to "256". For better performance set it to at least "4096" using "ulimit -n"
Error: /usr/local/share/lua/5.1/kong/cmd/start.lua:37: could not find OpenResty 'nginx' executable. Kong requires version 1.13.6.2
```

A:

```bash
Install OpenResty via Homebrew: brew install openresty/brew/openresty
Create folder /usr/local/openresty
Copy contents from /usr/local/Cellar/openresty/1.13.6.2 into it
Check for /usr/local/openresty/nginx/sbin/nginx presence
```





## 安装**kong dashboard**

安装步骤，请参考git 地址：<https://github.com/PGBI/kong-dashboard>



### Q&A

- Q1

  ```bash
  npm install -g kong-dashboard
  # 执行报出一下错误时
  dyld: Library not loaded: /usr/local/opt/icu4c/lib/libicui18n.63.dylib
    Referenced from: /usr/local/bin/node
    Reason: image not found
  [1]    79843 abort      npm install -g kong-dashboard
  ```

  A:

  ```bash
  brew uninstall node icu4c
  brew install node
  # brew uninstall --ignore-dependencies node icu4c
  ```





**最后建议还是使用docker，这样保证系统隔离，毕竟也是测试kong**



## 参考地址：

1. <https://blog.csdn.net/li396864285/article/details/77371466>
2. <https://blog.csdn.net/CGD_ProgramLife/article/details/80510163>
3. <https://stackoverflow.com/questions/54604121/dyld-library-not-loaded-usr-local-opt-icu4c-lib-libicui18n-63-dylib-in-vscode>
4. postgresql使用 <https://www.jianshu.com/p/9e91aa8782da>