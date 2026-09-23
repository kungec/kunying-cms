<?php
/**
 * 坤影CMS 引导文件
 * KunYing CMS
 */
define('KY_PATH', __DIR__ . '/..');
define('KY_VERSION', '1.0.33');
define('KY_RELEASE', 20260924);

error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_WARNING);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
date_default_timezone_set('Asia/Shanghai');

// 关键安全响应头
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-XSS-Protection: 1; mode=block');
    header_remove('X-Powered-By');
}

require __DIR__ . '/Func.php';
require __DIR__ . '/Db.php';
require __DIR__ . '/Core.php';
require __DIR__ . '/Security.php';
require __DIR__ . '/Auth.php';

// 控制器与Lib自动加载
spl_autoload_register(function ($class) {
    if (strpos($class, '\\') !== false) return;
    static $libs = ['Http', 'Captcha', 'Mailer', 'Verify', 'Pay', 'Collector', 'Addon', 'License', 'Updater'];
    if (in_array($class, $libs, true)) {
        $file = KY_PATH . '/ky/Lib/' . $class . '.php';
        if (is_file($file)) require $file;
        return;
    }
    if (substr($class, -10) === 'Controller' || $class === 'ApiBase') {
        $file = KY_PATH . '/ky/Controller/' . $class . '.php';
        if (is_file($file)) require $file;
    }
});
