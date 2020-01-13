---
title: 微服务架构系列 - 注册中心篇：基于 Vagrant 模拟 Consul 集群实现服务注册与发现
layout: post
category: blog
tags: |-
  PHP
  微服务架构系列
  Go Micro
  注册中心篇
  Consul
  Vagrant
---

# 微服务架构系列（二十三） 

由于 Consul 是由 Hashicorp 公司开发的，而这个公司旗下还有一款著名的虚拟化工具 Vagrant，所以这篇教程我们基于 Vagrant 在本地通过虚拟机来演示如何搭建一个包含多个服务端和客户端代理的 Consul 集群。



**初始化虚拟机**



我们将搭建一个包含两个 Consul Server，一个 Consul Client 的集群环境，前面我们介绍 Consul 原理的时候已经提到过，Server 比较重，负责所有数据的存储并保证一致性，Client 则比较轻量级，只负责健康检查和请求转发，首先我们在本地用户家目录下创建一个 `Consul` 目录作为 Consul 虚拟机的工作台，然后在该目录下创建一个 `Vagrantfile` 来存储虚拟机配置信息：



```
    cd ~
    mkdir Consul
    cd Consul
    touch Vagrantfile
```

  

然后编辑 `Vagrantfile` 内容如下（从 https://github.com/hashicorp/consul/blob/master/demo/vagrant-cluster/Vagrantfile 示例文件中拷贝过来粘贴）：



```
    # -*- mode: ruby -*-
    # vi: set ft=ruby :
    
    $script = <<SCRIPT
    
    echo "Installing dependencies ..."
    sudo apt-get update
    sudo apt-get install -y unzip curl jq dnsutils
    
    echo "Determining Consul version to install ..."
    CHECKPOINT_URL="https://checkpoint-api.hashicorp.com/v1/check"
    if [ -z "$CONSUL_DEMO_VERSION" ]; then
        CONSUL_DEMO_VERSION=$(curl -s "${CHECKPOINT_URL}"/consul | jq .current_version | tr -d '"')
    fi
    
    echo "Fetching Consul version ${CONSUL_DEMO_VERSION} ..."
    cd /tmp/
    curl -s https://releases.hashicorp.com/consul/${CONSUL_DEMO_VERSION}/consul_${CONSUL_DEMO_VERSION}_linux_amd64.zip -o consul.zip
    
    echo "Installing Consul version ${CONSUL_DEMO_VERSION} ..."
    unzip consul.zip
    sudo chmod +x consul
    sudo mv consul /usr/bin/consul
    
    sudo mkdir /etc/consul.d
    sudo chmod a+w /etc/consul.d
    
    SCRIPT
    
    # Specify a Consul version
    CONSUL_DEMO_VERSION = ENV['CONSUL_DEMO_VERSION']
    
    # Specify a custom Vagrant box for the demo
    DEMO_BOX_NAME = ENV['DEMO_BOX_NAME'] || "debian/stretch64"
    
    # Vagrantfile API/syntax version.
    # NB: Don't touch unless you know what you're doing!
    VAGRANTFILE_API_VERSION = "2"
    
    Vagrant.configure(VAGRANTFILE_API_VERSION) do |config|
      config.vm.box = DEMO_BOX_NAME
    
      config.vm.provision "shell",
                              inline: $script,
                              env: {'CONSUL_DEMO_VERSION' => CONSUL_DEMO_VERSION}
    
      config.vm.define "n1" do |n1|
          n1.vm.hostname = "n1"
          n1.vm.network "private_network", ip: "172.20.20.10"
      end
    
      config.vm.define "n2" do |n2|
          n2.vm.hostname = "n2"
          n2.vm.network "private_network", ip: "172.20.20.11"
      end
    end
```



接下来，在该目录下运行如下命令初始化并启动上面配置的两个 Consul Server 节点对应的虚拟机（IP 分别为 `172.20.20.10` 和 `172.20.20.11`）：



```
    vagrant up
```

  

![img](/assets/post/b88207e2b7091109c4f0249e76364927a3acef071326ffc29a28972d14137d74.png)



