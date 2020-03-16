---
title: 微服务架构系列 - 增补篇：基于 Thrift + Laravel 构建微服务（一）
layout: post
category: blog
tags: |-
  PHP
  微服务架构系列
  Thrift
  Laravel
---

# 微服务架构系列（三十八）



简单介绍了 Thrift 框架，本周学院君将会花几个篇幅的教程来介绍如何基于 Thrift + Laravel 构建微服务接口。



**项目初始化**



为此，我们先初始化一个新的 Laravel 应用 `thrift`：



```
    laravel new thrift
```

  

在 `thrift` 项目根目录下新增一个 `thrift` 子目录，然后在该子目录下创建 Thrift IDL 文件 `user.thrift`，用于定义和用户相关的服务接口（语言为 PHP，命名空间为 `App\Thrift\User`）：



```
    namespace php App.Thrift.User
    
    // 定义用户接口
    service User {
        string getInfo(1:i32 id)
    }
```

  

接着在项目根目录下运行如下命令，根据上述 IDL 文件生成相关的服务代码：



```
    thrift -r --gen php:server -out ./ thrift/user.thrift
```

  

这样就会在 `App\Thrift\User` 命名空间下生成对应的服务代码：

  

![img](/assets/post/FiTWVPTvDpbA3cLMNm1bw4wGJICY.png)



然后通过 Composer 安装 Thrift PHP 依赖包：



```
    composer require apache/thrift
```

  

**编写 RPC 服务端代码**



接下来，我们就可以编写服务端代码了，在 `app` 目录下新建一个 `Services/Server` 子目录，然后在该目录下创建服务接口类 `UserService`，该类实现自 `App\Thrift\User\UserIf` 接口：



![img](/assets/post/FjOe7eY4ZSylzysW3u1sYJdGEfZo.png)



在服务接口实现中，我们通过传入参数查询数据库并返回对应的记录，这里为了简化逻辑，我们直接调用模型类查询记录并直接返回，将参数校验、缓存优化、异常处理通通省略。



接下来，我们来编写服务端启动命令类，在 Laravel 框架中，这可以通过 Artisan 控制台来完成，首先创建命令类：



```
    php artisan make:command RpcServerStart
```

  

该命令会在 `app/Console/Commands` 目录下生成 `RpcServerStart.php`，我们编写 `RpcServerStart` 命令类代码如下：



```php
    <?php
    namespace App\Console\Commands;
    
    use App\Services\Server\UserService;
    use App\Thrift\User\UserProcessor;
    use Illuminate\Console\Command;
    use Thrift\Exception\TException;
    use Thrift\Factory\TBinaryProtocolFactory;
    use Thrift\Factory\TTransportFactory;
    use Thrift\Server\TServerSocket;
    use Thrift\Server\TSimpleServer;
    use Thrift\TMultiplexedProcessor;
    
    class RpcServerStart extends Command
    {
        /**
         * The name and signature of the console command.
         *
         * @var string
         */
        protected $signature = 'rpc:start';
    
        /**
         * The console command description.
         *
         * @var string
         */
        protected $description = 'Start Thrift RPC Server';
    
        /**
         * Create a new command instance.
         *
         * @return void
         */
        public function __construct()
        {
            parent::__construct();
        }
    
        /**
         * Execute the console command.
         *
         * @return mixed
         */
        public function handle()
        {
            try {
                $thriftProcess = new UserProcessor(new UserService());
                $tFactory = new TTransportFactory();
                $pFactory = new TBinaryProtocolFactory();
                $processor = new TMultiplexedProcessor();
                // 注册服务
                $processor->registerProcessor('UserService', $thriftProcess);
                // 监听本地 8888 端口，等待客户端连接请求
                $transport = new TServerSocket('127.0.0.1', 8888);
                $server = new TSimpleServer($processor, $transport, $tFactory, $tFactory, $pFactory, $pFactory);
                $this->info("服务启动成功[127.0.0.1:8888]！");
                $server->serve();
            } catch (TException $exception) {
                $this->error("服务启动失败！");
            }
        }
    }
```



