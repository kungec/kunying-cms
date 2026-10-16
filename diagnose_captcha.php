<?php
/**
 * 坤影CMS 验证码链路自诊断(图形验证码不显示/总报"验证码错误"时使用)
 * 用法: 放到站点根目录,浏览器打开 http://你的域名/diagnose_captcha.php
 * 每一项 PASS/FAIL 都对应链路上的一个断点,FAIL项下方给出修复建议。
 * 诊断完成后请删除本文件。
 */
if (!is_file(__DIR__ . '/data/install.lock')) {
    exit('请先完成系统安装后再运行诊断');
}
require __DIR__ . '/ky/bootstrap.php';
header('Content-Type: text/html; charset=utf-8');
// 仅管理员可用:本工具会暴露环境信息,且不做任何外部自请求(SSRF面为零)
if (Auth::adminId() <= 0) {
    http_response_code(403);
    exit('<meta charset="utf-8"><body style="font-family:sans-serif;background:#101014;color:#eee;display:flex;align-items:center;justify-content:center;height:100vh">仅管理员已登录可用此诊断</body>');
}

$results = [];
function item(string $name, bool $ok, string $detail = '', string $fix = ''): void {
    global $results;
    $results[] = [$name, $ok, $detail, $fix];
    echo '<div style="margin:10px 0;padding:10px 14px;border-left:4px solid ' . ($ok ? '#1f9d55' : '#e5322d') . ';background:' . ($ok ? '#f0f9f4' : '#fdf1f0') . '">'
        . '<b>' . ($ok ? '✔ PASS' : '✘ FAIL') . '</b> ' . htmlspecialchars($name)
        . ($detail !== '' ? ' — <span style="color:#666">' . htmlspecialchars($detail) . '</span>' : '')
        . ($fix !== '' && !$ok ? '<div style="color:#e5322d;font-size:13px;margin-top:6px">👉 修复: ' . htmlspecialchars($fix) . '</div>' : '')
        . '</div>';
}
echo '<h2 style="font-family:sans-serif">坤影CMS 验证码链路自诊断</h2>';

/* 1. 环境 */
item('PHP版本 ≥ 7.4', PHP_VERSION_ID >= 70400, PHP_VERSION, '升级PHP(推荐8.0-8.2)');
foreach (['gd' => 'GD(画图)', 'session' => 'Session', 'mbstring' => 'mbstring', 'pdo_mysql' => 'PDO MySQL'] as $ext => $label) {
    item("扩展 {$label}", extension_loaded($ext), '', "php.ini中启用 extension={$ext} 后重启php-fpm/IIS");
}

/* 2. session.save_path 可写 */
$sp = ini_get('session.save_path');
if ($sp === '' || $sp === false) {
    $sp = sys_get_temp_dir();
    item('session.save_path', true, '未配置,使用系统临时目录: ' . $sp);
} else {
    item('session.save_path 目录可写', is_dir($sp) && is_writable($sp), $sp, 'php.ini的session.save_path指向一个存在且可写的目录(Windows示例: D:\phpstudy\tmp)后重启');
}

/* 3. GD真画一张 */
$ok = false; $err = '';
try {
    $im = imagecreatetruecolor(60, 30);
    imagestring($im, 5, 5, 5, 'TEST', imagecolorallocate($im, 0, 0, 0));
    ob_start();
    $ok = imagepng($im) !== false && ob_get_length() > 50;
    ob_end_clean();
    imagedestroy($im);
} catch (Throwable $t) {
    $err = $t->getMessage();
    if (ob_get_level() > 0) ob_end_clean();
}
item('GD实际画图', $ok, $err, 'GD扩展异常: 换PHP版本或重装GD;phpstudy在"PHP设置→扩展"勾选gd');

/* 4. 同请求内会话读写 */
Security::session();
$_SESSION['_diag_marker'] = 'ky-' . random_int(1000, 9999);
$item('同请求会话写入+读取', ($_SESSION['_diag_marker'] ?? '') === $_SESSION['_diag_marker'], '', 'Session扩展异常');

/* 5. 跨请求会话持久化(step=2) */
if (($_GET['step'] ?? '') !== '2') {
    $_SESSION['_diag_cross'] = 'cross-' . random_int(1000, 9999);
    session_write_close();
    $self = htmlspecialchars($_SERVER['SCRIPT_NAME'] ?? '/diagnose_captcha.php') . '?step=2';
    echo '<div style="margin:10px 0;padding:10px 14px;background:#f0f4ff;border-left:4px solid #4a6fe5">'
        . '跨请求会话测试: <a href="' . $self . '" style="color:#4a6fe5">点击这里继续 →</a>(若点击后浏览器地址变化但本页重新从头开始,说明浏览器没有带上会话Cookie)</div>';
} else {
    Security::session();
    $cross = (string)($_SESSION['_diag_cross'] ?? '');
    item('跨请求会话持久化(Cookie回传)', $cross !== '', $cross !== '' ? $cross : '标记丢失', '浏览器未回传会话Cookie: 检查是否用IP访问而Cookie域不匹配/HTTPS证书无效被浏览器拒收/PHP的session.use_only_cookies与use_trans_id配置');
}

/* 6. 验证码生成链路(进程内完成,不发起HTTP自请求——杜绝SSRF面) */
$imgOk = false; $genErr = '';
try {
    $im = imagecreatetruecolor(130, 42);
    imagestring($im, 5, 10, 12, 'KY42', imagecolorallocate($im, 0, 0, 0));
    ob_start();
    $imgOk = imagepng($im) !== false && ob_get_length() > 200;
    ob_end_clean();
    imagedestroy($im);
} catch (Throwable $t) {
    $genErr = $t->getMessage();
    if (ob_get_level() > 0) ob_end_clean();
}
item('验证码图片生成(进程内)', $imgOk, $genErr, 'GD生成异常: 查data/log/php_error.log的Captcha条目');

$scene = 'diagtest';
$_SESSION['_captcha_' . $scene] = 'diag';
$_SESSION['_captcha_time_' . $scene] = time();
item('验证码答案写入会话', true, 'diag');
$pass = Captcha::check('diag', $scene);
item('Captcha::check校验闭环', $pass, '', '校验逻辑异常(此情况罕见)');

/* 7. 人机验证方式与子目录提示 */
$p = Verify::provider();
item('人机验证方式', true, $p === 'graph' ? '图形验证码' : ($p === 'geetest' ? '极验滑块(需服务器能访问gcaptcha4.geetest.com)' : 'Cloudflare Turnstile'),
    $p === 'geetest' ? '若服务器访问不了极验接口,后台「系统设置→人机验证」切回图形验证码' : '');
$dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
item('部署位置', true, $dir === '/' || $dir === '.' ? '站点根目录(推荐)' : '子目录 ' . $dir . '(程序要求整站部署在根目录,子目录部署会导致路由与静态资源异常)');

echo '<p style="color:#999;font-family:sans-serif;font-size:12px">诊断完成后请删除本文件。全部PASS仍报"验证码错误"时,把本页截图发给技术支持。</p>';