如果感觉这种方式下载太慢，可以将其中的链接拷贝出来：https://vagrantcloud.com/debian/boxes/stretch64/versions/9.9.1/providers/virtualbox.box，粘贴到浏览器地址栏然后回车下载，浏览器中下载还是慢的话，就再把浏览器解析出来的下载地址拷贝到下载软件去下载：



![img](/assets/post/b694ca78803b31d8331420220dc46762a50690290c68bdb057347c96a35eab1c.png)



可以看到，下载速度快了很多，下载完成后，在本地将下载的 box 文件手动添加到 Vagrant 里面，通过 `name` 选项指定 box 名称，这里的 `name` 和 `Vagrantfile` 文件中的 `DEMO_BOX_NAME` 值保持一致，默认是 `debian/stretch64`：



```
    vagrant box add --name debian/stretch64 /path/to/virtualbox
```

  

![img](/assets/post/73671f4942622132a18a752a61f07adcd3b6e6376c21d6ffbebccc4e9677ef85.png)

  

然后再到 `Vagrantfile` 所在目录运行 `vagrant up` 命令，即可快速完成虚拟机安装：



![img](/assets/post/2d9084cd7e75afb1194ae2e5386569d35021ca3d1755f5e5fb37cb616e447214.png)

...

![img](/assets/post/65c1707cffaa127150d9e53c662c9d03cd283b0b5dc96967d2761b9fdca19471.png)



**检查服务端 Consul 安装**



安装完成后，我们来检查下虚拟机环境是否符合预期，这里我们只安装了两个虚拟机，用于模拟 Consul Server 服务端节点，而 Consul Client 则运行在本地，与 Go Micro 搭建的微服务部署在一起。



首先运行 `vagrant status` 查看虚拟机盒子状态，可以看到已经上面安装的两个虚拟机已经运行起来了：



![img](/assets/post/ba9331c306be4a011e60672a5ef70a7386f711cd953a01a03385e0a4b4cf955f.png)



打开 VirtualBox，也可以在虚拟机列表中看到这两个虚拟机：



![img](/assets/post/d3a111da2a77476364c999a3e957608e6f2ba6ac04215ff020116e020de7b890.png)



接下来，我们分别进入这两个虚拟机，检查 Consul 是否安装成功：



![img](/assets/post/3d1f551e178a65d8301aa20e586d2d13651ebd4844c3d122454ad718f13bba26.png)



**启动 Consul Server 集群**



从上面的截图中可以看到 Consul 在服务端都已经安装成功，接下来，我们要以 Server 模式启动它们，并将它们组合到同一个数据中心集群，打开两个终端窗口，一个通过 `vagrant ssh n1` 进入节点 `n1`，以服务器模式启动 Consul：

 

```
consul agent -server -bootstrap-expect=2 \
   -data-dir=/tmp/consul -node=agent-one -bind=172.20.20.10 \
   -config-dir=/etc/consul.d 
```



![img](/assets/post/522e655e46ff164a3402fc3a840164a4914f446caa0c2a6eae7c5941e3de599c.png)



另一个通过 `vagrant ssh n2` 进入节点 `n2`，同样以服务器模式启动 Consul：



```
consul agent -server -bootstrap-expect=2 \
    -data-dir=/tmp/consul -node=agent-two -bind=172.20.20.11 \
    -enable-script-checks=true -config-dir=/etc/consul.d
```



![img](/assets/post/13bcfc17f1c3f368d2444a46ac6a4cd598ddbfb147518ccf6ea1700b0cb3f702.png)



此时，两个服务器节点的 Consul Server 代理都已经运行起来了，但它们还不知道彼此，要将它们加入到一个集群，可以这么做，进入 `n1` 虚拟机，运行如下命令：



![img](/assets/post/bdb2c5df419961b3ba768567f0203378beebfe5d08320f1d96bd77058f892d46.png)



将 `n2` 节点对应的 IP 地址 `172.20.20.11` 加入进来，这样，运行 `consul members` 就能看到它们已经处于同一个数据中心了（默认是 `dc1`）：



