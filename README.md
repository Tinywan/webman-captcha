# webman captcha plugin

[![Latest Stable Version](http://poser.pugx.org/tinywan/captcha/v)](https://packagist.org/packages/tinywan/captcha) [![Total Downloads](http://poser.pugx.org/tinywan/captcha/downloads)](https://packagist.org/packages/tinywan/captcha) [![Latest Unstable Version](http://poser.pugx.org/tinywan/captcha/v/unstable)](https://packagist.org/packages/tinywan/captcha) [![License](http://poser.pugx.org/tinywan/captcha/license)](https://packagist.org/packages/tinywan/captcha)

base64 image verification captcha library for webman plugin

## 安装

```shell
composer require tinywan/captcha
```

## 使用

### 生成base64

```php
use Tinywan\Captcha\Captcha;

echo Captcha::base64();
```

**输出base64**
```php
data:image/png;base64,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
```

**BASE64转图片**
> 通过以下网址：https://tool.jisuapi.com/base642pic.html

**转图片效果如图**

![captcha.png](captcha.png)

### 验证验证码

```php
use Tinywan\Captcha\Captcha;
use support\Request;

public function check(Request $request)
{
    $code = $request->get('code');
    if (false === Captcha::check($code)) {
        // 验证失败
    };
    // 验证通过
}
```