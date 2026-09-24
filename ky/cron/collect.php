<?php
/**
 * 定时采集CLI入口(比访客触发更可靠)
 * 用法:
 *   php collect.php                                定时增量采集(全部启用接口,近12小时更新)
 *   php collect.php --full --pages=10              全量采集启用接口第1~10页(单进程)
 *   php collect.php --full --pages=30 --threads=4  4进程并行分片采集(推荐)
 *   php collect.php --full --pages=10 --api=3      只采集指定接口
 * crontab示例: 0,30 * * * * php /站点目录/ky/cron/collect.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
define('KY_SUB_DIR', '');
require dirname(__DIR__) . '/bootstrap.php';

if (!is_file(KY_PATH . '/data/install.lock')) exit("not installed\n");

$opts = getopt('', ['threads:', 'pages:', 'api:', 'shards:', 'worker', 'full']);
$threads = max(1, min(8, (int)($opts['threads'] ?? 1)));

// ── 子进程模式:按分片列表采集并输出JSON结果行 ──
if (isset($opts['worker'])) {
    foreach (explode(',', (string)($opts['shards'] ?? '')) as $pair) {
        $seg = array_map('intval', explode(':', trim($pair)));
        if (count($seg) < 2 || $seg[0] < 1 || $seg[1] < 1) continue;
        try {
            $r = Collector::collectPage($seg[0], $seg[1]);
            echo json_encode(['ok' => 1, 'api' => $seg[0], 'page' => $seg[1], 'added' => $r['added'], 'updated' => $r['updated']], JSON_UNESCAPED_UNICODE), "\n";
        } catch (Throwable $t) {
            echo json_encode(['ok' => 0, 'api' => $seg[0], 'page' => $seg[1], 'error' => $t->getMessage()], JSON_UNESCAPED_UNICODE), "\n";
        }
    }
    exit(0);
}

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
    // 页面缓存修剪:详情页按源/集数存在变体,7天前的缓存文件定期清理防累积
    $pruned = 0;
    foreach (glob(KY_PATH . '/data/cache/pages/*.html') ?: [] as $pcf) {
        if (time() - filemtime($pcf) > 7 * 86400) { @unlink($pcf); $pruned++; }
    }
    echo "每日维护完成(验证码/日志瘦身,页面缓存清理{$pruned})\n";
}

// 磁盘看门狗:剩余<3GB自动暂停采集(采集是磁盘消耗大头),防止磁盘写满导致全站故障
$freeMB = (int)(disk_free_space(KY_PATH) / 1048576);
if ($freeMB < 3072) {
    file_put_contents('/www/backup/health.log', date('Y-m-d H:i') . " ⚠⚠ 磁盘仅剩{$freeMB}MB,已自动暂停采集,请扩容或清理后恢复\n", FILE_APPEND);
    exit("disk low: {$freeMB}MB free, collection paused\n");
}

// ── 全量/多进程模式:启用接口 × 页数 → 分片 → N子进程并行 ──
if (isset($opts['full'])) {
    $pages = max(1, min(500, (int)($opts['pages'] ?? 10)));
    $apiFilter = isset($opts['api']) ? (int)$opts['api'] : 0;
    $apis = Db::fetchAll("SELECT id, name FROM ky_collect_api WHERE status=1 AND collect_auto=1" . ($apiFilter > 0 ? " AND id=" . $apiFilter : ""));
    if (!$apis) { echo "没有启用自动采集的接口\n"; exit(0); }
    $pairs = [];
    foreach ($apis as $a) for ($p = 1; $p <= $pages; $p++) $pairs[] = $a['id'] . ':' . $p;
    $chunks = array_chunk($pairs, (int)ceil(count($pairs) / $threads)) ?: [];
    echo '[' . date('Y-m-d H:i:s') . "] 采集任务: " . count($apis) . " 个接口 × {$pages} 页 = " . count($pairs) . " 分片,{$threads} 进程并行\n";
    $t0 = microtime(true);
    $sum = ['added' => 0, 'updated' => 0, 'errors' => 0];
    $perApi = [];
    foreach ($chunks as $chunk) {
        if (!$chunk) continue;
        $cmd = PHP_BINARY . ' ' . escapeshellarg(__FILE__) . ' --worker --shards=' . escapeshellarg(implode(',', $chunk));
        // stderr指向文件,避免子进程警告写满管道造成死锁/污染JSON输出
        $devnull = fopen('/dev/null', 'w');
        $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => $devnull], $pipes);
        if (!is_resource($p)) { $sum['errors'] += count($chunk); continue; }
        $out = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        proc_close($p);
        foreach (explode("\n", (string)$out) as $line) {
            $j = json_decode(trim($line), true);
            if (!is_array($j)) continue;
            if (!empty($j['ok'])) {
                $sum['added'] += (int)$j['added'];
                $sum['updated'] += (int)$j['updated'];
                $perApi[(int)$j['api']] = ($perApi[(int)$j['api']] ?? 0) + (int)$j['added'];
                echo "✓ 接口#{$j['api']} 第{$j['page']}页: 新增{$j['added']} 更新{$j['updated']}\n";
            } else {
                $sum['errors']++;
                echo "✗ 接口#" . ($j['api'] ?? '?') . " 第" . ($j['page'] ?? '?') . "页: " . ($j['error'] ?? '错误') . "\n";
            }
        }
    }
    $sec = round(microtime(true) - $t0, 1);
    // 写回定时采集状态(与后台展示格式兼容)
    $rows = [];
    foreach ($apis as $a) {
        if (isset($perApi[(int)$a['id']])) $rows[] = ['api' => $a['name'], 'added' => $perApi[(int)$a['id']], 'updated' => 0];
    }
    config_set('collect_last_result', json_encode($rows, JSON_UNESCAPED_UNICODE));
    config_set('collect_last_auto', (string)time());
    printf("完成: 新增%d 更新%d 失败%d 用时%s秒\n", $sum['added'], $sum['updated'], $sum['errors'], $sec);
    exit(0);
}

echo '[' . date('Y-m-d H:i:s') . "] 定时采集开始\n";
$ret = Collector::runDue(true);
$added = 0;
foreach ($ret as $r) {
    $added += (int)($r['added'] ?? 0);
    echo isset($r['error']) ? "✗ {$r['api']}: {$r['error']}\n" : "✓ {$r['api']}: 新增{$r['added']} 更新{$r['updated']}\n";
}
// 百度推送:有新增影片时,把新片详情页推给百度加速收录
if ($added > 0 && config('baidu_push_site', '') !== '' && config('baidu_push_token', '') !== '') {
    $host = trim((string)config('baidu_push_site', ''));
    $rw = config('rewrite_enable', '0') == '1';
    $ids = Db::fetchAll("SELECT id FROM ky_vod WHERE status=1 AND addtime > " . (time() - 1800));
    $urls = [];
    foreach ($ids as $v) $urls[] = 'https://' . $host . ($rw ? '/detail-' . (int)$v['id'] . '.html' : '/index.php?s=/vod/detail&id=' . (int)$v['id']);
    if ($urls && baidu_push($urls)) echo "百度推送: ", count($urls), "条\n";
}
echo "done\n";
