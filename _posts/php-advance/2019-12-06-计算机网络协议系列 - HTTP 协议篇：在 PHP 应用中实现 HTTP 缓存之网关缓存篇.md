---
title: 计算机网络协议系列 -   HTTP 协议篇：在 PHP 应用中实现 HTTP 缓存之网关缓存篇

layout: post

category: blog

tags: |-

  PHP

  计算机网络协议系列
  
  HTTP 协议篇

  网关缓存
---



# 计算机网络协议系列（四十八）

上篇分享中，给大家介绍了如何通过响应头设置浏览器缓存来实现 HTTP 缓存，今天我们还是在 Laravel 项目中基于网关缓存来实现 HTTP 缓存，比起浏览器缓存，网关缓存更易于通过服务端代码进行维护和控制，同时还可以被多个客户端共享，所以更推荐在实际项目中以这种方式实现 HTTP 缓存。

**原理概述**

这里要介绍的网关缓存主要就是反向代理缓存，常见的反向代理服务器有 Nginx、Varnish、Squid 等，但是这里为了简化模型，将使用 Symfony 框架提供的 HTTP Cache 功能来做演示，Laravel 框架底层的 HTTP 模块是基于 Symfony 的，所以我们很容易在 Laravel 框架中基于 Symfony 的 HTTPCache 模块来实现 HTTP 缓存，更加方便的是，还有一个现成的 Laravel HTTP Cache 扩展包 [barryvdh/laravel-httpcache](https://github.com/barryvdh/laravel-httpcache) 对 Symfony 的 HTTP 缓存功能进行了封装，以便我们在 Laravel 项目中快速接入以实现 HTTP 缓存。

有关 Symfony 的 HTTP Cache 功能可以参考 [Symfony 文档](https://symfony.com/doc/current/http_cache.html)，这里我们将重点放在基于 laravel-httpcache 扩展包在 Laravel 项目中演示基于网关缓存实现 HTTP 缓存上。

**安装扩展包**

首先，我们通过 Composer 在 Laravel 项目根目录下安装这个扩展包：

```
    composer require barryvdh/laravel-httpcache 
```

​    

然后，在 `app/Http/Kernel.php` 的 `web` 中间件组中添加一个中间件：

```
    \Barryvdh\HttpCache\Middleware\CacheRequests::class,
```

​    

这样，我们就可以在 `routes/web.php` 定义的路由中应用 HTTP 网关缓存了，之所以叫做网关缓存，是因为这个缓存存放在服务器网关而非客户端浏览器或中间代理中，这里的网关就是 Symfony 底层基于 PHP 实现的简单反向代理服务器了（工业级反向代理缓存服务器还是使用 Varnish 或 Squid）。

**Expires**

就是这么简单，接下来我们可以编写路由来演示基于底层 Symfony 网关实现的 HTTP 缓存了，首先我们来看基于 Expires 响应头的缓存：

```php
    Route::get('expires', function () {
        return response('Test Expires Header')
            ->setPublic()
            ->setExpires(new DateTime(date(DATE_RFC7231, time() + 3600)));
    });
```

由于缓存要存放在服务器网关中，所以 Cache-Control 响应头中需要设置 public 属性（默认是 private），我们可以通过 setPublic 方法来实现这一目的，然后通过 setExpires 方法设置缓存过期时间，这样，在浏览器访问该路由，首次访问的时候，会从服务器读取最新资源数据，同时 Symfony 网关会设置相应的响应头 X-Symfony-Cache: miss,store，表示缓存未命中，已存储：

![img](/assets/post/4e0554ae38ab0dd1328ac14e5095c63c768367b3ecb5254479e262794edd60cf.png)

缓存记录默认存储在 Laravel 项目的 storage/httpcache 目录下，再次访问该路由，就会从网关缓存获取资源了，这可以通过 X-Symfony-Cache: fresh 响应头得知：

![img](/assets/post/66eec071380b3b72274d9e8bda1b22384aef1e7a8d49b19fe8aa1def79a1770f.png)

**Cache-Control**

接下来，我们来看下基于 Cache-Control 响应头实现的网关缓存，相应的路由定义如下：

```php
    Route::get('cache_control', function () {
        return response('Test Cache-Control Header')->setTtl(3600);
    });
```

​    

max-age 用于设置本地缓存有效期，如果是代理缓存或网关缓存，则需要通过 s-maxage 属性来设置，所以在上述代码中我们使用了 setTtl 方法而不是 setClientTtl，该方法会同时设置 Cache-Control 响应头的 s-maxage 以及 public 属性，这样，我们在浏览器中访问该路由，首次访问当然还是不会命中，但缓存会被存储到网关：

![img](/assets/post/43c71580bd99af62249713167771dd3ef7f9a76abd7a882860e44a79a27e71f6.png)

再次访问，就可以从缓存获取资源了：

![img](/assets/post/0b1e4ea789eba7aab8c0912546d3aecd24cd3c7bd7b285c1329ea457656983aa.png)

**If-Modified-Since/Last-Modified**

下面我们再以 If-Modified-Since/Last-Modified 为例演示一个对比缓存的例子，编写路由定义如下：

```php
    Route::get('no_cache', function () {
        $response = response('Test If-Modified-Since/Last-Modified Http Cache');
        $response->headers->addCacheControlDirective('no-cache', true);
        $response->setTtl(3600);
        $response->setLastModified(new DateTime(date(DATE_RFC7231)));
        return $response;
    });
```

​    

我们设置响应头 Cache-Control 字段值为：no-cache,public,s-maxage=3600，表示需要进行缓存新鲜度检测，如果缓存未过期，则返回 304 响应，否则返回最新资源，同样首次访问该路由的时候缓存未命中，但会保存下来：

![img](/assets/post/d85ebfb34bd408f52bb12f17f6eb3a33d1c67fd9098392b1df03fdc82e32cefb.png)

再次访问，则返回 304 响应，然后从网关缓存获取缓存副本作为响应实体返回给客户端：

![img](/assets/post/1cfb2b9d58ae007cda5119d4f539d3c44fb2c39d7e437ed21ed783d1086f12ef.png)

If-None-Match/Etag 实现思路也是类似，这里不再单独演示了。

以上就是 Laravel 项目中实现通过浏览器缓存和网关缓存实现 HTTP 缓存的大致思路，有了 HTTP 缓存，就可以降低服务器负载和网络带宽，从而提高服务器性能，加快用户访问页面速度，在实际项目中，你可以将 Symfony 网关替换成 Varnish 之类的工业级软件，效果会更好，在 Laravel 中使用 Varnish 可以使用 [spatie/laravel-varnish](https://github.com/spatie/laravel-varnish) 这个扩展包。另外，需要注意的是，以上 HTTP 缓存都会存储完整的响应实体，即整个页面，所以 HTTP 缓存多适用于静态页面或文件的存储，如果你想要缓存数据片段的话，则更适合通过 Memcached 或 Redis 之类的缓存方案来解决。