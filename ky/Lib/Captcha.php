<?php
/**
 * 图形验证码(GD实现)
 */
if (!defined('KY_PATH')) exit('Access denied');

class Captcha
{
    /**
     * 输出验证码图片并存入session
     */
    public static function output(string $scene = 'default'): void
    {
        Security::session();
        $code = '';
        $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        for ($i = 0; $i < 4; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $_SESSION['_captcha_' . $scene] = strtolower($code);
        $_SESSION['_captcha_time_' . $scene] = time();

        $w = 130; $h = 42;
        $img = imagecreatetruecolor($w, $h);
        $bg = imagecolorallocate($img, 245, 246, 248);
        imagefilledrectangle($img, 0, 0, $w, $h, $bg);
        // 干扰线
        for ($i = 0; $i < 4; $i++) {
            $c = imagecolorallocate($img, random_int(160, 220), random_int(160, 220), random_int(160, 220));
            imageline($img, random_int(0, $w), random_int(0, $h), random_int(0, $w), random_int(0, $h), $c);
        }
        // 字符
        for ($i = 0; $i < 4; $i++) {
            $c = imagecolorallocate($img, random_int(30, 90), random_int(30, 90), random_int(120, 180));
            imagestring($img, 5, 12 + $i * 28, random_int(8, 20), $code[$i], $c);
        }
        // 噪点
        for ($i = 0; $i < 120; $i++) {
            $c = imagecolorallocate($img, random_int(120, 220), random_int(120, 220), random_int(120, 220));
            imagesetpixel($img, random_int(0, $w - 1), random_int(0, $h - 1), $c);
        }
        header('Content-Type: image/png');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        imagepng($img);
        imagedestroy($img);
        exit;
    }

    /**
     * 校验验证码(校验后立即失效)
     */
    public static function check(string $input, string $scene = 'default'): bool
    {
        Security::session();
        $key = '_captcha_' . $scene;
        $expect = strtolower((string)($_SESSION[$key] ?? ''));
        unset($_SESSION[$key]);
        if ($expect === '' || time() - (int)($_SESSION['_captcha_time_' . $scene] ?? 0) > 300) return false;
        return strtolower(trim($input)) === $expect;
    }
}