![img](/assets/post/a696ab7edc678bee18fc760b357226ec0a51af07842894ffe2aff53b4fe0940b.png)



反之，也可以在 `n2` 上运行该命令，将 IP 地址调整为 `n1` 节点的 `IP` 地址即可。



同时可以在 Consul 日志中看到选举 `n1` 为 Leader 节点（在各自节点上可以通过 `consul info` 查看明细）：



![img](/assets/post/07b54e3179b8fde6bb9164119f3e67c26d701ce0c5420490a2a08bb60ce3942e.png)



**启动 Consul 客户端代理**



接下来，我们回到本地，通过如下命令以 Client 模式启动 Consul 代理：



```
    consul agent -node=agent-client -bind=172.20.20.1 -data-dir=/tmp/consul
```



![img](/assets/post/551950740f1a9cc44468daef9d33d12356935124f066521c9615292eeda10b6f.png)



同样，如果没有加入到集群中，也是看不到其它 Consul 代理的，我们可以通过和服务端一样的方式将其加入到集群中（加入任何一个节点即可），然后就可以看到集群中的所有节点了，包括服务端和客户端 Consul 代理：



![img](/assets/post/b8458e5727ef97125832cd7e9a3da2f70ab1d48965f0e431d87cc9396a1f5eeb.png)



这个时候，如果中断 Consul 服务端/客户端代理进程，会导致对应节点状态为 `failed`，即不可用：



![img](/assets/post/28067d296b9d41b62d0579d1bdbff43ae5c8a05faa2ef89b8a7393e33808f278.png)



我们可以带上 `-rejoin` 选项再次启动中断的节点代理，表示重新加入之前的集群，同时带上 `-ui` 选项表示启动 Web UI 可视化界面：



```
    consul agent -node=agent-client -bind=172.20.20.1 -data-dir=/tmp/consul -ui -rejoin 
```



![img](/assets/post/a148342087f84e3cb5956b263c5af30f5443a58055b9137650b56a70ceee17a6.png)



再次运行 `consul members` 命令，可以看到对应节点的 Consul 代理进程已经恢复正常了：



![img](/assets/post/46a9ef408a875a335df86826e8633290248c1c637bd051a01b5f31c485b7cff4.png)



在浏览器中访问 `http://localhost:8500/ui/dc1/nodes`，可以看到所有有效的节点信息：



![img](/assets/post/10d9d5eaec25c62f526af7929d589ca23aba2efd28ee2916622f0f33ce0228d4.png)



**简单测试**



接下来，我们简单测试下 Consul 的 KV 存储功能，在客户端机器上运行如下代码：



```
    consul kv put hello world
```



然后在服务端机器即可访问到对应的存储信息，因为客户端代理并不会做存储和同步，这些请求会转发到服务端，最终由 Leader 完成存储和同步：



```
    consul kv get hello
```

  

![img](/assets/post/f6b041c8df3c1bf5ca5957688ef321368c0aa304aa0fd7d0789ec0242fd98ad3.png)



然后我们来测试下服务注册和发现功能是否正常，在本地启动 Greeter 微服务：



![img](/assets/post/d57ce76640b7f5378c5409826af07a89dbeaed8073b1dec1eddc832b2e67b737.png)



服务注册时，同样会经由客户端运行的代理将请求转发给服务端 Leader 节点进行存储和同步，登录到 `n1` 节点，运行如下命令即可看到对应注册的所有服务：



![img](/assets/post/f8a8678547331f0d2e3bfca5dee82e3948504719da9d0ac7f139f6d06c549d55.png)



接下来，在本地运行客户端请求代码：



![img](/assets/post/47bd94c9904ee7fcefb5608231f5cc83f4e5879b05557d5a82ab3419874cbe7e.png)



可以看到，服务发现功能也是 OK 的，这个服务请求同样会经由客户端 Consul 代理转发到服务端代理进行查询，并将服务节点信息返回，再由对应节点运行的微服务进程对请求进行处理和返回。



当然，我们还可以基于 Docker 来模拟 Consul 集群，关于 Docker 编排部分，我们在后面运维部分再详细介绍。