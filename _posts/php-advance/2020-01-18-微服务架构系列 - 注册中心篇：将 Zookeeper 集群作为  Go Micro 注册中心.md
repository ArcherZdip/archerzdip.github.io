---
title: 微服务架构系列 - 注册中心篇：将 Zookeeper 集群作为 Go Micro 注册中心
layout: post
category: blog
tags: |-
  PHP
  微服务架构系列
  Go Micro
  注册中心篇
  Zookeeper
---



# 微服务架构系列（二十七）

**以伪集群方式运行 Zookeeper**



上篇分享我们介绍了 Zookeeper 支持以单机和集群模式运行，单机模式用于开发环境，集群模式用于生产环境。单机模式上篇我们已经演示过了，这篇我们来看看如何通过集群模式运行 Zookeeper。



不过，我们还是在一台机器上操作，除了基于 Docker 或者 Vagrant 虚拟化技术在一台机器上虚拟化出多个节点外，Zookeeper 本身还支持以伪集群的方式在一台机器搭建多个节点。下面我们就通过这种方式实现 Zookeeper 的集群模式对外提供服务。



伪集群和集群模式非常相似，只不过集群模式是在多个节点安装 Zookeeper，伪集群是在单机操作，所以我们去 Zookeeper 项目官网下载最新稳定版本到本地：http://mirror.bit.edu.cn/apache/zookeeper/stable/（下载二进制版本），然后解压并拷贝到多个 Zookeeper 目录：



```
    wget http://mirror.bit.edu.cn/apache/zookeeper/stable/apache-zookeeper-3.5.5-bin.tar.gz
    tar zvxf apache-zookeeper-3.5.5-bin.tar.gz
    cp -r apache-zookeeper-3.5.5-bin zookeeper-node-1
    cp -r apache-zookeeper-3.5.5-bin zookeeper-node-2
    cp -r apache-zookeeper-3.5.5-bin zookeeper-node-3
```

  

然后分别将 `zookeeper-node-1`、`zookeeper-node-2`、`zookeeper-node-3` 三个目录下的 `conf/zoo_sample.cfg` 拷贝为 `zoo.cfg`：



```
    cp zookeeper-node-1/conf/zoo_sample.cfg zookeeper-node-1/conf/zoo.cfg
    cp zookeeper-node-2/conf/zoo_sample.cfg zookeeper-node-2/conf/zoo.cfg
    cp zookeeper-node-3/conf/zoo_sample.cfg zookeeper-node-3/conf/zoo.cfg
```



然后对各自目录下的 `zoo.cfg` 配置文件做如下修改，比如 `zookeeper-node-1/conf/zoo.cfg` 修改如下：



```
    tickTime=2000
    initLimit=10
    syncLimit=5
    dataDir=/usr/local/var/run/zookeeper1/data
    clientPort=2181
    #伪集群配置
    server.1=127.0.0.1:2888:3888
    server.2=127.0.0.1:2889:3889
    server.3=127.0.0.1:2890:3890
```



然后将 `zookeeper-node-1/conf/zoo.cfg` 拷贝到 `zookeeper-node-2/conf/zoo.cfg`，将其中的 `dataDir` 配置值修改为 `/usr/local/var/run/zookeeper2/data`，`clientPort` 修改为 `2182`，同理将 `zookeeper-node-1/conf/zoo.cfg` 拷贝到 `zookeeper-node-3/conf/zoo.cfg`，将其中的 `dataDir` 配置值修改为 `/usr/local/var/run/zookeeper3/data`，`clientPort` 修改为 `2183`，其他保持不变。



接下来依次创建上面 `dataDir` 映射到的目录：



  mkdir -p /usr/local/var/run/zookeeper1/data

  mkdir -p /usr/local/var/run/zookeeper2/data

  mkdir -p /usr/local/var/run/zookeeper3/data

  

