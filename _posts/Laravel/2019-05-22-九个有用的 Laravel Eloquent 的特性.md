---
title: 九个有用的 Laravel Eloquent 的特性
layout: post
category: blog
tags: |-
  PHP
  Laravel
  Eloquent
---



# 九个有用的 Laravel Eloquent 的特性

对于使用 Laravel 的开发者来说，可能都会惊叹于 Eloquent Model 的强大，但是在强大的表面之下，其实还是有很多鲜为人知的特性的，本文即来分享十个 Laravel Eloquent 的强大特性。

1.更强大的 find() 方法

很多开发者在使用 find() 方法的时候，通常就只是在这里传入一个 ID 的参数，其实我们也是可以传入第二个参数的：在 find() 方法中指定需要查找的字段
```php
$user = App\User::find(1, ['name', 'age']);
$user = App\User::findOrFail(1, ['name', 'age']);
// 这里面的 name 和 age 字段就是制定只查找这两个字段
```
2.克隆 Model

直接使用 replicate() 方法即可，这样我们就很容易地创建一个 Model 的副本：
```php
$user = App\User::find(1);
$newUser = $user->replicate();
$newUser->save();
// 这样，$newUser 和 $user 的基本数据就是一样的
```

3.检查 Model 是否相同

使用 is() 方法检查两个 Model 的 ID 是否一致，是否在同一个表中：
```php
$user = App\User::find(1);
$sameUser = App\User::find(1);
$diffUser = App\User::find(2);
$user->is($sameUser);       // true
$user->is($diffUser);       // false
```

4.在关联模型中同时保存数据

使用 push() 你可以在保存模型数据的同时，将所关联的数据也保存下来:
```php
class User extends Model
{
    public function phone()
    {
        return $this->hasOne('App\Phone');
    }
}
$user = User::first();
$user->name = "GeiXue";
$user->phone->number = '1234567890';
$user->push(); 
// 最后这一行 push() 会将 user 的数据和 phone 的数据同时更新到数据库中
```

5.自定义 deleted_at 字段

如果你使用过 Laravel 的软删除 Soft Delete 的话，你应该就知道其实 Laravel 在标记一个记录为已删除的状态其实是用 deleted_at 这个字段来维护的，其实你是可以自定义这个字段的：
```php
class User extends Model
{
    use SoftDeletes;
     * The name of the "deleted at" column.
     *
     * @var string
     */
    const DELETED_AT = 'deleted_date';
}
```
或者你这样自定义也可以：
```php
class User extends Model
{
    use SoftDeletes;
    public function getDeletedAtColumn()
    {
        return 'deleted_date';
    }
}
```

6.获取已修改的 Model 属性

使用 getChanges() 方法获取已被修改的属性：
```php
$user->getChanges()
  
  [
     "name" => "GeiXue",
  ]
```

7.检查 Model 是否被修改

使用 isDirty() 方法就可以检测模型中的数据是否被修改：
```php
$user = App\User::first();
$user->isDirty();          //false
$user->name = "GeiXue";
$user->isDirty();          //true
```

在使用 isDirty() 的时候，你也可以直接检测某个属性是否被修改：
```php
$user->isDirty('name');    //true
$user->isDirty('age');     //false
```

8.获取 Model 的原始数据

在给 Model 的属性赋予新值的时候，你可以通过 getOriginal() 来获取原来的值：
```php
$user = App\User::first();
$user->name;                   //JellyBool
$user->name = "GeiXue";         //GeiXue
$user->getOriginal('name');    //JellyBool
$user->getOriginal();          //Original $user record
```

9.刷新 Model 的数据

使用 refresh() 刷新 Model 的数据，这在你使用 tinker 的时候特别有用：
```php
$user = App\User::first();
$user->name;               // JellyBool
// 这个时候在其他地方，该用户的名字被更新为 GeiXue，你可以使用 refresh 来刷新，而不用退出 tinker
$user->refresh(); 
$user->name;              // GeiXue
```