---
title: 微服务架构系列 - 框架篇：sidecar 模式在 Go Micro 中的应用（下）
layout: post
category: blog
tags: |-
  PHP
  微服务架构系列
  Go Micro
  sidecar
---



# 微服务架构系列（十六）



这篇主要通过示例代码的方式演示在 PHP 中基于 Micro Proxy 实现微服务的注册和引用，并使其能够与现有的其它语言实现的微服务（如 Go、Python、Java 等）可以相互通信。



**整体思路**



整体实现思路如下：



我将基于 PHP 的 Swoole 扩展来实现一个 HTTP 服务器提供对外的微服务接口，在 HTTP 服务器启动时，将该服务节点注册到 Go Micro 的注册中心（Consul），接下来，当用户通过 HTTP 发起远程服务请求时（通过 Micro Proxy），Micro Proxy 会将向注册中心查询对应的服务节点，然后将请求转发过来，基于 Swoole 实现的 HTTP 服务器会触发 `onRequest` 事件对应的回调函数的执行，根据请求参数将业务逻辑分发给对应的类去执行，最后再将处理结果返回给调用方。当 HTTP 服务器关闭时，会从注册中心删除对应的服务节点。



> 注：当然，这只是一个最基本的实现，主要用于演示 Micro Proxy 的应用场景，不要用于生产环境。



下面我们就来编写相应的 PHP 代码。



**代理类**



我在本地的 swoole 目录下创建了一个 microservice 子目录，用于存放本次开发编写的代码，首先创建一个 `proxy.php` 文件用于存放服务注册和模拟调用代码（基于 curl 扩展模拟网络请求）：



```php
    <?php
    
    class Proxy {
    
        const REGISTRY_URL = 'http://localhost:8500/v1/agent/service/register';
        const DEREGISTRY_URL = 'http://localhost:8500/v1/agent/service/deregister';
        const PROXY_URL = 'http://localhost:8081';  // Micro Proxy 
    
        protected $headers = [
            'Content-Type' => 'application/json; charset=utf-8'
        ];
    
        /**
         * 注册服务到注册中心
         * @param $service
         */
        public function register($service)
        {
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, self::REGISTRY_URL);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true );
            curl_setopt($curl, CURLOPT_HTTPHEADER, $this->headers);
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($service));
            curl_exec($curl);
            curl_close($curl);
        }
    
        /**
         * 从注册中心删除服务
         * @param $service
         */
        public function deregister($service)
        {
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, self::DEREGISTRY_URL . '/' . $service['id']);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true );
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
            curl_exec($curl);
            curl_close($curl);
        }
    
        /**
         * 模拟对微服务发起 HTTP 请求
         * @param $path
         * @param $params
         * @return mixed
         */
        public function httpCall($path, $params)
        {
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, self::PROXY_URL);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true );
            list($service, $endpoint) = explode('/', $path);
            $this->headers['Micro-Service'] = 'php.micro.srv.' . $service;
            $this->headers['Micro-Endpoint'] = $endpoint;
            curl_setopt($curl, CURLOPT_HTTPHEADER, $this->headers);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));
            $result = curl_exec($curl);
            curl_close($curl);
            return $result;
        }
    }
```



**业务类**



然后创建一个 `greeter.php` 文件用于存放问候服务类代码：



```php
    <?php
    class greeter {
        public function hello($name = '学院君') {
            return json_encode(['msg' => '你好, ' . $name]);
        }
    }
```



**基于 HTTP 协议的 PHP 微服务实现**



最后，我们基于 Swoole 来实现 HTTP 服务器代码：



