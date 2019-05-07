---
title: Laravel实现beforeAction和afterAction
layout: post
category: blog
tags: |-
  PHP
  Laravel
  beforeAction
  afterAction
---





# Laravel实现beforeAction和afterActio



进入 laravel 的核心文件 **vendor\laravel\framework\src\Illuminate\Routing\Controller.php** 查找到方法 **callAction**：

```php
	/**
     * Execute an action on the controller.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function callAction($method, $parameters)
    {
        return call_user_func_array([$this, $method], $parameters);
    }
```



在App\Http\Controllers\Controller.php里将**callAction**重写，如下：

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * Execute an action on the controller.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function callAction($method, $parameters)
    {
        // beforeAction 不允许抛出异常
        if(method_exists($this,'beforeAction')) {
            call_user_func_array([$this, 'beforeAction'], $parameters);
        }
        $return = null;
        $exception = null;
        try {
            $return = call_user_func_array([$this, $method], $parameters);
        } catch (\Throwable $exception) {
            $exception = $exception;
        }
        // 抛出异常传递到afterAction中
        $parameters[0]->merge(['exception' => $exception]);

        // 在afterAction里处理未完成的数据和异常记录
        if(method_exists($this,'afterAction')) {
            call_user_func_array([$this, 'afterAction'], $parameters);
        }

       return $return;
    }
}

```



在控制器中测试：

```php
<?php

namespace App\Http\Controllers\Demo;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TestController extends Controller
{

    public function index(Request $request)
    {
        echo 'index action' . "<br>";
        throw new \Exception("index exception");
    }

    public function beforeAction(Request $request)
    {
        //dd($request);
        echo 'before action' . "<br>";
    }

    public function afterAction(Request $request)
    {
        if (strlen($request->input('exception'))) {
            $e = $request->input('exception');
            echo $e->getMessage() . "<br>";
        }
        echo 'after action' . "<br>";
    }
}

```



结果为：

![](/assets/post/image-20190507151439951.png)



可以看到基本实现beforeAction和afterAction的功能，但是假如在beforeAction抛出异常，程序则不能继续走到下面，所以只能是根据具体业务就行相应修改，在相关地方进行异常捕获，这里只是提出一种实现方法。