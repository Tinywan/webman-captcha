<?php
/**
 * @desc Captcha.php 描述信息
 * @author Tinywan(ShaoBo Wan)
 * @date 2022/3/24 21:36
 */
declare(strict_types=1);

namespace Tinywan\Captcha;

use support\Redis;

class Captcha
{
    /**
     * 验证验证码是否正确
     * @access public
     * @param string $code 用户验证码
     * @return bool 用户验证码是否正确
     */
    public static function check(string $code): bool
    {
        $config = config('plugin.tinywan.captcha.app.captcha');
        $cacheKey = $config['prefix'].request()->getRemoteIp();
        if (!Redis::exists($cacheKey)) {
            return false;
        }
        $hash = Redis::hGet($cacheKey,'key');
        $code = mb_strtolower($code, 'UTF-8');
        $res = password_verify($code, $hash);
        if ($res) {
            Redis::del('captcha');
        }
        return $res;
    }

    /**
     * 输出验证码并把验证码的值保存的session中
     * @access public
     * @param array $_config
     * @return string
     * @throws \Exception
     */
    public static function base64(array $_config = [])
    {
        $config = config('plugin.tinywan.captcha.app.captcha');
        if (!empty($_config)) {
            $config = array_merge($config,$_config);
        }
        $generator = self::generate($config);
        // 图片宽(px)
        $config['imageW'] || $config['imageW'] = $config['length'] * $config['fontSize'] * 1.5 + $config['length'] * $config['fontSize'] / 2;
        // 图片高(px)
        $config['imageH'] || $config['imageH'] = $config['fontSize'] * 2.5;
        // 建立一幅 $config['imageW'] x $config['imageH'] 的图像
        $im = imagecreate((int)$config['imageW'], (int) $config['imageH']);
        // 设置背景
        imagecolorallocate($im, $config['bg'][0], $config['bg'][1], $config['bg'][2]);

        // 验证码字体随机颜色
        $color = imagecolorallocate($im, mt_rand(1, 150), mt_rand(1, 150), mt_rand(1, 150));

        // 验证码使用随机字体
        $ttfPath = __DIR__ . '/../assets/' . ($config['useZh'] ? 'zhttfs' : 'ttfs') . '/';

        if (empty($config['fontttf'])) {
            $dir  = dir($ttfPath);
            $ttfs = [];
            while (false !== ($file = $dir->read())) {
                if (substr($file, -4) == '.ttf' || substr($file, -4) == '.otf') {
                    $ttfs[] = $file;
                }
            }
            $dir->close();
            $config['fontttf'] = $ttfs[array_rand($ttfs)];
        }

        $fontttf = $ttfPath . $config['fontttf'];

        if ($config['useImgBg']) {
            self::background($config,$im);
        }

        if ($config['useNoise']) {
            // 绘杂点
            self::writeNoise($config,$im);
        }
        if ($config['useCurve']) {
            // 绘干扰线
            self::writeCurve($config,$im,$color);
        }

        // 绘验证码
        $text = $config['useZh'] ? preg_split('/(?<!^)(?!$)/u', $generator['value']) : str_split($generator['value']); // 验证码

        foreach ($text as $index => $char) {
            $x     = $config['fontSize'] * ($index + 1) * mt_rand((int)1.2, (int)1.6) * ($config['math'] ? 1 : 1.5);
            $y     = $config['fontSize'] + mt_rand(10, 20);
            $angle = $config['math'] ? 0 : mt_rand(-40, 40);
            imagettftext($im, $config['fontSize'], $angle, (int) $x, (int) $y, $color, $fontttf, $char);
        }

        ob_start();
        imagepng($im);
        $content = ob_get_clean();
        imagedestroy($im);

        return 'data:image/png;base64,' . base64_encode($content);
    }

    /**
     * @desc: 创建验证码
     * @param array $config
     * @return array
     * @throws \Exception
     * @author Tinywan(ShaoBo Wan)
     */
    protected static function generate(array $config): array
    {
        $bag = '';
        if ($config['math']) {
            $config['useZh']  = false;
            $config['length'] = 5;
            $x   = random_int(10, 30);
            $y   = random_int(1, 9);
            $bag = "{$x} + {$y} = ";
            $key = $x + $y;
            $key .= '';
        } else {
            if ($config['useZh']) {
                $characters = preg_split('/(?<!^)(?!$)/u', $config['zhSet']);
            } else {
                $characters = str_split($config['codeSet']);
            }

            for ($i = 0; $i < $config['length']; $i++) {
                $bag .= $characters[rand(0, count($characters) - 1)];
            }

            $key = mb_strtolower($bag, 'UTF-8');
        }

        $hash = password_hash($key, PASSWORD_BCRYPT, ['cost' => 10]);
        Redis::hMSet($config['prefix'].request()->getRemoteIp(), ['key' => $hash,]);
        return ['value' => $bag,'key'   => $hash];
    }