```php
    <?php
    include "proxy.php";
    include "greeter.php";
    
    // 表明程序启动后监听本地 9051 端口
    $server = new swoole_http_server('192.168.31.218', 9508);
    
    $proxy = new Proxy();
    
    $service = [
        "id" => "php.micro.srv.greeter-" . uniqid(),
        "name" => "php.micro.srv.greeter",
        "address" => '192.168.31.218',
        "port" => 9508,
        "tags" => [
            "php-micro-service-demo"
        ]
    ];
    
    // 启动事件回调函数
    $server->on("start", function (\Swoole\Http\Server $server) use ($proxy, $service) {
        $proxy->register($service);
        echo "Swoole http server is started and micro services are registered\n";
    });
    
    // 请求事件回调函数
    $server->on("request", function (\Swoole\Http\Request $request, \Swoole\Http\Response $response) {
        if ($endpoint = $request->header['micro-endpoint']) {
            list($class, $method) = explode('/', $endpoint);
            $class = ucfirst($class);
            $obj = new $class;
            $params = json_decode($request->rawContent(), true);
            if (empty($params['name'])) {
                $data = $obj->$method();
            } else {
                $data = $obj->$method($params['name']);
            }
            $response->header("Content-Type", "application/json");
            $response->end($data);
        }
    });
    
    // 关闭事件回调函数
    $server->on('shutdown', function ($server, $fd) use ($proxy, $service){
        $proxy->deregister($service);
        echo "Swoole http server is closed and micro services are deregistered\n";
    });
    
    // 启动 HTTP 服务器
    $server->start();
```



我们将其保存为 `server.php`，可以看到，服务注册、调用和删除逻辑都在这里，我们重点看下服务调用代码，当 Micro Proxy 根据请求头中的 `Micro-Service` 值到注册中心查询到对应的服务节点（`192.168.31.218:9508`）后，会将请求转发到 Swoole HTTP 服务器来处理，并且将相应的 HTTP 请求信息都带过来，我们从请求头中拿到 `Micro-Endpoint` 字段值，并根据这个端点信息确定本地要调用的类和方法，然后将请求参数传进去，最后将处理结果以 JSON 格式返回。



这样一来，我们就可以将基于 PHP 实现的微服务纳入了 Go Micro 体系中，它们之间通过 Micro Proxy 通信，这使得 PHP 也就可以借助 Go Micro 的底层组件轻松实现微服务构建，而且这种实现对原本的 PHP 代码侵入很小，只需要在服务启动和关闭时从注册中心注册或删除服务节点即可。



**微服务接口调用测试**



首先，我们需要启动注册中心（已启动则忽略）：



```
    consul agent -dev
```

  

然后，要在 Go Micro 项目中启动 Micro Proxy：



```
    micro proxy
```

  

最后，进入 `swoole/microservice` 目录启动基于 PHP 实现的微服务：



```
    cd /path/to/swoole/microservice
    php server.php
```



![img](/assets/post/88a428593803f459dc402c27f035937b69f3f995f6e8a34db7607d98daf91445.png)



注册成功的话就可以在 Consul 控制面板中看到新注册的 `php.micro.srv.greeter` 服务节点了：



![img](/assets/post/1335c434e5e9798ad3e75df1d7e61494bf7b218cc7bec33f032fe92a6730b84d.png)



接下来，我们在 Postman 中模拟对这个远程服务接口的调用，基于 Micro Proxy 提供的代理地址：



![img](/assets/post/3b004663d49472737793d6240fa7d0d51a2e066bda566eff05717b731e053cea.png)



可以看到服务调用成功，对应的 CURL 代码如下：



```php
    curl -X POST \
      http://localhost:8081 \
      -H 'Content-Type: application/json' \
      -H 'Micro-Endpoint: greeter/hello' \
      -H 'Micro-Service: php.micro.srv.greeter' \
      -d '{"name": "学院君"}'
```

   

当然，你也可以修改请求参数对结果进行再次确认：

  

![img](/assets/post/e0f2beecf1c0d462d24fff7cf57edcc61ae997413c9b64e5d0e7d69f779a3c76.png)



基于 PHP 实现的简单微服务到此就告一段落了，除了 PHP 之外，还可以基于 Micro Proxy 纳入其它编程语言实现的微服务到 Go Micro 体系，此外，感兴趣的同学，还可以参照此例实现基于 RPC 协议的 PHP 微服务。