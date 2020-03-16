---
title: 微服务架构系列 - 增补篇：基于 Thrift Laravel 构建微服务（二）
layout: post
category: blog
tags: |-
  PHP
  微服务架构系列
  Thrift
  Laravel
---



# 微服务架构系列（三十九）



演示了如何在 Laravel 项目中集成 Thrift 提供远程 RPC 服务调用，不过，Thrift 默认是基于 PHP 同步阻塞机制的，在应对高并发场景时性能上是个硬伤，因此，今天这篇分享我们将引入 Swoole 来实现异步 TCP 服务器。



**服务端代码**



首先我们还是从服务端入手，在 `app` 目录下新建一个 `Swoole` 目录用于存放 Swoole 相关代码，首先我们创建一个 `ServerTransport.php` 用来存放服务端代理类，并编写代码如下：



```php
    <?php
    namespace App\Swoole;
    
    use Thrift\Exception\TTransportException;
    use Thrift\Server\TServerTransport;
    use Swoole\Server as SwooleServer;
    
    class ServerTransport extends TServerTransport
    {
        /**
         * @var array 服务器选项
         */
        public $options = [
            'worker_num' => 1,
            'dispatch_mode' => 1, //1: 轮循, 3: 争抢
            'open_length_check' => true, //打开包长检测
            'package_max_length' => 8192000, //最大的请求包长度,8M
            'package_length_type' => 'N', //长度的类型，参见PHP的pack函数
            'package_length_offset' => 0,   //第N个字节是包长度的值
            'package_body_offset' => 4,   //从第几个字节计算长度
        ];
        
        /**
         * @var SwooleServer
         */
        public $server;
    
        /**
         * SwooleServerTransport constructor.
         * @param $host
         * @param int $port
         * @param int $mode
         * @param int $sockType
         * @param array $options
         */
        public function __construct($host, $port = 9999, $mode = SWOOLE_PROCESS, $sockType = SWOOLE_SOCK_TCP, $options = [])
        {
            $this->server = new SwooleServer($host, $port, $mode, $sockType);
            $options = array_merge($this->options, $options);
            $this->server->set($options);
        }
        /**
         * List for new clients
         *
         * @return void
         * @throws TTransportException
         */
        public function listen()
        {
            if (!$this->server->start()) {
                throw new TTransportException('Swoole ServerTransport start failed.', TTransportException::UNKNOWN);
            }
        }
        /**
         * Close the server
         *
         * @return void
         */
        public function close()
        {
            $this->server->shutdown();
        }
        /**
         * Swoole服务端通过回调函数获取请求，不可以调用accept方法
         * @return TTransport
         */
        protected function acceptImpl()
        {
            return null;
        }
    }
```

  

我们在代理类的构造函数中初始化 Swoole TCP 服务器参数，然后在该类中定义 `listen` 方法启动这个 TCP 服务器并监听客户端请求，此外，我们还定义了一个 `close` 方法关闭该服务器。



接下来，我们在 `app/Swoole` 目录下创建 `Transport.php` 文件用于存放基于 Swoole 的传输层实现代码：



```php
    <?php
    namespace App\Swoole;
    
    use Swoole\Server as SwooleServer;
    use Thrift\Exception\TTransportException;
    use Thrift\Transport\TTransport;
    
    class Transport extends TTransport
    {
        /**
         * @var swoole服务器实例
         */
        protected $server;
        /**
         * @var int 客户端连接描述符
         */
        protected $fd = -1;
        /**
         * @var string 数据
         */
        protected $data = '';
        /**
         * @var int 数据读取指针
         */
        protected $offset = 0;
        
        /**
         * SwooleTransport constructor.
         * @param SwooleServer $server
         * @param int $fd
         * @param string $data
         */
        public function __construct(SwooleServer $server, $fd, $data)
        {
            $this->server = $server;
            $this->fd = $fd;
            $this->data = $data;
        }
        
        /**
         * Whether this transport is open.
         *
         * @return boolean true if open
         */
        public function isOpen()
        {
            return $this->fd > -1;
        }
        
        /**
         * Open the transport for reading/writing
         *
         * @throws TTransportException if cannot open
         */
        public function open()
        {
            if ($this->isOpen()) {
                throw new TTransportException('Swoole Transport already connected.', TTransportException::ALREADY_OPEN);
            }
        }
        
        /**
         * Close the transport.
         * @throws TTransportException
         */
        public function close()
        {
            if (!$this->isOpen()) {
                throw new TTransportException('Swoole Transport not open.', TTransportException::NOT_OPEN);
            }
            $this->server->close($this->fd, true);
            $this->fd = -1;
        }
        
        /**
         * Read some data into the array.
         *
         * @param int $len How much to read
         * @return string The data that has been read
         * @throws TTransportException if cannot read any more data
         */
        public function read($len)
        {
            if (strlen($this->data) - $this->offset < $len) {
                throw new TTransportException('Swoole Transport[' . strlen($this->data) . '] read ' . $len . ' bytes failed.');
            }
            $data = substr($this->data, $this->offset, $len);
            $this->offset += $len;
            return $data;
        }
       
        /**
         * Writes the given data out.
         *
         * @param string $buf The data to write
         * @throws TTransportException if writing fails
         */
        public function write($buf)
        {
            if (!$this->isOpen()) {
                throw new TTransportException('Swoole Transport not open.', TTransportException::NOT_OPEN);
            }
            $this->server->send($this->fd, $buf);
        }
    }
```

