---
title: Redis数据类型之位图 bitmap
layout: post
category: blog
tags: |-
  PHP
  Redis
  Bitmap
---

<!-- TOC -->

- [简介](#简介)
- [设置某位上的值](#设置某位上的值)
- [获取某位上的值](#获取某位上的值)
- [统计范围内1的个数](#统计范围内1的个数)
- [查找第一次0或者1出现的位置](#查找第一次0或者1出现的位置)
- [BITOP](#bitop)
- [使用场景](#使用场景)
    - [使用场景一：用户签到](#使用场景一用户签到)
    - [使用场景二：统计活跃用户](#使用场景二统计活跃用户)
    - [使用场景三：用户在线状态](#使用场景三用户在线状态)

<!-- /TOC -->


## 简介
位图不是实际的数据类型，而是在字符串类型上定义的一组面向位的操作。因为字符串是二进制安全的blob，它们的最大长度为512 MB，所以可以设置2^32个不同的位。

位操作分为两组:常量时间的单位操作(比如将位设置为1或0，或者获取它的值)和对位组的操作(例如在给定的位范围内计算集合位的数量)。

位图最大的优点之一是，在存储信息时，位图通常可以节省大量空间。例如，在一个用增量用户id表示不同用户的系统中，仅使用512 MB内存就可以记住40亿用户的单个位信息(例如，知道用户是否想接收时事通讯)。  （参考链接：http://www.redis.cn/topics/data-types-intro.html#bitmaps）

## 设置某位上的值
语法 `SETBIT key offset value`
offset：是偏移量，从0开始，从左到右
value：只能是0 或者 1
例子：
当我们使用命令 setbit key (0,2,5,9,12) 1后，它的具体表示为：

|byte|bit0|bit1|bit2|bit3|bit4|bit5|bit6|bit7|
|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
|byte0|1|0|1|0|0|1|0|0|
|byte1|0|1|0|0|1|0|0|0|

## 获取某位上的值
语法`GETBIT key offset`
offset:为查询的位
![在这里插入图片描述](https://archerzdip.github.io/assets/post/20190312192108387.png)

使用get获取：
![在这里插入图片描述](https://img-blog.csdnimg.cn/20190312220856844.png)

\xa4H = \xa4 \x48 (因为 0x48的ascii码为H）
二进制为： 1 0 1 0 0 1 0 0    0 1 0 0 1 0 0 0 即分别在0 2 5 9 12 位置上为1.

## 统计范围内1的个数

语法`BITCOUNT key [start end]`
获取位图指定位置（start到end，单位是字节，如果不指定就是获取全部）位值为1的个数。
例子1：不指定开始结束
![在这里插入图片描述](https://img-blog.csdnimg.cn/20190312221547841.png)

例子2：只获取第一个字节里的个数
![在这里插入图片描述](https://img-blog.csdnimg.cn/2019031222163979.png)

例子3：只获取第二个字节里的个数

![在这里插入图片描述](https://img-blog.csdnimg.cn/20190312221739480.png?x-oss-process=image/watermark,type_ZmFuZ3poZW5naGVpdGk,shadow_10,text_aHR0cHM6Ly9ibG9nLmNzZG4ubmV0L3pkaXAxMjM=,size_16,color_FFFFFF,t_70)
0表示从左到右，-1表示从右到左,所以BITCOUNT key 0 -1 为统计所有的。

## 查找第一次0或者1出现的位置
语法`BITPOS key bit [start end]`
例子1：查找第一0出现的位置
![在这里插入图片描述](https://img-blog.csdnimg.cn/20190312222805822.png)

例子2：在第2个字节中查找`1`第一次出现的位置
![在这里插入图片描述](https://img-blog.csdnimg.cn/20190312222918904.png)

**注：这里的start，end也是字节**

## BITOP
语法`BITOP operation desckey key [key ...]`
operation：支持AND OR XOR NOT四种操作
- BITOP AND destkey srckey1 … srckeyN ，对一个或多个 key 求逻辑与，并将结果保存到 destkey
- BITOP OR destkey srckey1 … srckeyN，对一个或多个 key 求逻辑或，并将结果保存到 destkey
- BITOP XOR destkey srckey1 … srckeyN，对一个或多个 key 求逻辑异或，并将结果保存到 destkey
- BITOP NOT destkey srckey，对给定 key 求逻辑非，并将结果保存到 destkey
例子1：对key进行逻辑非
![在这里插入图片描述](https://img-blog.csdnimg.cn/2019031222352359.png?x-oss-process=image/watermark,type_ZmFuZ3poZW5naGVpdGk,shadow_10,text_aHR0cHM6Ly9ibG9nLmNzZG4ubmV0L3pkaXAxMjM=,size_16,color_FFFFFF,t_70)

[\xb7 = \x5b \xb7
例子2：对desckey和key进行逻辑与
![在这里插入图片描述](https://img-blog.csdnimg.cn/2019031222353816.png?x-oss-process=image/watermark,type_ZmFuZ3poZW5naGVpdGk,shadow_10,text_aHR0cHM6Ly9ibG9nLmNzZG4ubmV0L3pkaXAxMjM=,size_16,color_FFFFFF,t_70)

结果恰好为 0 0 0 0 0 0 0 0   0 0 0 0 0 0 0 0 


## 使用场景
参考：（`https://www.cnblogs.com/matengfei123/p/9055815.html`）

### 使用场景一：用户签到
很多网站都提供了签到功能(这里不考虑数据落地事宜)，并且需要展示最近一个月的签到情况，如果使用bitmap我们怎么做？
```php
<?php
/**
 * Created by PhpStorm.
 * User: archerzdip
 * Date: 2019-03-12
 * Time: 22:41
 */

$redis = new Redis();
$redis->connect('127.0.0.1');


//用户uid
$uid = 1;

//记录有uid的key
$cacheKey = sprintf("sign_%d", $uid);

//开始有签到功能的日期
$startDate = '2017-01-01';

//今天的日期
$todayDate = '2017-01-21';

//计算offset
$startTime = strtotime($startDate);
$todayTime = strtotime($todayDate);
$offset = floor(($todayTime - $startTime) / 86400);

echo "今天是第{$offset}天" . PHP_EOL;

//签到
//一年一个用户会占用多少空间呢？大约365/8=45.625个字节，好小，有木有被惊呆？
$redis->setBit($cacheKey, $offset, 1);

//查询签到情况
$bitStatus = $redis->getBit($cacheKey, $offset);
echo 1 == $bitStatus ? '今天已经签到啦' : '还没有签到呢';
echo PHP_EOL;

//计算总签到次数
echo $redis->bitCount($cacheKey) . PHP_EOL;

/**
 * 计算某段时间内的签到次数
 * 很不幸啊,bitCount虽然提供了start和end参数，但是这个说的是字符串的位置，而不是对应"位"的位置
 * 幸运的是我们可以通过get命令将value取出来，自己解析。并且这个value不会太大，上面计算过一年一个用户只需要45个字节
 * 给我们的网站定一个小目标，运行30年，那么一共需要1.31KB(就问你屌不屌？)
 */
//这是个错误的计算方式
echo $redis->bitCount($cacheKey, 0, 20) . PHP_EOL;
```

### 使用场景二：统计活跃用户
使用时间作为cacheKey，然后用户ID为offset，如果当日活跃过就设置为1
那么我该如果计算某几天/月/年的活跃用户呢(暂且约定，统计时间内只有有一天在线就称为活跃)，有请下一个redis的命令
命令 BITOP operation destkey key [key ...]
说明：对一个或多个保存二进制位的字符串 key 进行位元操作，并将结果保存到 destkey 上。
说明：BITOP 命令支持 AND 、 OR 、 NOT 、 XOR 这四种操作中的任意一种参数。
```php
<?php
/**
 * Created by PhpStorm.
 * User: archerzdip
 * Date: 2019-03-12
 * Time: 22:55
 */

$redis = new Redis();

$redis->connect('127.0.0.1');

// 日期对应的活跃用户
$data = [
    '2019-03-01' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
    '2019-03-02' => [1, 2, 3, 4, 5, 6, 7, 8,],
    '2019-03-03' => [1, 2, 3, 4, 5, 6],
    '2019-03-04' => [1, 2, 3, 4],
    '2019-03-05' => [1, 2],
];

//批量设置活跃状态

foreach ($data as $data => $uids) {
    $cacheKey = sprintf("stat_%s", $data);
    foreach ($uids as $uid) {
        $redis->setBit($cacheKey, $uid , 1);
    }
}

$redis->bitOp('AND', 'stat', 'stat_2019-03-01', 'stat_2019-03-02', 'stat_2019-03-03') . PHP_EOL;
//总活跃用户：6

echo "总活跃用户：" . $redis->bitCount('stat') . PHP_EOL;

$redis->bitOp('AND', 'stat1', 'stat_2019-03-01', 'stat_2019-03-02', 'stat_2019-03-04') . PHP_EOL;
//总活跃用户：4
echo "总活跃用户：" . $redis->bitCount('stat1') . PHP_EOL;

$redis->bitOp('AND', 'stat2', 'stat_2019-03-01', 'stat_2019-03-02') . PHP_EOL;
//总活跃用户：8

echo "总活跃用户：" . $redis->bitCount('stat2') . PHP_EOL;
```

假设当前站点有5000W用户，那么一天的数据大约为50000000/8/1024/1024=6MB

### 使用场景三：用户在线状态
前段时间开发一个项目，对方给我提供了一个查询当前用户是否在线的接口。不了解对方是怎么做的，自己考虑了一下，使用bitmap是一个节约空间效率又高的一种方法，只需要一个key，然后用户ID为offset，如果在线就设置为1，不在线就设置为0，和上面的场景一样，5000W用户只需要6MB的空间。
```php
<?php
/**
 * Created by PhpStorm.
 * User: archerzdip
 * Date: 2019-03-12
 * Time: 23:11
 */

$redis = new Redis();

$redis->connect('127.0.0.1');


//批量设置在线状态
$uids = range(1, 500000);
foreach($uids as $uid) {
    $redis->setBit('online', $uid, $uid % 2);
}

//一个一个获取状态
$uids = range(1, 500000);

$startTime = microtime(true);
/**
foreach($uids as $uid) {
    echo $redis->getBit('online', $uid) . PHP_EOL;
}
 */
echo $redis->bitCount('online');

$endTime = microtime(true);
//在我的电脑上，获取50W个用户的状态需要25秒

echo "total:" . ($endTime - $startTime) . "s";

```

其实BitMap可以运用的场景很多很多(当然也会受到一些限制)，思维可以继续扩散~欢迎小伙伴给我留言探讨 ~


==================================
由于本人水平有限，文章在表述和代码方面如有不妥之处，欢迎批评指正。