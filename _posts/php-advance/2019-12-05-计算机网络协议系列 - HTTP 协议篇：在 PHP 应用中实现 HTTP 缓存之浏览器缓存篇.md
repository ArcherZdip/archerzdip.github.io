---
title: 计算机网络协议系列 -   HTTP 协议篇：在 PHP 应用中实现 HTTP 缓存之浏览器缓存篇

layout: post

category: blog

tags: |-

  PHP

  计算机网络协议系列
  
  HTTP 协议篇

  浏览器缓存
---



# 计算机网络协议系列（四十七）



在前两篇教程中给大家介绍了 HTTP 缓存的工作机制和实现原理，为了简化模型，更多是基于浏览器缓存来介绍的，但是在实际项目中，基于客户端浏览器的私有缓存并不是主流的实现方案，因为服务端页面更新后，往往需要用户主动刷新页面才能清空缓存，并不便于服务端去控制，所以针对 HTTP 缓存，使用网关缓存的实现更为主流，比如我们比较熟悉的 CDN 缓存、反向代理缓存都属于这一范畴，常见的反向代理服务器有 Nginx、Squid、Varnish 等，Nginx 更多用于高性能 Web 服务器，具备缓存静态资源的能力，Squid、Varnish 则多用于代理缓存服务器，用于实现 HTTP 静态缓存。我们这里强调「静态」是为了区别于 Memcached、Redis 之类的缓存服务器，后者更多用于存储从数据库获取的、动态变化的「动态」缓存。

下面我们以基于 Laravel 框架的 PHP 项目为例，简单演示下如何通过浏览器缓存和网关缓存实现 HTTP 缓存。

**浏览器缓存**

首先我们来看通过 Expires 响应头实现 HTTP 缓存，这很简单，只需要在返回的响应实例上设置额外的 Expires 头即可，我们设置资源过期时间为1小时后：

```php
    Route::get('expires', function () {
        return response('Test Expires Header')->setExpires(new DateTime(date(DATE_RFC7231, time() + 3600)));
    });
```

​    

在浏览器访问该路由，首次访问本地还没有缓存副本，会从服务器拉取资源并保存到本地，再次访问就可以通过浏览器缓存获取资源了：

![img](/assets/post/5595a5aaac15a140bf06f620d39037a676e5afaa1a97a87a362cddf2e233727a.png)

响应状态码仍然是 200，但是后面有一个提示，说明该资源是从本地缓存获取的。注意不要刷新页面，否则会在请求头中加上  Cache-Control: max-age=0，设置该请求头后，每次都会从服务器验证缓存是否已过期，只有在服务器返回 304 响应时才会应用缓存，否则会从服务器拉取最新资源。

类似的，我们还可以通过在响应头设置 Cache-Control 字段来实现浏览器缓存：

```php
    Route::get('cache_control', function () {
        return response('Test Cache-Control Header')->setClientTtl(3600);
    });
```

​    

这段代码会在响应头中设置 Cache-Control 的 max-age 属性值为 3600，表示缓存有效期为1个小时。同样，首次访问的时候，由于本地没有相应的缓存副本，会从服务器读取最新资源并保存到本地，第二次访问的时候，就会从缓存获取了：

![img](/assets/post/615c5c300494d78299a84ca440d48036bfa68f971cc4844fbe081476d531d851.png)

上述两种缓存策略都属于强制缓存，如果响应头 Cache-Control 中设置了 no-cache，则需要客户端发送相应的请求协商头（If-Modified-Since/If-None-Match），与服务端对应字段（Last-Modified/Etag）对比验证缓存是否过期来实现 HTTP 缓存，这种缓存策略我们称之为对比缓存。

我们以 If-Modified-Since/Last-Modified 为例来演示这种浏览器缓存的实现，首先我们在 Laravel 项目中定义相应的路由如下：

​    

```php
    Route::get('no_cache', function (\Illuminate\Http\Request $request) {
        $httpcache = false;
        $lastmodified = 'Thu, 09 May 2019 22:32:00 GMT';
        if ($request->hasHeader('If-Modified-Since')) {
            $time1 = new DateTime($request->header('If-Modified-Since'));
            $time2 = new DateTime($lastmodified);
            if ($time1->getTimestamp() >= $time2->getTimestamp()) {
                $httpcache = true;
            }
        }
        $response = response('');
        $response->headers->addCacheControlDirective('no-cache', true);
        $response->setClientTtl(3600);
        $response->setLastModified(new DateTime($lastmodified));
        if ($httpcache) {
            $response->setStatusCode(304);
            return $response;
        }
        $response->setContent('Test Cache-Control Header：no-cache');
        return $response;
    });
```

​    

我们需要设置响应头 Cache-Control 字段值为 no-cache,max-age=3600,private，同时还设置 Last-Modified 字段值为一个固定值，并且需要注意的是在对比缓存中，如果缓存有效，返回的响应状态码是 304，这一点和强制缓存不同，在浏览器中访问该路由，首次访问的时候会从服务器获取资源并缓存到本地，再次访问的时候，浏览器会自动加上 If-Modified-Since 请求头，如果缓存有效则返回 304 状态码，然后使用本地缓存作为响应实体在页面渲染：

![img](/assets/post/5394ac37de8d76c87865645ca72b55699778dea03df0018beebd5ae96138bbca.png)

下一篇我们将通过基于 Laravel 的一个 HTTP Cache 扩展包通过网关缓存来演示 Laravel 应用的 HTTP 缓存实现。