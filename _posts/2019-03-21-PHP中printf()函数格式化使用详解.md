---
title: PHP中printf()函数格式化使用详解
layout: post
category: blog
tags: |-
  PHP
  Strings
---
<!-- TOC -->

- [格式组成说明](#格式组成说明)
- [格式字符说明](#格式字符说明)
- [特殊说明](#特殊说明)
- [例子](#例子)
    - [printf](#printf)
    - [sprintf](#sprintf)
    - [vsprintf - 返回格式化字符串](#vsprintf---返回格式化字符串)
    - [vsprintf扩展功能函数vnsprintf](#vsprintf扩展功能函数vnsprintf)

<!-- /TOC -->
## 格式组成说明
- %：表示格式说明的起始符号，不可缺少。 
- -：有-表示左对齐输出，如省略表示右对齐输出。 
- 0：有0表示指定空位填0,如省略表示指定空位不填。 
- m.n：m指域宽，即对应的输出项在输出设备上所占的字符数。N指精度。用于说明输出的实型数的小数位数。为指定n时，隐含的精度为n=6位。 
- l或h:l对整型指long型，对实型指double型。h用于将整型的格式字符修正为short型
- '：可以用单引号(')前缀来指定替代填充字符
- +:强制将符号(-或+)用于数字。默认情况下，如果数字是负数，则只对其使用-号。这个说明符强制正数也要有+号。

## 格式字符说明
格式字符用以指定输出项的数据类型和输出格式。
- b: 输出二级制格式
- d：用以输出十进制整数  
%d：按整型数据的实际长度输出。   
%md：m为指定的输出字段的宽度。如果数据的位数小于m，则左端补以空格，若大于m，则按实际位数输出。   
%ld：输出长整型数据。   
- o: 以无符号八进制形式输出整数。对长整型可以用"%lo"格式输出。同样也可以指定字段宽度用“%mo”格式输出
- x: 16进制形式输出，对长整型可以用"%lx"格式输出。同样也可以指定字段宽度用"%mx"格式输出
- u：以无符号十进制形式输出整数。对长整型可以用"%lu"格式输出。同样也可以指定字段宽度用“%mu”格式输出
- c：输出一个字符
- s：输出字符串
%s：例如:printf("%s", "CHINA")输出"CHINA"字符串（不包括双引号）。   
%ms：输出的字符串占m列，如字符串本身长度大于m，则突破获m的限制,将字符串全部输出。若串长小于m，则左补空格。   
%-ms：如果串长小于m，则在m列范围内，字符串向左靠，右补空格。   
%m.ns：输出占m列，但只取字符串中左端n个字符。这n个字符输出在m列的右侧，左补空格。   
%-m.ns：其中m、n含义同上，n个字符输出在m列范围的左侧，右补空格。如果n>m，则自动取n值，即保证n个字符正常输出。    
- f：用来输出实数（包括单、双精度），以小数形式输出。有以下几种用法： 
%f：不指定宽度，整数部分全部输出并输出6位小数。   
%m.nf：输出共占m列，其中有n位小数，如数值宽度小于m左端补空格。   
%-m.nf：输出共占n列，其中有n位小数，如数值宽度小于m右端补空格。   
- e：以指数形式输出实数。可用以下形式： 
%e：数字部分（又称尾数）输出6位小数，指数部分占5位或4位。   
%m.ne和%-m.ne：m、n和”-”字符含义与前相同。此处n指数据的数字部分的小数位数，m表示整个输出数据所占的宽度。   
- g：自动选f格式或e格式中较短的一种输出，且不输出无意义的零。  


## 特殊说明
如果想输出字符"%",则应该在“格式控制”字符串中用连续两个%表示，如: 
```php
printf("%f%%", 1.0/3); // 输出0.333333%。
```

## 例子
### printf
说明 `printf ( string $format [, mixed $args [, mixed $... ]] ) : int`
例子：
```php
printf("ni hao %s and %d\n", 'holle world', 123);  //ni hao holle world and 123

printf("ni hao %2\$s and %1\$s \n", 'holle world', 'holle world2');  //ni hao holle world2 and holle world

printf("%04d", 2);  //0002
```

### sprintf
说明 `sprintf ( string $format [, mixed $... ] ) : string`
```php
$num = 5;
$location = 'tree';

$format = 'There are %d monkeys in the %s';
echo sprintf($format, $num, $location);

// 指定顺序
$format = 'The %2$s contains %1$d monkeys';
echo sprintf($format, $num, $location);

echo PHP_EOL;

// 填充
echo sprintf("%'.9d\n", 123);
echo sprintf("%'*9d\n", 123);

echo sprintf("%04d", 123);

echo PHP_EOL;

$format = 'The %2$s contains %1$04d monkeys';
echo sprintf($format, $num, $location);
```
Result:
```
There are 5 monkeys in the treeThe tree contains 5 monkeys
......123
******123
0123
The tree contains 0005 monkeys
```

```php
<?php
$n =  43951789;
$u = -43951789;
$c = 65; // ASCII 65 is 'A'

// notice the double %%, this prints a literal '%' character
printf("%%b = '%b'\n", $n); // binary representation
printf("%%c = '%c'\n", $c); // print the ascii character, same as chr() function
printf("%%d = '%d'\n", $n); // standard integer representation
printf("%%e = '%e'\n", $n); // scientific notation
printf("%%u = '%u'\n", $n); // unsigned integer representation of a positive integer
printf("%%u = '%u'\n", $u); // unsigned integer representation of a negative integer
printf("%%f = '%f'\n", $n); // floating point representation
printf("%%o = '%o'\n", $n); // octal representation
printf("%%s = '%s'\n", $n); // string representation
printf("%%x = '%x'\n", $n); // hexadecimal representation (lower-case)
printf("%%X = '%X'\n", $n); // hexadecimal representation (upper-case)

printf("%%+d = '%+d'\n", $n); // sign specifier on a positive integer
printf("%%+d = '%+d'\n", $u); // sign specifier on a negative integer
?>
```
以上例程会输出：
```
%b = '10100111101010011010101101'
%c = 'A'
%d = '43951789'
%e = '4.39518e+7'
%u = '43951789'
%u = '4251015507'
%f = '43951789.000000'
%o = '247523255'
%s = '43951789'
%x = '29ea6ad'
%X = '29EA6AD'
%+d = '+43951789'
%+d = '-43951789'
```
### vsprintf - 返回格式化字符串
说明 `vsprintf ( string $format , array $args ) : string`  
作用与 sprintf() 函数类似，但是接收一个数组参数，而不是一系列可变数量的参数。
例子：
```php
print vsprintf("%04d-%02d-%02d", explode('-', '1988-8-1')); // 1988-08-01

echo PHP_EOL;

$string = "
I like the state of %1\$s <br />
I picked: %2\$d as a number, <br />
I also picked %2\$d as a number again <br />
%3\$s<br /> ";

$returnText = vprintf(  $string, array('Oregon','7','I Love Oregon')  );

echo $returnText;

/* result
I like the state of Oregon <br />
I picked: 7 as a number, <br />
I also picked 7 as a number again <br />
I Love Oregon<br />
*/
```

### vsprintf扩展功能函数vnsprintf
```php
function vnsprintf( $format, array $data)
{
    preg_match_all( '/ (?<!%) % ( (?: [[:alpha:]_-][[:alnum:]_-]* | ([-+])? [0-9]+ (?(2) (?:\.[0-9]+)? | \.[0-9]+ ) ) ) \$ [-+]? \'? .? -? [0-9]* (\.[0-9]+)? \w/x', $format, $match, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
    $offset = 0;
    $keys = array_keys($data);
    foreach ( $match as &$value )
    {
        if ( ( $key = array_search( $value[1][0], $keys) ) !== FALSE || ( is_numeric( $value[1][0]) && ( $key = array_search( (int)$value[1][0], $keys) ) !== FALSE ) ) {
            $len = strlen( $value[1][0]);
            $format = substr_replace( $format, 1 + $key, $offset + $value[1][1], $len);
            $offset -= $len - strlen( $key);
        }
    }
    return vsprintf( $format, $data);
}
```
例子：（来自PHP手册）
```php
$examples = array(
    2.8=>'positiveFloat',    // key = 2 , 1st value
    -3=>'negativeInteger',    // key = -3 , 2nd value
    'my_name'=>'someString'    // key = my_name , 3rd value
);

echo vsprintf( "%%my_name\$s = '%my_name\$s'\n", $examples);    // [unsupported]
echo vnsprintf( "%%my_name\$s = '%my_name\$s'\n", $examples);    // output : "someString"

echo vsprintf( "%%2.5\$s = '%2.5\$s'\n", $examples);        // [unsupported]
echo vnsprintf( "%%2.5\$s = '%2.5\$s'\n", $examples);        // output : "positiveFloat"

echo vsprintf( "%%+2.5\$s = '%+2.5\$s'\n", $examples);        // [unsupported]
echo vnsprintf( "%%+2.5\$s = '%+2.5\$s'\n", $examples);        // output : "positiveFloat"

echo vsprintf( "%%-3.2\$s = '%-3.2\$s'\n", $examples);        // [unsupported]
echo vnsprintf( "%%-3.2\$s = '%-3.2\$s'\n", $examples);        // output : "negativeInteger"

echo vsprintf( "%%2\$s = '%2\$s'\n", $examples);            // output : "negativeInteger"
echo vnsprintf( "%%2\$s = '%2\$s'\n", $examples);            // output : [= vsprintf]

echo vsprintf( "%%+2\$s = '%+2\$s'\n", $examples);        // [unsupported]
echo vnsprintf( "%%+2\$s = '%+2\$s'\n", $examples);        // output : "positiveFloat"

echo vsprintf( "%%-3\$s = '%-3\$s'\n", $examples);        // [unsupported]
echo vnsprintf( "%%-3\$s = '%-3\$s'\n", $examples);        // output : "negativeInteger"
```



PS: 以上大部分来自PHP手册上的例子，算是对一些用法的总结。
==================================
由于本人水平有限，文章在表述和代码方面如有不妥之处，欢迎批评指正。