    /**
     * @desc: 画一条由两条连在一起构成的随机正弦函数曲线作干扰线(你可以改成更帅的曲线函数)
     * @param array $config
     */
    protected static function writeCurve(array $config,$im,$color): void
    {
        $py = 0;
        // 曲线前部分
        $A = mt_rand(1, (int) ($config['imageH'] / 2)); // 振幅
        $b = mt_rand(-(int) ($config['imageH'] / 4), (int) ($config['imageH'] / 4)); // Y轴方向偏移量
        $f = mt_rand(-(int) ($config['imageH'] / 4), (int) ($config['imageH'] / 4)); // X轴方向偏移量
        $T = mt_rand((int) $config['imageH'], (int) ($config['imageW'] * 2)); // 周期
        $w = (2 * M_PI) / $T;

        $px1 = 0; // 曲线横坐标起始位置
        $px2 = mt_rand((int) ($config['imageW'] / 2), (int)$config['imageW']); // 曲线横坐标结束位置

        for ($px = $px1; $px <= $px2; $px = $px + 1) {
            if (0 != $w) {
                $py = $A * sin($w * $px + $f) + $b + $config['imageH'] / 2; // y = Asin(ωx+φ) + b
                $i  = (int) ($config['fontSize'] / 5);
                while ($i > 0) {
                    imagesetpixel($im, (int) $px + $i, (int) $py + $i, (int)$color); // 这里(while)循环画像素点比imagettftext和imagestring用字体大小一次画出（不用这while循环）性能要好很多
                    $i--;
                }
            }
        }

        // 曲线后部分
        $A   = mt_rand(1, (int) ($config['imageH'] / 2)); // 振幅
        $f   = mt_rand(-(int) ($config['imageH'] / 4), (int) ($config['imageH'] / 4)); // X轴方向偏移量
        $T   = mt_rand((int) $config['imageH'], (int) ($config['imageW'] * 2)); // 周期
        $w   = (2 * M_PI) / $T;
        $b   = $py - $A * sin($w * $px + $f) - $config['imageH'] / 2;
        $px1 = $px2;
        $px2 = $config['imageW'];

        for ($px = $px1; $px <= $px2; $px = $px + 1) {
            if (0 != $w) {
                $py = $A * sin($w * $px + $f) + $b + $config['imageH'] / 2; // y = Asin(ωx+φ) + b
                $i  = (int) ($config['fontSize'] / 5);
                while ($i > 0) {
                    imagesetpixel($im, (int) $px + $i, (int) $py + $i, (int)$color);
                    $i--;
                }
            }
        }
    }

    /**
     * @desc: 画杂点  往图片上写不同颜色的字母或数字
     * @param array $config
     * @param $im
     * @author Tinywan(ShaoBo Wan)
     */
    protected static function writeNoise(array $config,$im): void
    {
        $codeSet = 'tinywan20222345678abcdefhijkmnpqrstuvwxyz';
        for ($i = 0; $i < 10; $i++) {
            //杂点颜色
            $noiseColor = imagecolorallocate($im, mt_rand(150, 225), mt_rand(150, 225), mt_rand(150, 225));
            for ($j = 0; $j < 5; $j++) {
                // 绘杂点
                imagestring($im, 5, mt_rand(-10, (int) $config['imageW']), mt_rand(-10, (int) $config['imageH']), $codeSet[mt_rand(0, 29)], (int) $noiseColor);
            }
        }
    }

    /**
     * @desc: 绘制背景图片 注：如果验证码输出图片比较大，将占用比较多的系统资源
     * @param array $config
     * @param $im
     * @author Tinywan(ShaoBo Wan)
     */
    protected static function background(array $config, $im): void
    {
        $path = __DIR__ . '/../assets/bgs/';
        $dir  = dir($path);

        $bgs = [];
        while (false !== ($file = $dir->read())) {
            if ('.' != $file[0] && substr($file, -4) == '.jpg') {
                $bgs[] = $path . $file;
            }
        }
        $dir->close();

        $gb = $bgs[array_rand($bgs)];

        [$width, $height] = @getimagesize($gb);
        $bgImage = @imagecreatefromjpeg($gb);
        @imagecopyresampled($im, $bgImage, 0, 0, 0, 0, (int) $config['imageW'], (int) $config['imageH'], $width, $height);
        @imagedestroy($bgImage);
    }
}