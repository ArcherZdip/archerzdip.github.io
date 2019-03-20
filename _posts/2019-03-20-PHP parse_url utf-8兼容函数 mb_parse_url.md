---
title: PHP parse_url utf-8兼容函数 mb_parse_url
layout: post
category: blog
tags: |-
  PHP
  Strings
---

@[TOC]
## 简介
使用parse_url 对以下url进行解析时会出现乱码,（这种URL正常是被允许的，应该在后端进行命名）
```php
$url = 'http://www.baidu.com/a/b/c/新建 Microsoft Word 文档.docx';
print_r(parse_url($url));
```

结果：
![在这里插入图片描述](https://img-blog.csdnimg.cn/20190311124536173.png?x-oss-process=image/watermark,type_ZmFuZ3poZW5naGVpdGk,shadow_10,text_aHR0cHM6Ly9ibG9nLmNzZG4ubmV0L3pkaXAxMjM=,size_16,color_FFFFFF,t_70)

## mb_parse_url
在(http://php.net/manual/zh/function.parse-url.php)中有相关的兼容UTF-8的方法，但是感觉不能完全兼容parse_url。
```php
if (!function_exists('mb_parse_url')) {
    /**
     * UTF-8 aware parse_url() replacement.
     *
     * @param $url
     * @param int $component
     * @return mixed|string
     */
    function mb_parse_url($url, $component = -1)
    {
        $encodedUrl = preg_replace_callback(
            '%[^:/?#&=\.]+%usD',
            function ($matches) {
                return urlencode($matches[0]);
            },
            $url
        );

        $components = parse_url($encodedUrl, $component);

        if (is_array($components)) {
            foreach ($components as &$part) {
                if (is_string($part)) {
                    $part = urldecode($part);
                }
            }
        } else if (is_string($components)) {
            $components = urldecode($components);
        }

        return $components;
    }
}
```

结果:
![在这里插入图片描述](https://img-blog.csdnimg.cn/20190311125120445.png?x-oss-process=image/watermark,type_ZmFuZ3poZW5naGVpdGk,shadow_10,text_aHR0cHM6Ly9ibG9nLmNzZG4ubmV0L3pkaXAxMjM=,size_16,color_FFFFFF,t_70)

并可以传入parse_url中的相关参数`component`,指定 PHP_URL_SCHEME、 PHP_URL_HOST、 PHP_URL_PORT、 PHP_URL_USER、 PHP_URL_PASS、 PHP_URL_PATH、 PHP_URL_QUERY 或 PHP_URL_FRAGMENT 的其中一个来获取 URL 中指定的部分的 string。 （除了指定为 PHP_URL_PORT 后，将返回一个 integer 的值）。
![在这里插入图片描述](https://img-blog.csdnimg.cn/20190311125318537.png)

==================================
由于本人水平有限，文章在表述和代码方面如有不妥之处，欢迎批评指正。