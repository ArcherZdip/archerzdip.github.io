---
title: PHP Laravel 身份证号码生成faker
layout: post
category: blog
tags: |-
  PHP
  Laravel
---

<!-- TOC -->

- [简介](#简介)
- [安装](#安装)
- [使用](#使用)
- [console](#console)

<!-- /TOC -->

## 简介
最近在写测试用例的时候经常会用到身份证号码，但是随便写在`Validate`又不能通过，才发现身份证号码有自己的生成规则，于是在研究后编写了个Laravel的扩展包，用于生成和验证居民身份证号码。
**文献：**
[1]:https://baike.baidu.com/item/%E5%B1%85%E6%B0%91%E8%BA%AB%E4%BB%BD%E8%AF%81%E5%8F%B7%E7%A0%81/3400358?fr=aladdin 居民身份证号码规则
[2]:https://packagist.org/packages/archerzdip/laravel-identity Laravel的身份证号码生成器地址

## 安装
```
composer require archerzdip/laravel-identity
// 或者

// composer.json
"archerzdip/laravel-identity":"dev-master"
// composer update
composer update
```
发布
`php artisan vendor:publish --provider="ArcherZdip\Identity\IdentityServiceProvice"`

## 使用
可以使用identity()帮助函数,或者app('identity_faker')来获取身份证号码 :
```php
// Get one id number value
identity();

app('identity_faker')->one();

// Get multiple id number value
identity(10);

app('identity_faker')->limit(10)->get();

// 可以设置省份、性别、生日来获取特定身份证号码
app('identity_faker')->province('北京市')->sex('男')->birth('2018-02-09')->one();
```
可以使用identity_verity()来验证身份号码的有效性:
```php
identity_verity(123456);  // false
// or
ArcherZdip\Identity\VerityChineseIDNumber::isValid($idNumer);
```

## console
获取身份证号码：
```
$ php artisan identity:get --help                   
Usage:
  identity:get [options]

Options:
  -l, --limit[=LIMIT]        Get identity number
  -P, --province[=PROVINCE]  Set province,like `北京市`
  -S, --sex[=SEX]            Set Sex,like `男`
  -B, --birth[=BIRTH]        Set birth,like xxxx-xx-xx

```
可以使用-l 指定获取条数，-P 指定省份，-S 指定性别，-B 指定生日
![在这里插入图片描述](https://archerzdip.github.io/assets/post/20190307152137134.png)
![在这里插入图片描述](https://archerzdip.github.io/assets/post/20190307152244723.png)

验证身份证号码正确性：
```
$ php artisan identity:verity --help            
Usage:
  identity:verity <idnumber>

Arguments:
  idnumber              Chinese ID number string

```
如：
正确的号码：
![在这里插入图片描述](https://archerzdip.github.io/assets/post/20190307152350211.png)

错误的号码：
![在这里插入图片描述](https://archerzdip.github.io/assets/post/2019030715241350.png)