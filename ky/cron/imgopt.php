<?php
/**
 * 存量封面批量压缩CLI(配合采集端自动压缩,历史大图一次性瘦身)
 * 用法:
 *   php imgopt.php --limit=2000   本轮最多压缩2000张(建议配合crontab分批跑)
 *   php imgopt.php --limit=2000 --quality=80 --width=480
 * 规则: 仅处理 >480px宽 或 >150KB 的 jpg/webp;GIF/PNG跳过(动图/透明)
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
define('KY_SUB_DIR', '');
require dirname(__DIR__) . '/bootstrap.php';

if (!is_file(KY_PATH . '/data/install.lock')) exit("not installed\n");
if (!class_exists('Collector')) exit("bootstrap error\n");

$opts = getopt('', ['limit:', 'quality:', 'width:']);
$limit = max(1, min(20000, (int)($opts['limit'] ?? 2000)));
$quality = max(50, min(95, (int)($opts['quality'] ?? 80)));
$width = max(200, min(960, (int)($opts['width'] ?? 480)));

$dir = KY_PATH . '/upload/vod';
if (!is_dir($dir)) exit("no upload dir\n");

$files = [];
foreach (glob($dir . '/*.jpg') ?: [] as $f) $files[] = $f;
foreach (glob($dir . '/*.webp') ?: [] as $f) $files[] = $f;
// 从最旧开始,避免反复扫描同一批
sort($files);

$done = 0; $saved = 0; $scanned = 0; $pos = (int)(config('imgopt_cursor', '0'));
$count = count($files);
if ($pos >= $count) $pos = 0;

while ($done < $limit && $scanned < $count) {
    $f = $files[$pos] ?? null;
    $pos = ($pos + 1) % $count;
    $scanned++;
    if (!$f || !is_file($f)) continue;
    $size = filesize($f);
    if ($size < 150000) continue; // <150KB跳过
    $info = @getimagesize($f);
    if (!$info || ($info[2] !== IMAGETYPE_JPEG && $info[2] !== IMAGETYPE_WEBP)) continue;
    if ((int)$info[0] <= $width && $size < 300000) continue;
    $body = @file_get_contents($f);
    if ($body === false) continue;
    $img = @imagecreatefromstring($body);
    if ($img === false) continue;
    $w = (int)$info[0]; $h = (int)$info[1];
    $tw = min($width, $w);
    $th = (int)round($h * $tw / $w);
    $out = imagecreatetruecolor($tw, $th);
    imagecopyresampled($out, $img, 0, 0, 0, 0, $tw, $th, $w, $h);
    ob_start();
    $ok = imagejpeg($out, null, $quality);
    $data = $ok ? (string)ob_get_clean() : '';
    ob_end_clean();
    imagedestroy($img);
    imagedestroy($out);
    if ($ok && strlen($data) > 1024 && strlen($data) < $size) {
        file_put_contents($f, $data, LOCK_EX);
        $saved += $size - strlen($data);
        $done++;
        echo "压缩 " . basename($f) . " " . round($size / 1024) . "KB → " . round(strlen($data) / 1024) . "KB\n";
    }
    usleep(20000); // 20ms限速,防IO冲击
}
config_set('imgopt_cursor', (string)$pos);
printf("本轮: 扫描%d 压缩%d 节省%.1fMB (进度%d/%d)\n", $scanned, $done, $saved / 1048576, $pos, $count);
