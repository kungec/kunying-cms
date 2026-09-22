<?php
/**
 * 定时采集CLI入口(可选,比访客触发更可靠)
 * crontab示例(每30分钟执行一次): echo 0,30 代替分位,即 0,30 * * * * php /站点目录/ky/cron/collect.php
 */
define('KY_SUB_DIR', '');
require dirname(__DIR__) . '/bootstrap.php';

if (!is_file(KY_PATH . '/data/install.lock')) exit("not installed\n");
echo '[' . date('Y-m-d H:i:s') . "] 定时采集开始\n";
$ret = Collector::runDue(true);
foreach ($ret as $r) {
    echo isset($r['error']) ? "✗ {$r['api']}: {$r['error']}\n" : "✓ {$r['api']}: 新增{$r['added']} 更新{$r['updated']}\n";
}
echo "done\n";