`Transport` 类主要用于从传输层写入或读取数据，最后我们创建 `Server.php` 文件，用于存放基于 Swoole 的 RPC 服务器类：



```php
    <?php
    namespace App\Swoole;
    
    use Swoole\Server as SwooleServer;
    use Thrift\Server\TServer;
    
    class Server extends TServer
    {
        public function serve()
        {
            $this->transport_->server->on('receive', [$this, 'handleReceive']);
            $this->transport_->listen();
        }
    
        public function stop()
        {
            $this->transport_->close();
        }
    
        /**
         * 处理RPC请求
         * @param Server $server
         * @param int $fd
         * @param int $fromId
         * @param string $data
         */
        public function handleReceive(SwooleServer $server, $fd, $fromId, $data)
        {
            $transport = new Transport($server, $fd, $data);
            $inputTransport = $this->inputTransportFactory_->getTransport($transport);
            $outputTransport = $this->outputTransportFactory_->getTransport($transport);
            $inputProtocol = $this->inputProtocolFactory_->getProtocol($inputTransport);
            $outputProtocol = $this->outputProtocolFactory_->getProtocol($outputTransport);
            $this->processor_->process($inputProtocol, $outputProtocol);
        }
    }
```



该类继承自 `Thrift\Server\TServer`，在子类中需要实现 `serve` 和 `stop` 方法，分别定义服务器启动和关闭逻辑，这里我们在 `serve` 方法中定义了 Swoole TCP 服务器收到请求时的回调处理函数，其中 `$this->transport` 指向 `App\Swoole\ServerTransport` 实例，回调函数 `handleReceive` 中我们会将请求数据传入传输层处理类 `Transport` 进行初始化，然后再通过一系列转化通过处理器对请求进行处理，该方法中 `$this` 指针指向的属性都是在外部启动 RPC 服务器时传入的，后面我们会看到。定义好请求回调后，即可通过 `$this->transport_->listen()` 启动服务器并监听请求。



下面，我们还是通过 Artisan 命令来启动 RPC 服务器，创建一个新的命令类 `SwooleServerStart`：



```
    php artisan make:command SwooleServerStart
```

  

编写命令类实现代码如下：



```php
    <?php


    namespace App\Console\Commands;
    
    use App\Services\Server\UserService;
    use App\Swoole\Server;
    use App\Swoole\ServerTransport;
    use App\Swoole\TFramedTransportFactory;
    use App\Thrift\User\UserProcessor;
    use Illuminate\Console\Command;
    use Thrift\Exception\TException;
    use Thrift\Factory\TBinaryProtocolFactory;
    
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
            }
        }
    }
```

  

基本逻辑和上篇一致，这里我们使用了一个自定义的 `TFramedTransportFactory` 类，对应源码位于 `app/Swoole/TFramedTransportFactory.php`：



```php
    <?php
    namespace App\Swoole;
    
    use Thrift\Factory\TTransportFactory;
    use Thrift\Transport\TFramedTransport;
    use Thrift\Transport\TTransport;
    
    class TFramedTransportFactory extends TTransportFactory
    {
        public static function getTransport(TTransport $transport)
        {
            return new TFramedTransport($transport);
        }
    }
```

  

该类重写了 `getTransport` 类方法来返回经过 `TFramedTransport` 封装的 Transport，以便被Swoole 服务器处理。



最后，我们在 `app/Console/Kernel.php` 中注册 `SwooleServerStart` 这个命令类使其生效：



```php
    use App\Console\Commands\SwooleServerStart;

    protected $commands = [
        RpcServerStart::class,
        SwooleServerStart::class,
    ];
```

  

**客户端代码**



