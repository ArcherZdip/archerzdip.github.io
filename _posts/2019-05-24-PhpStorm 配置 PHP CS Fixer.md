---
title: PhpStorm 配置 PHP CS Fixer
layout: post
category: blog
tags: |-
  PHP
  PHPSTORM
  PHP-CS-FIXER
---



# PhpStorm 配置 PHP CS Fixer



PHP CS Fixer 是一个非常好用的 PHP 代码编码风格纠正工具.



## 安装PHP-CS-Fixer

地址：<https://github.com/FriendsOfPHP/PHP-CS-Fixer>

安装成功后：

```bash
☁  ~  php-cs-fixer
PHP CS Fixer 2.14.0 Sunrise by Fabien Potencier and Dariusz Ruminski (b788ea0)

Usage:
  command [options] [arguments]

Options:
  -h, --help            Display this help message
  -q, --quiet           Do not output any message
  -V, --version         Display this application version
      --ansi            Force ANSI output
      --no-ansi         Disable ANSI output
  -n, --no-interaction  Do not ask any interactive question
  -v|vv|vvv, --verbose  Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

Available commands:
  describe     Describe rule / ruleset.
  fix          Fixes a directory or a file.
  help         Displays help for a command
  list         Lists commands
  readme       Generates the README content, based on the fix command help.
  self-update  [selfupdate] Update php-cs-fixer.phar to the latest stable version.
```



## 配置到PHPSTORM中

点击settings->Tools->External Tools->Add

![image-20190524142828942](/assets/post/image-20190524142828942.png)



- Program:  php-cs-fixer的bin目录
- Arguments: 执行参数，配置如下 `fix $FileDir$/$FileName$ --config=/Users/zhanglingyu/.php_cs.dist`
- Working directory: 工作目录配置成 `$ProjectFileDir$`

`.php_cs.dist` 为fix参数，具体可参照git地址有详细说明

```bash
☁  ~  cat .php_cs.dist
<?php
return PhpCsFixer\Config::create()
    ->setRiskyAllowed(true)
    ->setRules([
        'align_multiline_comment' => true,
        'no_trailing_whitespace' => true,
        'no_short_echo_tag' => true,
        'no_unused_imports' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => ['sortAlgorithm' => 'length']
    ])
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->exclude('tests/')
            ->in(__DIR__)
    )
;
```



## 配置PHPSTORM快键键

点击settings->Keymap->External Tools->PHP-CS-FIXER配置快捷键，比如我配置成`control+command+B`.

![image-20190524143416753](/assets/post/image-20190524143416753.png)