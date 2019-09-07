---
title: 计算机网络协议系列 - 不定期分享之 ifconfig 与 ip addr 命令详解

layout: post

category: blog

tags: |-

  PHP

  计算机网络协议系列
---



# 计算机网络协议系列（十六）



**如何查看机器的IP地址**

我们在 Linux 系统查看 IP 地址通常有以下两种方式：

1）ifconfig

![img](/assets/post/23d004da516220dee854b9a721eddceacef109735fbea7785a48e983808d4a94.png)

2）ip addr

![img](/assets/post/6f2ac02ebb52c9aeb9cca768bd213aa253f6bea0d5c3b7b3380be239625ff54e.png)

注：如果在 Windows 系统上，查看 IP 地址的命令是 ipconfig。

这两个命令返回的都是机器的网卡信息，其中包含了网卡的 MAC 地址和 IP 地址，有了这两个地址才能进行网络通信。

**ifconfig 与 ip addr 源起和区别**

要了解这两个命令的区别，需要先看看它们的历史起源：

ifconfig 命令归属于 net-tools 工具集。net-tools 起源于 BSD，自 2001 年起，Linux 社区已经停止对其进行维护。而 ip 命令归属于 iproute2 工具集，iproute2 旨在取代 net-tools，并提供了一些新功能。

一些 Linux 发行版已经停止支持 net-tools，只支持 iproute2，在这些 Linux 版本中，只能使用 ip

addr 命令查看 IP 地址，使用 ifconfig 会提示命令不存在。

net-tools 通过 procfs(/proc) 和 ioctl 系统调用去访问和改变内核网络配置，而 iproute2 则通过 netlink 套接字接口与内核通讯。

net-tools 中工具的名字比较杂乱，而 iproute2 则相对整齐和直观，基本是 ip 命令加后面的子命令：

![img](/assets/post/e532599a965e74eeb5565d13f7cfdc0db2433aab443838d33eb62d1b0514e591.png)

**输出的网卡信息详解**

了解了两个命令的区别之后，下面我们以 ip addr 命令输出为例对每个字段的含义进行解释。

1）网卡名称

我们先看最外层，eth0 和 eth1 都是网卡的名称，其中 eth 是以太网英文名 Ethernet 的缩写，表示数据链路是以太网，之所以有两张网卡是因为一张网卡用于内网通信，一张网卡用于外网通信。

lo 全称是 loopback，又称环回接口，往往会被分配到 127.0.0.1 这个地址。这个地址用于本机通信，经过内核处理后直接返回，不会在任何网络中出现。

一般来说，任何主机都至少有上述三个网卡（或者至少一个lo网卡和以太网卡）。

然后我们依次看每一行的网卡信息。

2）网络设备状态标识

首先看第一行信息：

​    <BROADCAST,MULTICAST,UP,LOWER_UP> mtu 1500 qdisc fq_codel state UP group default qlen 1000

<BROADCAST,MULTICAST,UP,LOWER_UP> 叫作 net_device flags，即网络设备的状态标识。

UP 表示网卡处于启动的状态；BROADCAST 表示这个网卡有广播地址，可以发送广播包；MULTICAST 表示网卡可以发送多播包；LOWER_UP 表示 L1 是启动的，也就是网线是插着的。

mtu 1500 前面介绍数据链路层的时候提到过，表示以太网最大传输单元 MTU 为 1500，这是以太网的默认值。

qdisc 全称是 queueing discipline，中文叫排队规则。内核如果需要通过某个网络接口发送数据包，它都需要按照为这个接口配置的 qdisc（排队规则）把数据包加入队列。这里 lo 网卡配置的值是 noqueue 不使用队列，其它两个网卡配置的值是 fq_codel，对应的英文全名是 Fair Queueing with Controlled Delay，即具有受控延迟的公平队列，这种情况下每个网络流都有一个队列。

state UP 该网卡已启用，group default 表示网卡分组，qlen 1000 表示传输队列长度。

3）MAC 地址

接下来的每个网卡的第二行显示的是该网卡的 MAC 地址：

​    link/ether 08:00:27:b9:64:24 brd ff:ff:ff:ff:ff:ff

本地环回接口不需要，所以为空。

MAC 地址是一个网卡的物理地址，具体概念我们在[链路层](https://articles.zsxq.com/id_uspqbr5knj78.html)已经详细介绍过，使用十六进制表示，用冒号分隔，总共是六个字节。MAC 地址只能再同一个网段内通信，跨网段通信需要借助 IP 地址，所以接下来就是网卡的 [IP 地址](https://articles.zsxq.com/id_16g49gbkcjon.html)。

4）IPv4 地址

首先是 IPv4 地址：

​    inet 192.168.10.10/24 brd 192.168.10.255 scope global eth1

​        valid_lft forever preferred_lft forever

192.168.10.10/24 表示子网掩码，192.168.10.255 表示真正的 IP 地址。在 IP 地址的后面有个 scope，对于 eth1 这张网卡来讲，是 global，说明这张网卡是可以对外通信的，可以接收来自各个地方的包（如果还有 dynamic 表示该 IP 地址是动态分配的）。对于 lo 来讲，是 host，说明这张网卡仅仅可以供本机相互通信。

valid_lft 表示该 IP（IPv4） 地址的有效使用期限，这里配置为 forever 表示永久有效；preferred_lft 表示该 IP 地址的首选生存期，也是配置为 forever 表示永久有效。

5）IPv6 地址

最后是 IPv6 地址：

​    inet6 fe80::a00:27ff:feb9:6424/64 scope link

​        valid_lft forever preferred_lft forever

​        

IPv6 地址表示的地址区间非常之大，所以不需要区分网络号和主机号，也就不需要子网掩码了，IPv6 地址也是通过十六进制表示，需要注意的是这里 scope 配置为 link 表示只在此设备生效。其它配置和 IPv4 地址一样，不再赘述。