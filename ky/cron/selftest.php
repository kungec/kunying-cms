<?php
/**
 * 坤影CMS 自检脚本(纯CLI,不写生产数据)
 * 用法: php ky/cron/selftest.php
 * 退出码: 0=全部通过 1=存在失败项(可用于监控/cron告警)
 */
if (PHP_SAPI !== 'cli') exit("CLI only\n");
require __DIR__ . '/../bootstrap.php';

$pass = 0; $fail = 0;
function check(string $name, bool $ok, string $detail = ''): void {
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo ($ok ? '[OK]   ' : '[FAIL] ') . $name . ($detail !== '' ? "  ({$detail})" : '') . "\n";
}

/* ---------- 环境 ---------- */
foreach (['pdo_mysql' => 'PDO MySQL', 'curl' => 'curl', 'gd' => 'gd', 'mbstring' => 'mbstring', 'zip' => 'zip'] as $ext => $label) {
    check("PHP扩展 {$label}", extension_loaded($ext));
}
if (!extension_loaded('fileinfo')) {
    // 宝塔CLI常见差异:FPM已启用而CLI未启用;以Web端为准,仅提示不判失败
    echo "[INFO] PHP扩展 fileinfo:CLI未启用(请确认Web端php.ini已启用,安装器要求)\n";
}

/* ---------- 数据库schema ---------- */
$tables = array_map(fn($r) => array_values((array)$r)[0], Db::fetchAll('SHOW TABLES'));
foreach (['ky_vod', 'ky_type', 'ky_user', 'ky_order', 'ky_goods', 'ky_comment', 'ky_fav', 'ky_play_record', 'ky_film_request', 'ky_user_vod'] as $t) {
    check("数据表 {$t}", in_array($t, $tables, true));
}
$cols = array_column(Db::fetchAll('SHOW COLUMNS FROM ky_vod'), 'Field');
check('ky_vod.search_hits 列(搜索热度排名)', in_array('search_hits', $cols, true));
$colsT = array_column(Db::fetchAll('SHOW COLUMNS FROM ky_type'), 'Field');
check('ky_type.nav 列(分类主/副分级)', in_array('nav', $colsT, true));
$uniq = Db::fetchOne("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ky_order' AND INDEX_NAME='uniq_trade'");
check('ky_order.trade_no 唯一索引(防USDT重复入账)', (int)$uniq === 1);

/* ---------- 缓存 ---------- */
$ck = 'selftest_' . rand_str(6);
cache_set($ck, 'ping', 60);
check('缓存写入/读取', cache_get($ck) === 'ping');
cache_del($ck);

/* ---------- 加密主题stub完整性(密钥轮换后必须全过) ---------- */
$tk = (string)cache_get('theme_key');
$tkey = $tk !== '' ? $tk : 'KyTheme#x7P9mQ4vZ';
$stubTotal = 0; $stubBad = [];
foreach (['kunpro', 'iqiyi'] as $theme) {
    $dir = KY_PATH . "/theme/{$theme}";
    if (!is_dir($dir)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->getExtension() !== 'php') continue;
        $src = file_get_contents($f->getPathname());
        if (strpos($src, 'ky_theme_run') === false) continue;
        if (!preg_match("/ky_theme_run\(.[a-z]+.,\s*.([A-Za-z0-9+\/=]{50,})/", $src, $m)) continue;
        $stubTotal++;
        $raw = base64_decode($m[1], true);
        $out = '';
        for ($i = 0; $i < strlen($raw); $i++) $out .= $raw[$i] ^ $tkey[$i % strlen($tkey)];
        if (stripos($out, '<?php') !== 0) $stubBad[] = "{$theme}/" . $f->getFilename();
    }
}
check('付费主题stub密钥完整性', $stubTotal > 0 && empty($stubBad), $stubBad ? '解密失败:' . implode(',', array_slice($stubBad, 0, 3)) : "{$stubTotal}个stub全部可解密");

/* ---------- 授权配置 ---------- */
$licToken = trim((string)config('lic_token', ''));
check('授权令牌已配置(lic_token)', $licToken !== '', $licToken !== '' ? '长度' . strlen($licToken) : '未配置,付费主题授权校验将失败');

