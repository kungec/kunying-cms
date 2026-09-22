<?php
/**
 * 定时采集CLI入口(可选,比访客触发更可靠)
 * crontab示例(每30分钟执行一次): echo 0,30 代替分位,即 0,30 * * * * php /站点目录/ky/cron/collect.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
define('KY_SUB_DIR', '');
require dirname(__DIR__) . '/bootstrap.php';

if (!is_file(KY_PATH . '/data/install.lock')) exit("not installed\n");
echo '[' . date('Y-m-d H:i:s') . "] 定时采集开始\n";
// 每日维护(0-5点窗口内每天执行一次):图片清理+数据表瘦身
$today = (int)date('Ymd');
if ((int)date('G') < 5 && (int)config('maint_last_day', '0') !== $today) {
    config_set('maint_last_day', (string)$today);
    if (config('img_auto_clean_enable', '0') == '1') {
        $n = img_clean_orphans();
        config_set('img_clean_last_result', date('Y-m-d H:i') . " 自动清理{$n}张");
        config_set('img_clean_last_day', (string)$today);
        echo "自动清理未引用图片: {$n}张\n";
    }
    $cut = time() - 86400;
    Db::query("DELETE FROM ky_email_code WHERE expire < $cut OR (used=1 AND created < $cut)");
    Db::query("DELETE FROM ky_login_fail WHERE updated_at < " . (time() - 90 * 86400));
    $minLog = (int)Db::fetchOne("SELECT id FROM ky_admin_log ORDER BY id DESC LIMIT 1 OFFSET 2000");
    if ($minLog > 0) Db::query("DELETE FROM ky_admin_log WHERE id < $minLog");
    echo "每日维护完成(验证码/日志瘦身)\n";
}
echo "done\n";
