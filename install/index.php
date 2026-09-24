<?php
/**
 * 坤影CMS 傻瓜式安装向导
 * 步骤: 1环境检测 → 2数据库与管理员 → 3完成
 */
define('KY_INSTALL', true);
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_WARNING);
ini_set('display_errors', '0');
session_start();

$root = dirname(__DIR__);
$lockFile = $root . '/data/install.lock';

// 防止越权重复安装
if (is_file($lockFile)) {
    header('Content-Type: text/html; charset=utf-8');
    exit('<!doctype html><meta charset="utf-8"><body style="font-family:sans-serif;background:#101014;color:#eee;display:flex;align-items:center;justify-content:center;height:100vh">系统已安装。如需重装请先删除 data/install.lock 文件</body>');
}

$step = max(1, min(3, (int)($_GET['step'] ?? 1)));
$msg = '';
$ok = true;

/* ---------------- 环境检测 ---------------- */
function env_checks(string $root): array
{
    $items = [];
    $items[] = ['PHP版本 >= 7.4', version_compare(PHP_VERSION, '7.4.0', '>='), '当前 ' . PHP_VERSION];
    foreach (['pdo_mysql' => 'PDO MySQL', 'curl' => 'cURL', 'gd' => 'GD图形库', 'mbstring' => 'mbstring', 'zip' => 'ZipArchive', 'fileinfo' => 'fileinfo', 'openssl' => 'OpenSSL'] as $ext => $name) {
        $items[] = [$name . ' 扩展', extension_loaded($ext), $ext];
    }
    foreach (['data', 'data/cache', 'data/cache/pages', 'data/upload', 'data/license', 'data/packages', 'upload', 'upload/vod', 'theme', 'addon'] as $dir) {
        $path = $root . '/' . $dir;
        if (!is_dir($path)) @mkdir($path, 0755, true);
        // 自动索要755权限(目录需可写才能在线装主题/缓存/图片本地化)
        if (!is_writable($path)) @chmod($path, 0755);
        if (!is_writable($path)) { @chmod(dirname($path), 0755); @chmod($path, 0775); }
        $items[] = [$dir . '/ 目录可写(755)', is_writable($path), $dir];
    }
    $items[] = ['入口文件可写(config生成)', is_writable($root . '/data/'), 'data'];
    return $items;
}

/* ---------------- 递归删除目录(安装成功后自删install) ---------------- */
function rrmdir(string $dir): bool
{
    if (!is_dir($dir)) return true;
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $f) {
        $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    }
    return @rmdir($dir);
}

