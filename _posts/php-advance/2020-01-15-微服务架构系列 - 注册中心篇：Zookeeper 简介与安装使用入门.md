---
title: 微服务架构系列 - 注册中心篇：Zookeeper 简介与安装使用入门
layout: post
category: blog
tags: |-
  PHP
  微服务架构系列
  Go Micro
  注册中心篇
  Zookeeper
---

# 微服务架构系列（二十六）

**基本介绍**



前面我们介绍了基于 Go 语言编写的、通过 Raft 算法实现分布式一致性的注册中心 Consul 和 Etcd，在 Go 语言微服务生态中，当然优先推荐使用这两个注册中心，不过，学院君还是要给大家介绍一个更老牌的，基于 ZAB 协议的 Zookeeper。关于 Zookeeper 其实我在前面的分布式入门系列中已经介绍过：



- [分布式数据一致性工业级解决方案 —— Zookeeper （一）](https://t.zsxq.com/fmiE2vJ)
- [分布式数据一致性工业级解决方案 —— Zookeeper （二）](https://t.zsxq.com/3b2VrfA)



这里简单回顾下，ZooKeeper 由雅虎（这个层级的互联网霸主已经随着岁月烟消云散）开发并开源给 Apache，用来实现分布式协调服务，使用 Java 语言开发，是 Google Chubby 的开源实现。



Zookeeper 也没有基于 Paxos 算法（现代分布式系统的算法基础，可以参考这篇分享了解细节：[大名鼎鼎的Paxos算法](https://t.zsxq.com/6yRZZN3)），而是基于其变种 ZAB 算法，其基本实现思路和 Paxos/Raft 类似，都有 Leader 和 Follower 的概念，以及选举和数据同步机制。不过，ZAB 的设计目标是构建高可用的分布式数据主备系统，而 Paxos 算法则用于构建一个分布式的一致性状态机系统。



ZooKeeper 是一个典型的分布式数据一致性的解决方案，为分布式应用提供了高效且可靠的分布式协调服务，提供了诸如统一命名服务、配置管理和分布式锁等分布式的基础服务，分布式应用程序可以基于它实现诸如数据发布/订阅、负载均衡、命名服务、分布式协调/通知、集群管理、Master 选举、分布式锁和分布式队列等功能。



Zookeeper 提供基于类似文件系统的目录节点树的方式来实现数据存储：



![img](/assets/post/ef994a31d4af49f795b89a56b08f100cf1ad699d92a045c0c065fb4fa145d6b4.png)



每个子目录项如 `NameService` 都被称作为 znode，这个 znode 是被它所在的路径唯一标识，如 `Server1` 这个 znode 的标识为 `/NameService/Server1`，znode 可以有子节点目录，并且每个 znode 可以存储数据并且可以对应多个版本，znode 可以被监控，包括这个目录节点中存储的数据的修改，子节点目录的变化等，一旦变化可以通知设置监控的客户端，这个是 Zookeeper 的核心特性，也正是基于这一特性，我们可以将其作为注册中心实现服务注册和发现，比如阿里巴巴开源的分布式服务框架 Dubbo 默认就推荐基于 Zookeeper 作为注册中心。



更多关于 Zookeeper 的底层实现，可以通过上面列出的分享链接去查看，后面介绍分布式开发的时候还会深入探讨，这里我们把视线移回到基于 Go Micro 框架的微服务开发上来。



与 Consul、Etcd 相比，由于 Zookeeper 基于 Java 开发，要使用 Zookeeper 作为注册中心的话，需要安装 Java 运行时环境，此外，Zookeeper 需要胖客户端，每个服务节点需要通过对应语言的 SDK 与 Zookeeper 进行通信，如果通过 HTTP API 进行服务注册和发现的话，还要自行维护服务消费者与服务提供者之间的健康检查。显然，相较于 Consul 和 Etcd 增加了实现的复杂度和额外的维护成本。



**安装使用**



下面我们在本地安装单机版的 Zookeeper 并测试其基本命令和使用。



前面我们说过，Zookeeper 基于 Java 语言编写，要安装运行它，首先需要在本地安装 Java 运行环境并设置相应的系统环境变量，关于这一块大家可以自行在网上搜索，这里就不单独介绍了，我这里的 Java 版本是 Java 8：



![img](/assets/post/ef553d0eb369f58f79216a555a4bf64340a7d01f1576d5641239c856046c2fcc.png)



Zookeeper 有两种运行模式：单机模式和集群模式，本篇教程我们先以单机模式安装运行，下一篇再介绍集群模式，和前面介绍的 Consul 和 Etcd 一样，单机模式的 Zookeeper 也是一种特殊模式的集群 —— 只有一台机器的集群。



下面我们以单机模式安装 Zookeeper，我们可以在[这里](http://mirror.bit.edu.cn/apache/zookeeper/)选择对应版本的 Zookeeper 下载，在 MacOS 中还可以通过如下命令快捷安装（这种方式安装的不一定是最新版本，但是测试足够了，这样做的好处是省去了额外的配置工作）：



```
    brew install zookeeper
```

  

我这里通过这种方式安装的版本是 `3.4.13`，安装完成后，可以通过 `/usr/local/etc/zookeeper/zoo.cfg` 查看默认配置：



![img](/assets/post/b97559a97a5353e209aa869a35c4f0774c4cf721ad0bd55fe6c51e0a71c2d4a2.png)



我们来简单看下这里的配置项（以下我们将 Zookeeper 简称 ZK）：



- `tickTime`：ZK 的时间单元，ZK 中所有时间都是以这个时间单元为基础进行整数倍配置。
- `initTime`：Follower 在启动过程中，会从 Leader 同步所有最新数据，然后确定自己能够对外服务的起始状态，Leader 允许 Follower 在 `initLimit` 时间内完成这个工作。
- `syncTime`：在运行过程中，Leader 负责与 ZK 集群中所有机器进行通信，例如通过一些心跳检测机制来检测机器的存活状态。如果 Leader 发出心跳包在 `syncLimit` 之后，还没有从 Follower 那里收到响应，那么就认为这个 Follower 已经不在线了。
- `dataDir`：存储快照文件 snapshot 的目录，默认情况下，事务日志也会存储在这里。
- `clientPort`：ZK 客户端连接 ZK 服务器的端口，即对外服务端口，默认设置为 2181。
- `maxClientCnxns`：单个客户端与单台服务器之间的连接数的限制，是 ip 级别的，默认是 60，如果设置为 0，那么表明不作任何限制。
- `autopurge.purgeInterval`：3.4.0 及之后版本，ZK 提供了自动清理事务日志和快照文件的功能，这个参数指定了清理频率，单位是小时，需要配置一个 1 或更大的整数，默认是 0，表示不开启自动清理功能。
- `autopurge.snapRetainCount`：这个参数和上面的参数搭配使用，用于指定需要保留的文件数目，默认是保留 3 个。
- `server.x=[hostname]:nnnnn[:nnnnn]`：对应上面的伪集群配置，x 是一个数字，与 myid 文件（该文件在 ZK 服务器启动手动创建，保存在 `dataDir` 配置的目录下，其中只有一个数字，即一个 Server ID）中的 id 是一致的，右边配置的是对应机器的 IP 地址和两个端口，第一个端口用于 Follower 和 Leader 之间的数据同步和其它通信，第二个端口用于 Leader 选举过程中投票通信。



如果是单机运行的话，我们不需要做任何额外配置即可通过如下命令启动 ZK 服务器：



```
    zkServer start
```

  

![img](/assets/post/64d4493d7bf98f45492528465e97eb86d2d8d48d00caf855737ce8366f28d165.png)



当然，我们也可以通过在配置文件 `/usr/local/etc/zookeeper/zoo.cfg` 中追加如下配置：



```
    server.1=127.0.0.1:2888:3888
```



然后在 `dataDir` 配置目录 `/usr/local/var/run/zookeeper/data` 下创建一个 myid 文件，并且在该文件中添加对应的 Server ID 值 1：



![img](/assets/post/5986323177a0d38e5d50bfa7350f65ebcc4054df225e77b7db278382e252b618.png)



然后我们重启下 ZK Server：



![img](/assets/post/185bae492e77aa57dc66746da15b4728d5bb45b6dfad123e67d4c284764ab72c.png)



同样启动成功，接下来我们可以基于 Telnet 命令通过 2181 端口连接到本地 ZK 服务器，并执行 `stat` 指令打印服务端信息：



![img](/assets/post/332c14e974d025686539efbed28482628a768f75beee50d678142e157a71daec.png)



也可以通过 Zookeeper 自带的 `zkCli` 命令连接到指定的 ZK 服务器：



![img](/assets/post/d4023118b52c591f8c99cf8a25bfad1799b7becbfeece8bbc8ec92eb39ce060e.png)



接下来，我们就可以在客户端执行指令对指定节点进行增删改查了：



![img](/assets/post/525b6e974c56e921892068c93a305f621f4b6d7e0093257d8780070adc123691.png)



以上就是 Zookeeper 的安装配置以及客户端与服务端的基本交互指令，下一篇分享我们将在本地构建 Zookeeper 伪集群，并在 Go Micro 框架中引入 Zookeeper 作为注册中心来实现服务注册与发现，同时介绍 Go 语言如何通过客户端 SDK 与 Zookeeper 服务器集群交互。