/* ---------- SSRF防护 ---------- */
check('SSRF:内网地址拒绝', !Http::isPublicHttpUrl('http://192.168.1.1/x.m3u8'));
check('SSRF:云元数据拒绝', !Http::isPublicHttpUrl('http://169.254.169.254/latest/meta-data/'));
check('SSRF:本机回环拒绝', !Http::isPublicHttpUrl('http://127.0.0.1:8080/'));
check('SSRF:file协议拒绝', !Http::isPublicHttpUrl('file:///etc/passwd'));
check('SSRF:公网地址放行', Http::isPublicHttpUrl('https://example.com/a.m3u8'));

/* ---------- 会话令牌数学 ---------- */
$h = password_hash('selftest-pass', PASSWORD_DEFAULT);
check('管理员会话令牌一致性', Auth::adminSessionToken(['pwd' => $h]) === Auth::adminSessionToken(['pwd' => $h]));
check('管理员会话令牌区分性', Auth::adminSessionToken(['pwd' => $h]) !== Auth::adminSessionToken(['pwd' => password_hash('selftest-pass', PASSWORD_DEFAULT)]));

/* ---------- 目录可写 ---------- */
foreach (['data/cache', 'data/cache/pages', 'data/backup', 'upload'] as $d) {
    $p = KY_PATH . '/' . $d;
    if (!is_dir($p)) @mkdir($p, 0755, true);
    check("目录可写 {$d}", is_dir($p) && is_writable($p));
}

/* ---------- 错误日志管道 ---------- */
$elogPath = KY_PATH . '/data/log/php_error.log';
$elogErr = 0;
if (is_file($elogPath)) {
    foreach (file($elogPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $eline) {
        if (strpos($eline, '[' . date('d-M-Y')) !== false) $elogErr++;
    }
}
check('PHP错误日志管道已接通(今日无错误)', is_file($elogPath) && $elogErr === 0, $elogErr > 0 ? "今日{$elogErr}条错误,见data/log/php_error.log" : (is_file($elogPath) ? '' : '尚未生成(无错误即不建文件)'));

/* ---------- 数据健康(检测项来自2026-10-15数据质量扫描) ---------- */
$emptyLive = (int)Db::fetchOne("SELECT COUNT(*) FROM ky_vod WHERE status=1 AND (play_url='' OR play_url IS NULL)");
check('在架影片无空播放源(防用户点开空页)', $emptyLive === 0, $emptyLive > 0 ? "{$emptyLive}部应下架或等采集复活" : '');
$collisions = (int)Db::fetchOne("SELECT COUNT(*) FROM (SELECT 1 FROM ky_vod GROUP BY name_norm HAVING COUNT(*)>1) t");
check('同名归一碰撞组已清零', $collisions === 0, $collisions > 0 ? "{$collisions}组待后台同名合并处理" : '');
check('归一算法版本已迁移(norm_algo=2)', (string)config('norm_algo', '') === '2');
$orphans = 0;
foreach (['ky_comment', 'ky_fav', 'ky_play_record', 'ky_user_vod'] as $tb) {
    try { $orphans += (int)Db::fetchOne("SELECT COUNT(*) FROM {$tb} c LEFT JOIN ky_vod v ON v.id=c.vod_id WHERE v.id IS NULL"); } catch (Throwable $t) {}
}
check('无孤儿关联数据(评论/收藏/记录/购买)', $orphans === 0, $orphans > 0 ? "{$orphans}条" : '');

/* ---------- 备份新鲜度(INFO级,部署环境差异大) ---------- */
$bks = glob(KY_PATH . '/data/backup/db_*.sql.gz') ?: [];
if ($bks) {
    $latest = max(array_map('filemtime', $bks));
    $ageH = round((time() - $latest) / 3600);
    echo $ageH > 48 ? "[WARN] 应用层备份已{$ageH}小时未产出\n" : "[INFO] 应用层备份新鲜({$ageH}小时前)\n";
} else {
    echo "[INFO] data/backup无应用层备份(请确认部署层备份机制,如宝塔计划任务/MySQL转储)\n";
}

/* ---------- 支付配置提示(不判定失败) ---------- */
$payOn = false;
foreach (['epay_gateway', 'codepay_gateway', 'usdt_address'] as $k) {
    if (trim((string)config($k, '')) !== '') $payOn = true;
}
echo "[INFO] 在线支付已配置\n";
echo $payOn && (int)$uniq !== 1 ? "[WARN] 配置了支付但trade_no唯一索引缺失,请执行迁移\n" : '';

echo "\n自检完成: {$pass} 通过 / {$fail} 失败\n";
exit($fail > 0 ? 1 : 0);