别忘了在 `app/Console/Kernel.php` 中注册上述命令类使其生效：



```php
    use App\Console\Commands\RpcServerStart;

    protected $commands = [
        RpcServerStart::class,
    ];
```

  

这样，服务端接口和启动命令都已经完成了，接下来我们继续编写客户端建立连接和请求通信代码。



**编写 RPC 客户端代码**  

  

这个客户端并不是前端、移动端，而是相对于 RPC 服务器的 RPC 客户端，我们在 `app/Services/Client` 目录下创建 `UserService.php`，用于存放 RPC 客户端连接与请求服务接口方法：



```php
    <?php
    namespace App\Services\Client;
    
    use App\Thrift\User\UserClient;
    use Thrift\Protocol\TMultiplexedProtocol;
    use Thrift\Exception\TException;
    use Thrift\Protocol\TBinaryProtocol;
    use Thrift\Transport\TBufferedTransport;
    use Thrift\Transport\TSocket;
    
    class UserService
    {
        public function getUserInfo(int $id)
        {
            try {
                // 建立与 RpcServer 的连接
                $socket = new TSocket("127.0.0.1", "8888");
                $socket->setRecvTimeout(30000);  // 超时时间
                $socket->setDebug(true);
                $transport = new TBufferedTransport($socket, 1024, 1024);
                $protocol = new TBinaryProtocol($transport);
                $thriftProtocol = new TMultiplexedProtocol($protocol, 'UserService');
                $client = new UserClient($thriftProtocol);
                $transport->open();
                $result = $client->getInfo($id);
                $transport->close();
                return $result;
            } catch (TException $TException) {
                dd($TException);
            }
        }
    }
```



同样，为了简化代码和流程，我这里将连接和请求代码写到一起了，如果有多个服务接口，传输层是可以共用的，需要拆分开。这里我们先建立与 RPC 服务器的连接，然后通过调用 `App\Thrift\User\UserClient` 提供的 `getInfo` 请求 RPC 服务端 `App\Services\Server\UserService` 类提供的 `getInfo` 方法获取用户信息并返回。



最后，我们在 `routes/web.php` 中注册客户端请求路由：



```php
    use App\Services\Client\UserService;
    
    Route::get('/user/{id}', function($id) {
        $userService = new UserService();
        $user = $userService->getUserInfo($id);
        return $user;
    });
```

  

该路由通过匿名函数定义了路由处理逻辑：调用 `App\Services\Client\UserService` 的 `getUserInfo` 方法获取用户信息。需要与传统的 PHP 请求处理区分的是，这个请求底层不是在本地通过内存调用完成的，而是通过 RPC 客户端请求 RPC 服务端接口基于远程服务调用完成的。



**测试 RPC 远程服务调用**

  

至此，RPC 客户端和服务端代码都已经编写好了，接下来我们来测试这个 RPC 接口调用。



修改项目根目录下 `.env` 中的数据库相关配置，运行数据迁移生成 `users` 表：



```
    php artisan migrate
```

  

然后初始化 `users` 表数据：



![img](/assets/post/Fhu7ROLMGGBUxaH49fy_N8EDz8fw.png)

  

接下来，在项目根目录下启动 Thrift RPC 服务端：



```
    php artisan rpc:start
```

  

![img](/assets/post/Fv5MocTN4LNiUAprPhmZUYI4RjYn.png)



再新开一个终端窗口启动 Laravel 应用（RPC 客户端）：



```
    php artisan serve
```

  

![img](/assets/post/FvaAHCOzejZHOvk0eu89bL4nvyd8.png)



然后就可以在浏览器中访问获取用户信息的路由：



![img](/assets/post/ForHpZdc3Ddu_JVFaUvne_52sj6D.png)



成功获取到用户记录，说明 RPC 远程服务调用成功。



可以看到，Thrift 只是个 RPC 框架而已，只解决了通信协议和数据传输问题，对于服务治理和容错并没有提供对应的解决方案，需要我们自己去实现，而且基于 PHP 实现的服务器在高并发情况下可能无法满足微服务接口的性能要求，后面我们将从这几个方面来完善这个示例应用。