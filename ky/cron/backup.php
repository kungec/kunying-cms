<?php
/**
 * 数据库备份CLI(纯PHP实现,跨平台:Windows/Linux通用,不依赖mysqldump)
 * 用法:
 *   php backup.php                 备份到 data/backup/db_日期.sql.gz
 *   php backup.php --keep=7        保留最近7份(默认7),超出自动删除
 * Windows计划任务示例:
 *   schtasks /create /tn "KunYingBackup" /sc daily /st 03:20 /tr "php D:\site\ky\cron\backup.php"
 * Linux crontab示例:
 *   20 3 * * * php /站点目录/ky/cron/backup.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
define('KY_SUB_DIR', '');
require dirname(__DIR__) . '/bootstrap.php';

if (!is_file(KY_PATH . '/data/install.lock')) exit("not installed\n");

$opts = getopt('', ['keep:']);
$keep = max(1, min(90, (int)($opts['keep'] ?? 7)));

$outDir = KY_PATH . '/data/backup';
if (!is_dir($outDir)) @mkdir($outDir, 0755, true);
if (!is_dir($outDir) || !is_writable($outDir)) exit("备份目录不可写: {$outDir}\n");

$file = $outDir . '/db_' . date('Ymd_His') . '.sql.gz';
$gz = gzopen($file, 'w9');
if (!$gz) exit("无法创建备份文件\n");

$t0 = microtime(true);
$tables = Db::fetchAll('SHOW TABLES');
$written = 0;

gzwrite($gz, "-- KunYing CMS 数据库备份\n-- 时间: " . date('Y-m-d H:i:s') . "\n");
gzwrite($gz, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

foreach ($tables as $t) {
    $table = array_values((array)$t)[0];
    $create = Db::fetch("SHOW CREATE TABLE `{$table}`");
    if (!$create) continue;
    gzwrite($gz, "\nDROP TABLE IF EXISTS `{$table}`;\n");
    gzwrite($gz, $create['Create Table'] . ";\n\n");

    // 分批导出数据(每批2000行,控制内存)
    $offset = 0;
    while (true) {
        $rows = Db::fetchAll("SELECT * FROM `{$table}` LIMIT 2000 OFFSET {$offset}");
        if (!$rows) break;
        foreach ($rows as $row) {
            $vals = [];
            foreach ($row as $v) {
                // quote() 自带首尾引号,外层不能再包一层(双引号会导致恢复失败)
                $vals[] = $v === null ? 'NULL' : Db::pdo()->quote((string)$v);
            }
            gzwrite($gz, 'INSERT INTO `' . $table . '` VALUES (' . implode(',', $vals) . ");\n");
            $written++;
        }
        $offset += 2000;
    }
}

gzwrite($gz, "\nSET FOREIGN_KEY_CHECKS=1;\n");
gzclose($gz);

$size = round(filesize($file) / 1048576, 1);
$sec = round(microtime(true) - $t0, 1);
echo "备份完成: {$file}\n数据行: {$written} 大小: {$size}MB 用时: {$sec}秒\n";

// 异地容灾:配置了云存储(ftp/oss/s3)时自动上传一份,本盘损坏时备份不陪葬
if (class_exists('Store') && method_exists('Store', 'driver') && Store::driver() !== 'local') {
    $remote = Store::put('backup/' . basename($file), (string)file_get_contents($file));
    echo $remote !== null ? "异地备份成功: {$remote}\n" : "异地备份失败(检查云存储配置/连通性),本地份仍在\n";
}

// 轮转:只保留最近N份
$files = glob($outDir . '/db_*.sql.gz') ?: [];
sort($files);
$extra = count($files) - $keep;
foreach (array_slice($files, 0, max(0, $extra)) as $old) {
    @unlink($old);
    echo "轮转删除: " . basename($old) . "\n";
}