/* ---------------- 工作目录755权限(安装时统一落位) ---------------- */
function ensure_perms(string $root): array
{
    $fixed = 0; $failed = [];
    foreach (['data', 'data/cache', 'data/cache/pages', 'data/upload', 'data/license', 'data/packages', 'upload', 'upload/vod', 'theme', 'addon'] as $dir) {
        $path = $root . '/' . $dir;
        if (!is_dir($path)) @mkdir($path, 0755, true);
        if (!is_writable($path)) { @chmod($path, 0755); $fixed++; }
        if (!is_writable($path)) $failed[] = $dir;
    }
    // 递归修正已存在子目录
    foreach (['data/cache', 'theme', 'addon', 'upload'] as $dir) {
        $p = $root . '/' . $dir;
        if (!is_dir($p)) continue;
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($p, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($rii as $f) {
            if ($f->isDir() && !is_writable($f->getPathname())) { @chmod($f->getPathname(), 0755); $fixed++; }
        }
    }
    return [$fixed, $failed];
}

/* ---------------- 执行安装 ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 3) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $dbHost = trim((string)($_POST['db_host'] ?? '127.0.0.1'));
        $dbPort = (int)($_POST['db_port'] ?? 3306);
        $dbName = trim((string)($_POST['db_name'] ?? 'kunying'));
        $dbUser = trim((string)($_POST['db_user'] ?? 'root'));
        $dbPass = (string)($_POST['db_pass'] ?? '');
        $adminUser = trim((string)($_POST['admin_user'] ?? ''));
        $adminPass = (string)($_POST['admin_pass'] ?? '');
        $adminRe = (string)($_POST['admin_repass'] ?? '');

        if (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $dbName)) throw new RuntimeException('数据库名称不合法');
        if (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $adminUser)) throw new RuntimeException('管理员账号须为3-20位字母数字下划线');
        if (strlen($adminPass) < 8) throw new RuntimeException('管理员密码至少8位');
        if ($adminPass !== $adminRe) throw new RuntimeException('两次输入的管理员密码不一致');

        $pdo = new PDO("mysql:host={$dbHost};port={$dbPort};charset=utf8mb4", $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 8,
        ]);
        // 建库(不存在时)
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
        $pdo->exec("USE `{$dbName}`");

        // 建表
        $schema = install_schema();
        foreach ($schema as $sql) {
            $pdo->exec($sql);
        }

        // 管理员
        $st = $pdo->prepare("INSERT INTO ky_admin (username,pwd,created) VALUES (?,?,?)");
        $st->execute([$adminUser, password_hash($adminPass, PASSWORD_DEFAULT), time()]);

        // 基础配置
        $cfg = [
            'site_name' => '坤影影视',
            'site_template' => 'kylite',
            'site_mode' => 'cms',
            'cdn_mode' => '0',
            'member_enable' => '1',
            'show_api_notice' => '0',
            'footer_notice_link' => '0',
            'register_enable' => '1',
            'captcha_provider' => 'graph',
            'debug' => '0',
            'sys_version' => '1.0.0',
            'api_site' => '',
            'points_sign' => '5',
            'points_pay_rate' => '10',
            'usdt_rate' => '',
            'player_parse' => '',
            'collect_auto_enable' => '0',
            'collect_auto_interval' => '60',
            'collect_last_auto' => '0',
            'collect_last_result' => '',
            'collect_dedup_title' => '1',
            'collect_img_local' => '0',
            'collect_threads' => '1',
            'img_store' => 'local',
            'img_dir' => '/upload/vod',
            'search_limit_enable' => '1',
            'search_limit_times' => '30',
            'search_limit_window' => '60',
        ];
        $st = $pdo->prepare("INSERT INTO ky_config (`key`,`value`) VALUES (?,?)");
        foreach ($cfg as $k => $v) {
            $st->execute([$k, $v]);
        }

        // 默认大分类(采集时细分类型自动挂到对应大分类下)
        $st2 = $pdo->prepare("INSERT INTO ky_type (pid,name,sort,status,show_home) VALUES (0,?,?,1,1)");
        $i = 1;
        foreach (["电影","剧集","动漫","综艺","纪录片","短剧"] as $gn) { $st2->execute([$gn, $i++]); }

        // 内置采集源:极速资源 / 猫眼资源
        $st = $pdo->prepare("INSERT INTO ky_collect_api (name,api_url,remark,status,collect_auto,collect_hours,addtime) VALUES (?,?,?,?,?,12,?)");
        $st->execute(['极速资源', 'https://jszyapi.com/api.php/provide/vod/at/json', '极速云/极速m3u8 官方:jisuzy.tv', 1, 1, time()]);
        $st->execute(['猫眼资源', 'https://api.maoyanapi.top/api.php/provide/vod/from/mym3u8/at/json', '猫眼m3u8线路 官方:maoyanzy.com', 1, 1, time()]);
        $st->execute(['非凡资源', 'https://api.ffzyapi.com/api.php/provide/vod/from/ffm3u8/at/json', '非凡m3u8线路 官方:ffzy.tv', 1, 1, time()]);
        $st->execute(['豆瓣资源', 'https://caiji.dbzy5.com/api.php/provide/vod/from/dbm3u8/at/json', '豆瓣m3u8线路 官方:dbzy.tv', 1, 1, time()]);
        $st->execute(['百度资源', 'https://api.apibdzy.com/api.php/provide/vod/from/dbm3u8/at/json', '百度m3u8线路 官方:bdzy1.com(需官方加白名单)', 1, 1, time()]);

        // 内置播放器
        $players = [
            ['no', '内置直链播放(mp4/m3u8)', '', 1],
            ['jsm3u8', '极速m3u8', '', 1],
            ['jsyun', '极速云', 'm3u8:{url}/index.m3u8', 1],
            ['mym3u8', '猫眼m3u8', '', 1],
            ['ffm3u8', '非凡m3u8', '', 1],
            ['dbm3u8', '豆瓣m3u8', '', 1],
        ];
        $st = $pdo->prepare("INSERT INTO ky_player (`code`,name,parse,status) VALUES (?,?,?,?)");
        foreach ($players as $p) { $st->execute($p); }

        // 默认幻灯(5张,用户可在后台幻灯管理自行修改/删除)
        $slides = [
            ['万千好片 · 尽在坤影', '/static/img/banner.svg', 0],
            ['新片首发 · 抢先看', '/static/img/banner2.svg', 2],
            ['VIP专享 · 蓝光画质', '/static/img/banner3.svg', 3],
            ['每日更新 · 全网聚合', '/static/img/banner4.svg', 4],
            ['多端畅看 · 随心所欲', '/static/img/banner5.svg', 5],
        ];
        $st = $pdo->prepare("INSERT INTO ky_slide (name,pic,url,pos,sort,status) VALUES (?,?,?,'top',0,1)");
        foreach ($slides as $sl) { $st->execute([$sl[0], $sl[1], $sl[2]]); }

        // 写配置文件
        $appKey = bin2hex(random_bytes(24));
        $conf = "<?php\n// 坤影CMS数据库配置(自动生成)\nreturn [\n"
            . "    'db' => [\n"
            . "        'host' => " . var_export($dbHost, true) . ",\n"
            . "        'port' => " . (int)$dbPort . ",\n"
            . "        'name' => " . var_export($dbName, true) . ",\n"
            . "        'user' => " . var_export($dbUser, true) . ",\n"
            . "        'pass' => " . var_export($dbPass, true) . ",\n"
            . "    ],\n"
            . "    'key' => " . var_export($appKey, true) . ",\n"
            . "];\n";
        file_put_contents($root . '/data/config.php', $conf, LOCK_EX);

        // 全部工作目录统一落位755权限(防在线装主题/缓存/图片本地化失败)
        [$permFixed, $permFailed] = ensure_perms($root);

        file_put_contents($lockFile, 'installed:' . date('Y-m-d H:i:s') . "\n", LOCK_EX);

        // 安装完成自动删除install目录(失败不阻塞,页面会提示手动删除)
        $installRemoved = rrmdir($root . '/install');

        echo json_encode(['code' => 1, 'msg' => '安装成功', 'perm_failed' => $permFailed, 'install_removed' => $installRemoved]);
    } catch (Throwable $t) {
        echo json_encode(['code' => 0, 'msg' => '安装失败:' . $t->getMessage() . ' @' . basename($t->getFile()) . ':' . $t->getLine()]);
    }
    exit;
}

function install_schema(): array
{
    $t = [];
    $t[] = "CREATE TABLE IF NOT EXISTS `ky_config` (`key` varchar(64) NOT NULL, `value` text, PRIMARY KEY (`key`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $t[] = "CREATE TABLE IF NOT EXISTS `ky_admin` (`id` int unsigned NOT NULL AUTO_INCREMENT, `username` varchar(60) NOT NULL, `pwd` varchar(255) NOT NULL, `login_time` int unsigned DEFAULT 0, `login_ip` varchar(64) DEFAULT '', `created` int unsigned DEFAULT 0, PRIMARY KEY (`id`), UNIQUE KEY `username` (`username`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $t[] = "CREATE TABLE IF NOT EXISTS `ky_user` (`id` int unsigned NOT NULL AUTO_INCREMENT, `email` varchar(120) NOT NULL, `name` varchar(60) DEFAULT '', `pwd` varchar(255) NOT NULL, `points` int NOT NULL DEFAULT 0, `vip_expire` int unsigned DEFAULT 0, `avatar` varchar(255) DEFAULT '', `status` tinyint NOT NULL DEFAULT 1, `reg_ip` varchar(64) DEFAULT '', `reg_time` int unsigned DEFAULT 0, `email_verified` tinyint NOT NULL DEFAULT 0, `last_login_time` int unsigned DEFAULT 0, `last_login_ip` varchar(64) DEFAULT '', `sign_day` int unsigned DEFAULT 0, PRIMARY KEY (`id`), UNIQUE KEY `email` (`email`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $t[] = "CREATE TABLE IF NOT EXISTS `ky_login_fail` (`id` int unsigned NOT NULL AUTO_INCREMENT, `type` varchar(10) NOT NULL, `account` varchar(120) NOT NULL, `ip` varchar(64) NOT NULL, `fails` int NOT NULL DEFAULT 0, `ban_until` int unsigned NOT NULL DEFAULT 0, `updated_at` int unsigned NOT NULL DEFAULT 0, PRIMARY KEY (`id`), UNIQUE KEY `tk` (`type`,`account`,`ip`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $t[] = "CREATE TABLE IF NOT EXISTS `ky_email_code` (`id` int unsigned NOT NULL AUTO_INCREMENT, `email` varchar(120) NOT NULL, `code` varchar(10) NOT NULL, `type` varchar(20) NOT NULL DEFAULT 'register', `expire` int unsigned NOT NULL DEFAULT 0, `used` tinyint NOT NULL DEFAULT 0, `created` int unsigned NOT NULL DEFAULT 0, PRIMARY KEY (`id`), KEY `email` (`email`,`type`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $t[] = "CREATE TABLE IF NOT EXISTS `ky_type` (`id` int unsigned NOT NULL AUTO_INCREMENT, `pid` int unsigned NOT NULL DEFAULT 0, `name` varchar(30) NOT NULL, `sort` int NOT NULL DEFAULT 0, `status` tinyint NOT NULL DEFAULT 1, `show_home` tinyint NOT NULL DEFAULT 0, `icon` varchar(10) DEFAULT '', `nav` tinyint NOT NULL DEFAULT 1, `image` varchar(255) DEFAULT '', PRIMARY KEY (`id`), KEY `pid` (`pid`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $t[] = "CREATE TABLE IF NOT EXISTS `ky_vod` (`id` int unsigned NOT NULL AUTO_INCREMENT, `type_id` int unsigned NOT NULL DEFAULT 0, `api_id` int unsigned NOT NULL DEFAULT 0, `api_vid` varchar(32) DEFAULT '', `name` varchar(120) NOT NULL, `name_norm` varchar(130) NOT NULL DEFAULT '', `sub` varchar(120) DEFAULT '', `class` varchar(200) DEFAULT '', `year` varchar(20) DEFAULT '', `area` varchar(40) DEFAULT '', `lang` varchar(40) DEFAULT '', `remarks` varchar(60) DEFAULT '', `score` decimal(3,1) NOT NULL DEFAULT 0.0, `director` varchar(400) DEFAULT '', `actor` varchar(1000) DEFAULT '', `content` text, `pic` varchar(500) DEFAULT '', `play_from` varchar(200) DEFAULT '', `play_url` mediumtext, `vip` tinyint NOT NULL DEFAULT 0, `points` int NOT NULL DEFAULT 0, `total_hits` int unsigned NOT NULL DEFAULT 0, `status` tinyint NOT NULL DEFAULT 1, `addtime` int unsigned DEFAULT 0, `updatetime` int unsigned DEFAULT 0, PRIMARY KEY (`id`), KEY `type_id` (`type_id`), KEY `addtime` (`addtime`), KEY `updatetime` (`updatetime`), UNIQUE KEY `api` (`api_id`,`api_vid`), KEY `name` (`name`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $t[] = "CREATE TABLE IF NOT EXISTS `ky_topic` (`id` int unsigned NOT NULL AUTO_INCREMENT, `name` varchar(60) NOT NULL, `pic` varchar(500) DEFAULT '', `description` varchar(500) DEFAULT '', `content` text, `status` tinyint NOT NULL DEFAULT 1, `addtime` int unsigned DEFAULT 0, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $t[] = "CREATE TABLE IF NOT EXISTS `ky_article` (`id` int unsigned NOT NULL AUTO_INCREMENT, `title` varchar(200) NOT NULL, `content` mediumtext, `status` tinyint NOT NULL DEFAULT 1, `addtime` int unsigned DEFAULT 0, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $t[] = "CREATE TABLE IF NOT EXISTS `ky_slide` (`id` int unsigned NOT NULL AUTO_INCREMENT, `name` varchar(120) NOT NULL, `pic` varchar(500) NOT NULL, `url` varchar(300) DEFAULT '', `pos` varchar(10) NOT NULL DEFAULT 'top', `sort` int NOT NULL DEFAULT 0, `status` tinyint NOT NULL DEFAULT 1, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $t[] = "CREATE TABLE IF NOT EXISTS `ky_link` (`id` int unsigned NOT NULL AUTO_INCREMENT, `name` varchar(60) NOT NULL, `url` varchar(300) NOT NULL, `sort` int NOT NULL DEFAULT 0, `status` tinyint NOT NULL DEFAULT 1, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $t[] = "CREATE TABLE IF NOT EXISTS `ky_goods` (`id` int unsigned NOT NULL AUTO_INCREMENT, `name` varchar(60) NOT NULL, `price` decimal(10,2) NOT NULL DEFAULT 0.00, `points` int NOT NULL DEFAULT 0, `days` int NOT NULL DEFAULT 0, `sort` int NOT NULL DEFAULT 0, `status` tinyint NOT NULL DEFAULT 1, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $t[] = "CREATE TABLE IF NOT EXISTS `ky_order` (`id` int unsigned NOT NULL AUTO_INCREMENT, `order_no` varchar(30) NOT NULL, `user_id` int unsigned NOT NULL, `goods_id` int unsigned NOT NULL DEFAULT 0, `type` varchar(10) NOT NULL DEFAULT 'points', `title` varchar(120) DEFAULT '', `amount` decimal(10,2) NOT NULL DEFAULT 0.00, `usdt_amount` decimal(12,2) DEFAULT NULL, `pay_type` varchar(20) DEFAULT '', `status` tinyint NOT NULL DEFAULT 0, `trade_no` varchar(64) DEFAULT '', `created` int unsigned DEFAULT 0, `paid_time` int unsigned DEFAULT 0, PRIMARY KEY (`id`), UNIQUE KEY `order_no` (`order_no`), KEY `user_id` (`user_id`), KEY `status` (`status`,`created`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $t[] = "CREATE TABLE IF NOT EXISTS `ky_comment` (`id` int unsigned NOT NULL AUTO_INCREMENT, `user_id` int unsigned NOT NULL, `vod_id` int unsigned NOT NULL, `content` varchar(500) NOT NULL, `status` tinyint NOT NULL DEFAULT 1, `created` int unsigned DEFAULT 0, PRIMARY KEY (`id`), KEY `vod_id` (`vod_id`), KEY `user_id` (`user_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $t[] = "CREATE TABLE IF NOT EXISTS `ky_fav` (`id` int unsigned NOT NULL AUTO_INCREMENT, `user_id` int unsigned NOT NULL, `vod_id` int unsigned NOT NULL, `created` int unsigned DEFAULT 0, PRIMARY KEY (`id`), UNIQUE KEY `uv` (`user_id`,`vod_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $t[] = "CREATE TABLE IF NOT EXISTS `ky_play_record` (`id` int unsigned NOT NULL AUTO_INCREMENT, `user_id` int unsigned NOT NULL, `vod_id` int unsigned NOT NULL, `episode` int NOT NULL DEFAULT 1, `position` int unsigned NOT NULL DEFAULT 0, `updated` int unsigned DEFAULT 0, PRIMARY KEY (`id`), UNIQUE KEY `uv` (`user_id`,`vod_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $t[] = "CREATE TABLE IF NOT EXISTS `ky_sign` (`id` int unsigned NOT NULL AUTO_INCREMENT, `user_id` int unsigned NOT NULL, `day` int unsigned NOT NULL, `points` int NOT NULL DEFAULT 0, PRIMARY KEY (`id`), UNIQUE KEY `ud` (`user_id`,`day`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $t[] = "CREATE TABLE IF NOT EXISTS `ky_user_vod` (`id` int unsigned NOT NULL AUTO_INCREMENT, `user_id` int unsigned NOT NULL, `vod_id` int unsigned NOT NULL, `points` int NOT NULL DEFAULT 0, `created` int unsigned DEFAULT 0, PRIMARY KEY (`id`), UNIQUE KEY `uv` (`user_id`,`vod_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $t[] = "CREATE TABLE IF NOT EXISTS `ky_plugin` (`id` int unsigned NOT NULL AUTO_INCREMENT, `code` varchar(60) NOT NULL, `name` varchar(60) DEFAULT '', `type` varchar(10) NOT NULL DEFAULT 'plugin', `version` varchar(20) DEFAULT '1.0', `author` varchar(60) DEFAULT '', `status` tinyint NOT NULL DEFAULT 0, `expire` int unsigned NOT NULL DEFAULT 0, PRIMARY KEY (`id`), UNIQUE KEY `code` (`code`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $t[] = "CREATE TABLE IF NOT EXISTS `ky_collect_api` (`id` int unsigned NOT NULL AUTO_INCREMENT, `name` varchar(60) NOT NULL, `api_url` varchar(300) NOT NULL, `remark` varchar(200) DEFAULT '', `status` tinyint NOT NULL DEFAULT 1, `collect_auto` tinyint NOT NULL DEFAULT 0, `collect_hours` int NOT NULL DEFAULT 12, `addtime` int unsigned DEFAULT 0, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $t[] = "CREATE TABLE IF NOT EXISTS `ky_film_request` (`id` int unsigned NOT NULL AUTO_INCREMENT, `user_id` int unsigned NOT NULL, `title` varchar(120) NOT NULL, `note` varchar(500) DEFAULT '', `status` tinyint NOT NULL DEFAULT 0, `created` int unsigned DEFAULT 0, PRIMARY KEY (`id`), KEY `user_id` (`user_id`), KEY `status` (`status`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $t[] = "CREATE TABLE IF NOT EXISTS `ky_player` (`id` int unsigned NOT NULL AUTO_INCREMENT, `code` varchar(30) NOT NULL, `name` varchar(60) DEFAULT '', `parse` varchar(300) DEFAULT '', `status` tinyint NOT NULL DEFAULT 1, PRIMARY KEY (`id`), UNIQUE KEY `code` (`code`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $t[] = "CREATE TABLE IF NOT EXISTS `ky_admin_log` (`id` int unsigned NOT NULL AUTO_INCREMENT, `admin_id` int unsigned NOT NULL, `action` varchar(200) NOT NULL, `ip` varchar(64) DEFAULT '', `created` int unsigned DEFAULT 0, PRIMARY KEY (`id`), KEY `admin_id` (`admin_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    return $t;
}

$checks = env_checks($root);
$allOk = true;
foreach ($checks as $c) { if (!$c[1]) { $allOk = false; break; } }
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>坤影CMS · 安装向导</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,"PingFang SC","Microsoft YaHei",sans-serif;background:#0f1013;color:#e8e8ea;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.box{width:100%;max-width:640px;background:#17181d;border-radius:14px;padding:36px;box-shadow:0 20px 60px rgba(0,0,0,.5)}
.logo{display:flex;align-items:center;gap:10px;margin-bottom:6px}
.logo .i{width:34px;height:34px;background:#e5322d;border-radius:50%;display:flex;align-items:center;justify-content:center}
.logo .i::after{content:"";border-left:11px solid #fff;border-top:7px solid transparent;border-bottom:7px solid transparent;margin-left:3px}
.logo h1{font-size:20px}
.sub{color:#8b8e98;font-size:13px;margin-bottom:26px}
.steps{display:flex;gap:8px;margin-bottom:26px}
.steps span{flex:1;height:4px;background:#2a2c33;border-radius:2px}
.steps span.on{background:#e5322d}
table{width:100%;border-collapse:collapse;margin-bottom:18px}
td{padding:9px 6px;border-bottom:1px solid #23252c;font-size:14px}
td:last-child{text-align:right;color:#8b8e98;font-size:12px}
.yes{color:#3ecf72;font-weight:600}
.no{color:#e5322d;font-weight:600}
.field{margin-bottom:14px}
.field label{display:block;font-size:13px;color:#a7aab3;margin-bottom:6px}
input{width:100%;padding:11px 14px;background:#101116;border:1px solid #2a2c33;border-radius:8px;color:#e8e8ea;font-size:14px;outline:none}
input:focus{border-color:#e5322d}
.row{display:flex;gap:12px}
.row .field{flex:1}
.btn{width:100%;padding:13px;background:#e5322d;color:#fff;border:0;border-radius:9px;font-size:15px;font-weight:600;cursor:pointer;margin-top:8px}
.btn:disabled{background:#4a4c55;cursor:not-allowed}
.btn.plain{background:#26282f}
.tip{font-size:12px;color:#6f7280;margin-top:14px;text-align:center}
#loading{display:none;text-align:center;padding:30px 0}
.spin{width:38px;height:38px;border:3px solid #2a2c33;border-top-color:#e5322d;border-radius:50%;margin:0 auto 14px;animation:s 1s linear infinite}
@keyframes s{to{transform:rotate(360deg)}}
.err{background:#3a1d1f;border:1px solid #e5322d;color:#ff9c99;padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:14px;display:none}
@media(max-width:520px){.box{padding:24px}}
</style>
</head>
<body>
<div class="box">
  <div class="logo"><div class="i"></div><h1>坤影CMS 安装向导</h1></div>
  <div class="sub">KunYing CMS · 全新原创影视内容管理系统</div>
  <div class="steps"><span class="<?= $step >= 1 ? 'on' : '' ?>"></span><span class="<?= $step >= 2 ? 'on' : '' ?>"></span><span class="<?= $step >= 3 ? 'on' : '' ?>"></span></div>
  <div class="err" id="err"></div>

  <?php if ($step === 1): ?>
  <table>
    <?php foreach ($checks as $c): ?>
    <tr><td><?= htmlspecialchars($c[0]) ?></td><td class="<?= $c[1] ? 'yes' : 'no' ?>"><?= $c[1] ? '✓ 通过' : '✗ 不通过' ?><span style="margin-left:8px;opacity:.6"><?= htmlspecialchars($c[2]) ?></span></td></tr>
    <?php endforeach; ?>
  </table>
  <form method="get"><input type="hidden" name="step" value="2">
  <button class="btn" type="submit" <?= $allOk ? '' : 'disabled' ?>><?= $allOk ? '下一步:配置数据库' : '请先解决环境问题' ?></button></form>
  <div class="tip">环境检测通过后即可继续,全程约1分钟</div>

  <?php elseif ($step === 2): ?>
  <form id="f" onsubmit="return doInstall(event)">
    <div class="field"><label>数据库地址</label><input name="db_host" value="127.0.0.1" required></div>
    <div class="row">
      <div class="field"><label>端口</label><input name="db_port" value="3306" required></div>
      <div class="field"><label>数据库名(不存在会自动创建)</label><input name="db_name" value="kunying" required></div>
    </div>
    <div class="row">
      <div class="field"><label>数据库用户名</label><input name="db_user" value="root" required></div>
      <div class="field"><label>数据库密码</label><input name="db_pass" type="password"></div>
    </div>
    <div class="row">
      <div class="field"><label>管理员账号</label><input name="admin_user" placeholder="3-20位字母数字" required></div>
      <div class="field"><label>管理员密码(≥8位)</label><input name="admin_pass" type="password" minlength="8" required></div>
    </div>
    <div class="field"><label>确认管理员密码</label><input name="admin_repass" type="password" minlength="8" required></div>
    <button class="btn" type="submit" id="go">立即安装</button>
  </form>
  <div class="tip">点击安装后自动建库建表,无需任何手工操作</div>

  <?php else: ?>
  <div id="loading"><div class="spin"></div>正在安装,请稍候…</div>
  <?php endif; ?>
</div>
<script>
async function doInstall(ev){
  ev.preventDefault();
  var f=ev.target, d=new FormData(f), go=document.getElementById('go');
  go.disabled=true; go.textContent='正在安装…';
  try{
    var r=await fetch('?step=3',{method:'POST',body:d});
    var j=await r.json();
    if(j.code===1){
      var permTip = (j.perm_failed && j.perm_failed.length) ? '<div class="tip" style="color:#e5322d">以下目录权限未就绪(影响在线装主题),请SSH执行: chmod -R 755 '+j.perm_failed.join(' ')+'</div>' : '';
      document.querySelector('.box').innerHTML='<div class="logo"><div class="i"></div><h1>安装完成 🎉</h1></div><table><tr><td>后台地址</td><td style="color:#e5322d;font-weight:600">/admin.php</td></tr><tr><td>管理账号</td><td>'+f.admin_user.value.replace(/</g,'&lt;')+'</td></tr></table><a class="btn" style="display:block;text-align:center;text-decoration:none" href="/admin.php">进入后台</a><a class="btn plain" style="display:block;text-align:center;text-decoration:none" href="/">访问首页</a>'+permTip+(j.install_removed?'<div class="tip" style="color:#1a9c6b">✅ install 目录已自动删除</div>':'<div class="tip" style="color:#e5a03c">⚠ install 目录自动删除失败,请通过FTP/面板手动删除</div>');
    }else{
      var e=document.getElementById('err'); e.style.display='block'; e.textContent=j.msg;
      go.disabled=false; go.textContent='立即安装';
    }
  }catch(ex){
    var e=document.getElementById('err'); e.style.display='block'; e.textContent='请求异常:'+ex.message;
    go.disabled=false; go.textContent='立即安装';
  }
  return false;
}
</script>
</body>
</html>