接下来，我们来修改客户端请求服务端远程接口的代码，在此之前在 `app/Swoole` 目录下新建一个 `ClientTransport.php` 来存放客户端与服务端通信的传输层实现代码：



```php
    <?php
    namespace App\Swoole;
    
    use Swoole\Client;
    use Thrift\Exception\TTransportException;
    use Thrift\Transport\TTransport;
    
    class ClientTransport extends TTransport
    {
        /**
         * @var string 连接地址
         */
        protected $host;
        /**
         * @var int 连接端口
         */
        protected $port;
        /**
         * @var Client
         */
        protected $client;
    
        /**
         * ClientTransport constructor.
         * @param string $host
         * @param int $port
         */
        public function __construct($host, $port)
        {
            $this->host = $host;
            $this->port = $port;
            $this->client = new Client(SWOOLE_SOCK_TCP);
        }
    
        /**
         * Whether this transport is open.
         *
         * @return boolean true if open
         */
        public function isOpen()
        {
            return $this->client->sock > 0;
        }
    
        /**
         * Open the transport for reading/writing
         *
         * @throws TTransportException if cannot open
         */
        public function open()
        {
            if ($this->isOpen()) {
                throw new TTransportException('ClientTransport already open.', TTransportException::ALREADY_OPEN);
            }
            if (!$this->client->connect($this->host, $this->port)) {
                throw new TTransportException(
                    'ClientTransport could not open:' . $this->client->errCode,
                    TTransportException::UNKNOWN
                );
            }
        }
    
        /**
         * Close the transport.
         * @throws TTransportException
         */
        public function close()
        {
            if (!$this->isOpen()) {
                throw new TTransportException('ClientTransport not open.', TTransportException::NOT_OPEN);
            }
            $this->client->close();
        }
    
        /**
         * Read some data into the array.
         *
         * @param int $len How much to read
         * @return string The data that has been read
         * @throws TTransportException if cannot read any more data
         */
        public function read($len)
        {
            if (!$this->isOpen()) {
                throw new TTransportException('ClientTransport not open.', TTransportException::NOT_OPEN);
            }
            return $this->client->recv($len, true);
        }
        
        /**
         * Writes the given data out.
         *
         * @param string $buf The data to write
         * @throws TTransportException if writing fails
         */
        public function write($buf)
        {
            if (!$this->isOpen()) {
                throw new TTransportException('ClientTransport not open.', TTransportException::NOT_OPEN);
            }
            $this->client->send($buf);
        }
    }
```

然后我们在 `app/Services/Client/UserService.php` 中改写 `UserService` 类实现代码如下：



```php
    <?php
    namespace App\Services\Client;
    
    use App\Swoole\ClientTransport;
    use App\Thrift\User\UserClient;
    use Thrift\Exception\TException;
    use Thrift\Protocol\TBinaryProtocol;
    use Thrift\Protocol\TMultiplexedProtocol;
    use Thrift\Transport\TBufferedTransport;
    use Thrift\Transport\TFramedTransport;
    use Thrift\Transport\TSocket;
    
    class UserService
    {
        public function getUserInfoViaRpc(int $id)
        {
            try {
                // 建立与 RpcServer 的连接
                $socket = new TSocket("127.0.0.1", 8888);
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
    
        public function getUserInfoViaSwoole(int $id)
        {
            try {
                // 建立与 SwooleServer 的连接
                $socket = new ClientTransport("127.0.0.1", 9999);
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
    }
```

新增了一个 `getUserInfoViaSwoole` 方法来定义与 Swoole TCP 服务器通信访问远程服务接口的实现。基本逻辑和 `getUserInfoViaRpc` 差不多，只是将 Transport 实现改为 `ClientTransport` 类，并且通过 `TFramedTransport` 进行封装，从而与服务端保持一致实现数据的正常读取与写入。



为了测试该方法，需要在 `routes/web.php` 中修改之前的路由定义：



```php
    Route::get('/user/{id}', function($id) {
        $userService = new UserService();
        //$user = $userService->getUserInfoViaRpc($id);
        $user = $userService->getUserInfoViaSwoole($id);
        return $user;
    });
```



**测试服务接口访问**



至此，所有编码工作告一段落，我们新开一个终端窗口，启动 Swoole TCP 服务器：



![img](/assets/post/FpEfZlPINMuUtwWY7dFlrVEQ1M7K.png)



然后在客户端还是通过 `php artisan serve` 启动 Laravel 应用，然后在浏览器中访问 `http://127.0.0.1:8000/user/1`，得到的结果和上篇分享一致：



![img](/assets/post/FuTlFuaOOF_QhXyOMOUIEM5QfPc3.png)