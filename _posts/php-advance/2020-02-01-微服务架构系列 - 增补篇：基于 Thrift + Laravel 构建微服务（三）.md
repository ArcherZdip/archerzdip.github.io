---
title: 微服务架构系列 - 增补篇：基于 Thrift + Laravel 构建微服务（三）
layout: post
category: blog
tags: |-
  PHP
  微服务架构系列
  Thrift
  Laravel
---



# 微服务架构系列（四十）



前面我们介绍过，Thrift 只提供了传输层的解决方案，只能用作 RPC 框架来提供远程服务调用，如果要实现完整的微服务体系，需要自行实现服务发现、服务治理，下面我们就将借助开源的 Zookeeper 作为服务发现提供者，与 Thrift 一起构建微服务体系。



**技术架构**



关于 Zookeeper 的功能和安装配置，我们在 [Zookeeper 简介与安装使用入门](https://articles.zsxq.com/id_x88p5xa07rkh.html)这篇分享中已经详细介绍过，有了前面 Go Micro 的介绍，想必你也应该对微服务的基本架构有所了解，下面，我们来尝试绘制这个架构图：



![img](/assets/post/FmrdjSBdCsOMIbEB_-eLN-SvF_LF.png)



如图，我们在 RPC 服务端启动时（可能是个集群），会将服务节点注册到 Zookeeper 集群中，然后等待 RPC 客户端请求。客户端也会监听 Zookeeper 集群，当终端用户发起请求时，会先通过 Zookeeper 查询对应的服务节点是否存在，如果存在，则从节点列表中通过负载均衡算法获取其中某个节点，建立连接、发起请求、获取响应，再将响应结果处理后返回给终端用户，从而完成一次完整的微服务调用。当 RPC 服务端某个节点删除或者不可用，或者新增了节点，也应该及时通知 RPC 客户端，保证服务的高可用性。



以上是 Go Micro 微服务调用和服务发现的基本流程，也适用于所有其他微服务体系，这里，我们就按照这个思路将 Zookeeper 集成到 Thrift 框架中来实现服务发现。



**环境准备**



接下来，需要安装 PHP 的 Zookeeper 扩展以便和 Zookeeper 服务器进行通信，我们以 Homestead/Ubuntu 环境为例进行演示，首先需要安装 Zookeeper：



```
    sudo apt install zookeeperd
```

  

然后，安装 `libzookeeper`，因为 PHP Zookeeper 需要通过它与 Zookeeper 服务器通信：



```
    sudo apt install libzookeeper-st-dev  // 单线程
    sudo apt install libzookeeper-mt-dev  // 多线程
```



最后安装 PHP Zookeeper 扩展：



```
    pecl install zookeeper
```

  

> 注：目前最新稳定版本要求 PHP 版本在 7.0~7.2，不支持 PHP 7.3。



安装完成后，需要到 `php.ini` 中添加如下这行使其生效（CLI 和 FPM 模式都要添加）：



```
    extension=zookeeper.so
```

  

通过 `php -m` 可以看到 `zookeeper` 即可证明 Zookeeper 扩展安装成功。



FPM 配置文件修改后需要重启 PHP-FPM 服务，然后通过 `phpinfo()` 可以看到 Zookeeper 扩展信息表示安装成功：



![img](https://article-images.zsxq.com/FqTHru8Msz4Ops1zTIt_VdO6VSe1)



**服务注册**



安装完 Zookeeper 扩展后，我们就可以在 PHP 代码中通过它提供的 API 与 Zookeeper 服务器进行交互了。接下来，我们来编写 RPC 服务端启动时进行服务注册的代码。为了简化演示流程，我们将相关代码都写到命令类中，在此之前，在 `app/Services` 目录下创建一个 `ZookeeperService.php` 文件，初始化代码如下：



```php
    <?php
    namespace App\Services;
    
    use Zookeeper;
    
    class ZookeeperService
    {
        private static $zkServer = null;
    
        public static function getZkServer()
        {
            if (self::$zkServer == null) {
                $zkServer = new Zookeeper('127.0.0.1:2181');
                self::$zkServer = $zkServer;
            }
            return self::$zkServer;
        }
    }
```



该类提供了一个静态 `getZkServer()` 方法用于返回 `zkServer` 实例。



接下来，在 `app/Console/Commands/SwooleServerStart.php` 中，修改命令类代码如下：



```php
    <?php
    
    namespace App\Console\Commands;
    
    use App\Services\Server\UserService;
    use App\Services\ZookeeperService;
    use App\Swoole\Server;
    use App\Swoole\ServerTransport;
    use App\Swoole\TFramedTransportFactory;
    use App\Thrift\User\UserProcessor;
    use Illuminate\Console\Command;
    use Thrift\Exception\TException;
    use Thrift\Factory\TBinaryProtocolFactory;
    use Zookeeper;
    
    class SwooleServerStart extends Command
    {
        /**
         * The name and signature of the console command.
         *
         * @var string
         */
        protected $signature = 'swoole:start';
    
        /**
         * The console command description.
         *
         * @var string
         */
        protected $description = 'Start Swoole Thrift RPC Server';
    
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
                // 注册服务到 ZK
                $zkServer = ZookeeperService::getZkServer();
                $nodePath = '/UserService';
                if (!$zkServer->exists($nodePath)) {
                    $zkServer->create($nodePath, null, [[
                        'perms'  => Zookeeper::PERM_ALL,
                        'scheme' => 'world',
                        'id'     => 'anyone',
                    ]]);
                }
                $nodes = $zkServer->get($nodePath);
                if (!$nodes) {
                    $nodes = ['127.0.0.1:9999'];
                } else {
                    $nodes = json_decode($nodes, true);
                    $nodes[] = '127.0.0.1:9999';
                }
                $zkServer->set($nodePath, json_encode(array_unique($nodes)));
                $this->info("服务注册到 Zookeeper 成功");
                $processor = new UserProcessor(new UserService());
                $tFactory = new TFramedTransportFactory();
                $pFactory = new TBinaryProtocolFactory();
                // 监听本地 9999 端口，等待客户端连接请求
                $transport = new ServerTransport('127.0.0.1', 9999);
                $server = new Server($processor, $transport, $tFactory, $tFactory, $pFactory, $pFactory);
                $this->info("服务监听地址: 127.0.0.1:9999");
                $server->serve();
            } catch (TException $exception) {
                $this->error("服务启动失败！");
    
            } catch (\ZookeeperException $exception) {
                $this->error("注册服务失败:" . $exception->getMessage());
            }
        }
    }
```



我们在启动服务之前将其注册到 Zookeeper 中，为了简化演示流程，处理的比较简单粗暴，对服务节点启动失败以及关闭没有从 Zookeeper 服务器将对对应节点摘除，感兴趣的同学可以自行去实现。



**服务发现**



接下来，来到 RPC 客户端，编写服务发现代码，打开 `app/Services/Client/UserService.php`，修改 `getUserInfoViaSwoole` 方法实现如下：



```php
    use App\Services\ZookeeperService;
    
    public function getUserInfoViaSwoole(int $id)
    {
        try {
            // 通过 Zookeeper 获取 RPC 服务器节点
            $zkServer = ZookeeperService::getZkServer();
            $nodePath = '/UserService';
            if (!$zkServer->exists($nodePath)) {
                exit('对应的服务节点尚未在 ZK 注册');
            }
            $nodes = $zkServer->get($nodePath);
            if (!$nodes) {
                exit('对应的服务节点尚未在 ZK 注册');
            }
            // 从服务节点列表中随机获取一个节点
            $nodes = json_decode($nodes, true);
            $node = $nodes[array_rand($nodes)];
            list($ip, $port) = explode(':', $node);
            // 建立与 SwooleServer 的连接
            $socket = new ClientTransport($ip, $port);
            $transport = new TFramedTransport($socket);
            $protocol = new TBinaryProtocol($transport);
            $client = new UserClient($protocol);
            $transport->open();
            $result = $client->getInfo($id);
            $transport->close();
            return $result;
        } catch (TException $TException) {
            dd($TException);
        }
    }
```



之前我们是写死的 RPC 服务端 IP 和端口号，这里，我们将其修改为从 Zookeeper 中获取服务端节点信息，这里为了简化演示流程，我们也没有对结果进行缓存以及监听服务节点，感兴趣的可以自己去实现，PHP Zookeeper 扩展提供了对监听器的支持。



**功能测试**



为了简化流程，这里我们就通过默认的 Zookeeper 单例进行演示，如果你想设置 Zookeeper 集群，可以参考[将 Zookeeper 集群作为  Go Micro 注册中心](https://articles.zsxq.com/id_qvmwno644oe9.html)这篇分享。我们可以通过下面这个命令快速启动 Zookeeper 服务器：



```
    sudo service zookeeper start
```

  

然后进入 `thrift` 项目根目录，启动基于 Swoole 的 RPC 服务端，完成服务注册：



```
    php artisan swoole:start
```

  

![img](/assets/post/FlUKxvmgSOAGMs69rDWKIpubHn5D.png)



然后从客户端访问该远程服务：



![img](/assets/post/FshEfbC811L3_wFsmuwVEMd2KRjM.png)



返回数据成功，表示基于 Zookeeper 实现服务发现的完整 RPC 远程服务调用链路没有问题。