接着将配置文件中的 Server ID 写入上面的每个 `data` 目录下的 `myid` 文件：



  echo 1 > /usr/local/var/run/zookeeper1/data/myid

  echo 2 > /usr/local/var/run/zookeeper2/data/myid

  echo 3 > /usr/local/var/run/zookeeper3/data/myid



可以看出，伪集群实际上这是通过不同端口（进程）模拟多个 Zookeeper 服务端节点。



最后，我们启动上面三个 Zookeeper 服务端节点：



  zookeeper-node-1/bin/zkServer.sh start

  zookeeper-node-2/bin/zkServer.sh start

  zookeeper-node-3/bin/zkServer.sh start

  

然后我们就可以通过 `status` 命令查看各个节点的状态：



![img](/assets/post/038b1a6e78407513cb64d4c49ad348703caa21f778e2ae853ae3b820e78c756d.png)



可以看到，现在节点 2 被选举为 Leader，其他两个节点是 Follower。不过我们在客户端建立连接时，连接集群中的任意一个节点即可，请求最终都会被转发给 Leader，然后由 Leader 负责在集群之间进行数据同步，从而完成最终一致性。



比如我们先连接到节点1，然后创建一个配置，再退出连接到节点3，也可以获取到这个配置信息，说明数据已经同步：



![img](/assets/post/4202656749d5ba2d12d71e4f8184d9922c80e1eb15acbbadec85ad2050f391b9.png)



**集成到 Go Micro 作为注册中心**



将 Zookeeper 集成到 Go Micro 框架作为注册中心和 Etcd 的做法类似，因为 Go Micro 默认不支持 Zookeeper，所以我们需要通过 Go Plugins 将其引入，所以我们需要通过下面的命令安装对应的依赖：



```
     go get github.com/micro/go-plugins/registry/zookeeper
```

   

在 `zookeeper` 插件中，会基于 Go 语言版的 Zookeeper 客户端 SDK：https://github.com/samuel/go-zookeeper 与 Zookeeper 服务端集群进行通信来实现服务注册和发现，具体的实现原理我们放到下一篇去探讨，现在我们先快速演示下功能。



打开 `src/hello/main.go`，在导入代码块中将之前导入的 `etcd` 插件替换为 `zookeeper` 插件：



```
    import (
        "context"
        "fmt"
        "github.com/micro/go-micro"
        _ "github.com/micro/go-plugins/registry/zookeeper"
        proto "hello/proto"
    )
```

  

然后通过如下方式启动微服务服务端，这一次，我们没有通过环境变量设置默认的注册中心，而是在启动服务时通过 `--registry=zookeeper` 选项进行指定，启动成功后可以看到服务已经注册到 Zookeeper 上， 默认的连接节点是 `127.0.0.1:2181` 对应的节点 1（当然你也可以自行指定连接到的 IP 和 端口，下篇介绍源码的时候会提到如何自己指定连接的 Zookeeper 服务端节点）：



![img](/assets/post/490a357e9c0b894763cebe3189164c4c215407ef1c0719b10ea2b84a703f1eab.png)



然后在 `src/hello/client.go` 的 `import` 语句中也将 `ectd` 替换成 `zookeeper`，然后运行客户端进行测试，可以看到客户端也默认连接到节点 1 进行服务查询，然后经由 Leader 节点查询后将结果返回给客户端，客户端再根据返回信息建立与微服务节点的连接和通信，最后返回最终处理结果并显示出来：



![img](/assets/post/67414fc91e4837830ad6b007aa6b79cacfeac044f331b0979e8a37ff150cb549.png)



同样，我们在任意 Zookeeper 服务端节点都可以查询到注册的微服务节点信息：



![img](/assets/post/0b549e33fee6009ea37b748dd1a12ddc8b52ad66147d4900b273fb0ad6cc4668.png)



下一篇分享学院君将带领大家简单分析下 `zookeeper` 插件底层的源码以及与 Zookeeper 集群的交互、服务节点的健康检查是如何实现的。