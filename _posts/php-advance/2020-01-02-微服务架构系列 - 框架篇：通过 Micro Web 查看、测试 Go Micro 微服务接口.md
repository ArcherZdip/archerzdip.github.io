---
title: 微服务架构系列 - 框架篇：通过 Micro Web 查看、测试 Go Micro 微服务接口
layout: post
category: blog
tags: |-
  PHP
  微服务架构系列
  Go Micro
---

# 微服务架构系列（十三） 



我们可以通过 Micro Web 提供的仪表盘页面查看和测试基于 Go Micro 提供的所有微服务接口，该功能和 Micro API 类似，通过如下命令启动：



```
    micro web
```

  

![img](/assets/post/6348b871004b074b3c6816b8cfb1032f99da3833a00d927010d46a5a895896dc.png)



默认监听的是 8082 端口，我们可以在本地浏览器通过 `http://localhost:8082/registry` 访问注册中心提供的所有微服务接口：



![img](/assets/post/7b852d1b8491c674056a4488bf3bf44a817909f7c13a5798e027db2f05908e77.png)



点击 `go.micro.srv.greeter`，可以看到对应的接口描述文档：



![img](/assets/post/7f10b631f60d7596be11b1e1551496a8f92609f1172ec77d9065a01f5c3846f7.png)



其中详细展示了注册该服务的注册中心节点信息，以及调用该接口的端点信息，要测试该接口的调用，可以在 `http://localhost:8082/call` 页面进行：



![img](/assets/post/9461a5a31ba2c00166cfcc51818329b2af968638ec74ab57b1e45212fa9d411d.png)



对应参数遵循接口文档描述的格式进行设置即可。



此外，还可以在 `http://localhost:8082/cli` 页面中通过命令行对服务接口进行窗口和测试：



![img](/assets/post/95df91a92239be1a8847e0a6907a6a8224d8d6b661eeb97a84306e6b94caaae6.png)



`micro web` 命令底层运行原理和 `micro api` 类似，对应源码位于 `micro/micro/web/web.go` 的 `run` 方法，具体我就不深入介绍了，你可以参考[上篇](https://articles.zsxq.com/id_pv7uln5du1m8.html)分析 Micro API 底层源码的思路对照着看。此外 Micro Web 也支持启用 ACME 和 TLS 对传输进行加密，你可以参考 [Micro Web 官方文档](https://micro.mu/docs/web.html)进行设置。