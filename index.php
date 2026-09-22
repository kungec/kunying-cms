<?php
/**
 * 坤影CMS 前台入口
 */
define('KY_SUB_DIR', '');
require __DIR__ . '/ky/bootstrap.php';

if (!is_file(__DIR__ . '/data/install.lock')) {
    header('Location: /install/');
    exit;
}

Security::session();

// 伪静态:输出层URL重写(后台可开关)
if (config('rewrite_enable', '0') == '1') {
    ob_start(function (string $html) { return rewrite_html($html); });
}

// 定时任务:到点后由页面收尾时在后台执行(不阻塞访客)
register_shutdown_function(function () {
    try {
        // 每日凌晨(0-5点)自动清理未引用图片(后台可开关)
        if (config('img_auto_clean_enable', '0') == '1') {
            $today = date('Y-m-d');
            if (config('img_clean_last_date', '') !== $today && (int)date('G') < 5) {
                config_set('img_clean_last_date', $today);
                $n = img_clean_orphans();
                if ($n > 0) config_set('img_clean_last_result', '自动清理未引用图片 ' . $n . ' 张(' . date('H:i') . ')');
            }
        }
        if (!Collector::autoDue()) return;
        if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
        @set_time_limit(300);
        ignore_user_abort(true);
        Collector::runDue();
    } catch (\Throwable $t) {}
});

$app = new App('', 'Index');
$frontView = new View(theme_path());
View::setFront($frontView);
$app->setView($frontView);
$app->run();
