<?php
/**
 * 视频数据服务(首页/主题通用)
 */
class VodService
{
    public static function list(array $where, int $limit, int $page = 1): array
    {
        $cond = ['status=1'];
        $params = [];
        if (!empty($where['type_id'])) { $cond[] = 'type_id=?'; $params[] = (int)$where['type_id']; }
        if (!empty($where['vip'])) { $cond[] = 'vip=1'; }
        $order = in_array(($where['order'] ?? ''), ['total_hits DESC', 'addtime DESC', 'score DESC, total_hits DESC', 'id DESC'], true) ? $where['order'] : 'id DESC';
        $page = max(1, $page);
        $offset = ($page - 1) * $limit;
        $sql = "SELECT * FROM ky_vod WHERE " . implode(' AND ', $cond) . " ORDER BY {$order} LIMIT {$limit} OFFSET {$offset}";
        return Db::fetchAll($sql, $params);
    }

    /**
     * 探测来源是否可播放(首页地址有效性,结果缓存30分钟)
     */
    public static function probeSource(array $src): ?bool
    {
        $u = (string)($src['episodes'][0]['url'] ?? '');
        if ($u === '') return false;
        $ck = 'srcok_' . md5($u);
        $r = cache_get($ck);
        if ($r !== null) return (bool)$r;
        return null; // 未探测:由后台异步探测,绝不阻塞页面
    }

    /**
     * 实际执行探测并写缓存(仅在后台收尾阶段调用)
     */
    public static function probeDo(array $src): void
    {
        $u = (string)($src['episodes'][0]['url'] ?? '');
        if ($u === '') return;
        $ck = 'srcok_' . md5($u);
        if (cache_get($ck) !== null) return;
        $ok = self::probeUrl($u);
        if (!$ok && !preg_match('/\.[a-z0-9]{2,5}(\?|#|$)/i', $u)) {
            $ok = self::probeUrl(rtrim($u, '/') . '/index.m3u8');
        }
        // 成功缓存6小时;失败缓存10分钟便于自动恢复
        cache_set($ck, $ok ? 1 : 0, $ok ? 21600 : 600);
    }

    /**
     * 批量后台探测(响应已结束,不影响用户)
     */
    public static function probeAsync(array $sources): void
    {
        if (function_exists('session_write_close')) @session_write_close();
        @set_time_limit(120);
        foreach ($sources as $src) {
            try { self::probeDo($src); } catch (Throwable $t) {}
        }
    }

    private static function probeUrl(string $u): bool
    {
        $body = Http::getFollow($u, 4);
        if ($body === false || $body === '') return false;
        if (stripos($body, '#EXTM3U') !== false) return true;
        return strlen($body) > 20480; // 媒体二进制
    }

    /**
     * 解析播放来源与选集
     * play_from: "jsm3u8$$$jsyun"  play_url: "第01集$u1#第02集$u2$$$第01集$u1"
     */
    public static function parsePlay(array $vod): array
    {
        $froms = array_filter(explode('$$$', (string)$vod['play_from']));
        $groups = explode('$$$', (string)$vod['play_url']);
        $result = [];
        foreach ($froms as $i => $from) {
            $from = trim($from);
            if ($from === '') continue;
            $eps = [];
            $items = explode('#', $groups[$i] ?? '');
            foreach ($items as $j => $ep) {
                $pair = explode('$', $ep, 2);
                if (count($pair) !== 2) continue;
                $eps[] = ['name' => trim($pair[0]) !== '' ? trim($pair[0]) : '第' . ($j + 1) . '集', 'url' => trim($pair[1])];
            }
            if (empty($eps)) continue;
            $player = Db::fetch("SELECT * FROM ky_player WHERE `code`=?", [$from]);
            $result[] = [
                'from' => $from,
                'name' => $player['name'] ?? $from,
                'parse' => $player['parse'] ?? '',
                'episodes' => $eps,
            ];
        }
        return $result;
    }
}

/**
 * 后台公共助手
 */
class Admin
{
    public static function log(string $action): void
    {
        try {
            page_cache_flush();
            Db::insert('ky_admin_log', [
                'admin_id' => Auth::adminId(),
                'action' => mb_substr($action, 0, 180),
                'ip' => client_ip(),
                'created' => time(),
            ]);
        } catch (Throwable $t) {
        }
    }
}

/**
 * 坤影CMS - 全局助手函数
 * KunYing CMS
 */
if (!defined('KY_PATH')) exit('Access denied');

/**
 * HTML转义输出
 */
function e($str): string {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

/**
 * 获取客户端真实IP(支持CDN)
 */
function client_ip(): string {
    $cdn = config('cdn_mode');
    if ($cdn) {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP'] as $k) {
            if (!empty($_SERVER[$k]) && filter_var($_SERVER[$k], FILTER_VALIDATE_IP)) {
                return $_SERVER[$k];
            }
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            foreach ($ips as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * 当前是否HTTPS(兼容CDN/反代)
 */
function is_https(): bool {
    if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') return true;
    if (($_SERVER['SERVER_PORT'] ?? '') == '443') return true;
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') return true;
    if (!empty($_SERVER['HTTP_CF_VISITOR']) && stripos($_SERVER['HTTP_CF_VISITOR'], 'https') !== false) return true;
    return false;
}

function site_url(string $path = ''): string {
    $scheme = is_https() ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . '/' . ltrim($path, '/');
}

/**
 * 生成前台URL(支持REWRITE模式)
 */
function U(string $path, array $params = []): string {
    $url = '/' . trim($path, '/');
    if (!empty($params)) {
        $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($params);
    }
    return $url;
}

/**
 * 读取/写入配置(ky_config表 + 静态缓存)
 */
function config(string $key, $default = null) {
    if (!isset($GLOBALS['ky_config_loaded'])) {
        $GLOBALS['ky_config_loaded'] = true;
        $GLOBALS['ky_config'] = [];
        try {
            if (class_exists('Db')) {
                foreach (Db::fetchAll("SELECT `key`,`value` FROM ky_config") as $row) {
                    $GLOBALS['ky_config'][$row['key']] = $row['value'];
                }
            }
        } catch (\Throwable $t) { $GLOBALS['ky_config'] = []; }
    }
    return $GLOBALS['ky_config'][$key] ?? $default;
}

function config_set(string $key, string $value): void {
    config($key); // 确保配置已加载
    $GLOBALS['ky_config'][$key] = $value;
    Db::query("INSERT INTO ky_config (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)", [$key, $value]);
}

/**
 * JSON输出并终止
 */
function json_out($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function json_error(string $msg, int $code = 0): void {
    json_out(['code' => $code, 'msg' => $msg, 'data' => null]);
}

function json_ok($data = null, string $msg = 'ok'): void {
    json_out(['code' => 1, 'msg' => $msg, 'data' => $data]);
}

/**
 * 安全跳转(仅站内)
 */
function redirect(string $url): void {
    if (preg_match('#^https?://#i', $url)) {
        $host = parse_url($url, PHP_URL_HOST);
        if ($host && strcasecmp($host, $_SERVER['HTTP_HOST'] ?? '') !== 0) {
            $url = '/';
        }
    }
    header('Location: ' . $url);
    exit;
}

/**
 * 生成随机字符串
 */
function rand_str(int $len = 16, string $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'): string {
    $out = '';
    $max = strlen($chars) - 1;
    for ($i = 0; $i < $len; $i++) {
        $out .= $chars[random_int(0, $max)];
    }
    return $out;
}

function order_no(): string {
    return date('YmdHis') . rand_str(6, '0123456789');
}

/**
 * 将文本中的URL安全化输出(简介等,仅允许换行)
 */
function nl2text(string $str): string {
    return nl2br(e($str));
}

/**
 * 友好时间
 */
function friend_date(int $time): string {
    $diff = time() - $time;
    if ($diff < 60) return '刚刚';
    if ($diff < 3600) return floor($diff / 60) . '分钟前';
    if ($diff < 86400) return floor($diff / 3600) . '小时前';
    if ($diff < 2592000) return floor($diff / 86400) . '天前';
    return date('Y-m-d', $time);
}

/**
 * 图片地址补全(空图占位)
 */
function pic_url(?string $pic): string {
    $pic = trim((string)$pic);
    if ($pic === '') return '/static/img/nopic.png';
    if (preg_match('#^https?://#i', $pic)) {
        return img_proxy_url($pic);
    }
    return $pic[0] === '/' ? $pic : '/' . ltrim($pic, '/');
}

/**
 * 远程图片同源代理地址(本站磁盘缓存,解决外链慢/防盗链/混合内容)
 */
function img_proxy_url(string $url): string {
    $sg = md5($url . '|' . date('Ymd') . '|' . app_key());
    return '/index.php?s=/proxy/img&u=' . rawurlencode($url) . '&sg=' . $sg;
}

/**
 * 主题资源路径
 */
function theme_url(string $path = ''): string {
    return '/theme/' . rawurlencode(active_theme()) . '/' . ltrim($path, '/');
}

function theme_path(string $path = ''): string {
    return KY_PATH . '/theme/' . active_theme() . '/' . ltrim($path, '/');
}

/**
 * 当前生效主题(配置缺失或目录不存在时回落dsv1,防止站点打不开)
 */
function active_theme(): string {
    static $tpl = null;
    if ($tpl === null) {
        $tpl = strtolower(basename((string)config('site_template', 'kylite')));
        if ($tpl === '' || !is_dir(KY_PATH . '/theme/' . $tpl)) {
            $tpl = 'kylite';
        }
    }
    return $tpl;
}

/**
 * 缓存(文件)
 */
function cache_get(string $key) {
    $file = KY_PATH . '/data/cache/' . md5($key) . '.php';
    if (!is_file($file)) return null;
    $data = @unserialize(file_get_contents($file));
    if (!is_array($data)) return null;
    if ($data['expire'] > 0 && $data['expire'] < time()) { @unlink($file); return null; }
    return $data['value'];
}

function cache_set(string $key, $value, int $ttl = 300): void {
    $file = KY_PATH . '/data/cache/' . md5($key) . '.php';
    @file_put_contents($file, serialize(['expire' => $ttl > 0 ? time() + $ttl : 0, 'value' => $value]), LOCK_EX);
}

function cache_del(string $key): void {
    @unlink(KY_PATH . '/data/cache/' . md5($key) . '.php');
}

/* ===================== 伪静态(输出层URL重写) ===================== */
function rewrite_html(string $html): string {
    if (config('rewrite_enable', '0') != '1') return $html;
    if (strpos($html, '/index.php?s=') === false) return $html;
    // 详情: /index.php?s=/vod/detail&id=5&ep=2 -> /detail-5.html?ep=2
    $html = preg_replace_callback('#/index\.php\?s=/vod/detail&(?:amp;)?id=(\d+)((?:&(?:amp;)?)[^"\'<>]*)?#', function ($m) {
        $rest = isset($m[2]) ? ltrim(str_replace('&amp;', '&', $m[2]), '&') : '';
        $rest = $rest !== '' ? '?' . ltrim($rest, '?') : '';
        return '/detail-' . $m[1] . '.html' . $rest;
    }, $html);
    // 分类: /index.php?s=/vod/type&id=X&order=Y -> /type-X.html?order=Y
    $html = preg_replace_callback('#/index\.php\?s=/vod/type&(?:amp;)?id=(\d+)((?:&(?:amp;)?)[^"\'<>]*)?#', function ($m) {
        $rest = isset($m[2]) ? ltrim(str_replace('&amp;', '&', $m[2]), '&') : '';
        $rest = $rest !== '' ? '?' . ltrim($rest, '?') : '';
        return '/type-' . $m[1] . '.html' . $rest;
    }, $html);
    // 通用: /index.php?s=/a/b&args -> /a/b?args ; /index.php?s=/a/b -> /a/b
    $html = preg_replace('#/index\.php\?s=/([^"\'&<>\s]+)&amp;#', '/$1?', $html);
    $html = preg_replace('#/index\.php\?s=/([^"\'&<>\s]+)&#', '/$1?', $html);
    $html = preg_replace('#/index\.php\?s=/([^"\'&<>\s]+)#', '/$1', $html);
    return $html;
}

/* ===================== 授权地址加密存储(后台/数据库均不明文) ===================== */
function ky_xor(string $s, string $k): string {
    if ($k === '') return $s;
    $out = '';
    for ($i = 0; $i < strlen($s); $i++) $out .= $s[$i] ^ $k[$i % strlen($k)];
    return $out;
}
function api_site_enc(string $url): string {
    return base64_encode(ky_xor($url, (string)config('key', 'kunying')));
}
function api_site_dec(string $v): string {
    if ($v === '') return '';
    $raw = base64_decode($v, true);
    if ($raw === false) return $v; // 兼容历史明文
    $dec = ky_xor($raw, (string)config('key', 'kunying'));
    return preg_match('#^https?://#i', $dec) ? $dec : $v;
}

/* ===================== 付费主题加密运行时(未授权无法解密执行) ===================== */
function ky_theme_run(string $code, string $enc, array $vars = []): void {
    static $dec = [];
    static $auth = [];
    if (!isset($auth[$code])) $auth[$code] = License::check($code);
    if (empty($auth[$code])) {
        echo '<div style="text-align:center;padding:80px 20px;color:#e5322d;font-size:17px;font-weight:600">🔒 该主题未授权或授权已过期<br><span style="font-size:13px;color:#9aa0ad;font-weight:400">请在后台重新验证授权,或前往授权站购买</span></div>';
        return;
    }
    if (isset($dec[$enc])) { extract($vars, EXTR_SKIP); eval('?>' . $dec[$enc]); return; }
    $ck = cache_get('theme_key');
    $tkey = (is_string($ck) && $ck !== '') ? $ck : 'KyTheme#x7P9mQ4vZ';
    $src = ky_xor(base64_decode($enc, true), $tkey);
    if ($src === false || stripos($src, '<?php') !== 0) { echo '<div style="text-align:center;padding:60px;color:#e5322d">主题文件校验失败</div>'; return; }
    $dec[$enc] = $src;
    extract($vars, EXTR_SKIP);
    eval('?>' . $src);
}

/* ===================== 未引用图片清理(手动/每日凌晨自动) ===================== */
function img_clean_orphans(): int {
    $dir = KY_PATH . '/upload/vod';
    if (!is_dir($dir)) return 0;
    $used = [];
    foreach (Db::fetchAll("SELECT pic FROM ky_vod WHERE pic LIKE '%/upload/vod/%' LIMIT 5000") as $r) {
        if (preg_match_all('#/upload/vod/([A-Za-z0-9_\-\.]+)#', (string)$r['pic'], $m)) {
            foreach ($m[1] as $n) $used[strtolower($n)] = 1;
        }
    }
    foreach (Db::fetchAll("SELECT pic FROM ky_slide WHERE pic LIKE '%/upload/vod/%'") as $r) {
        $used[strtolower(basename((string)$r['pic']))] = 1;
    }
    $n = 0;
    foreach (glob($dir . '/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE) ?: [] as $f) {
        if (!isset($used[strtolower(basename($f))])) { @unlink($f); $n++; }
    }
    return $n;
}

/* ===================== 游客页面缓存(后台任何操作自动失效) ===================== */
function page_cache_get(string $key): ?string {
    $f = KY_PATH . '/data/cache/pages/' . md5($key) . '.html';
    if (!is_file($f)) return null;
    $raw = @file_get_contents($f);
    if ($raw === false || strlen($raw) < 12) return null;
    $exp = (int)substr($raw, 0, 11);
    if ($exp > 0 && $exp < time()) { @unlink($f); return null; }
    return substr($raw, 11);
}
function page_cache_set(string $key, string $html, int $ttl = 600): void {
    $dir = KY_PATH . '/data/cache/pages';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    @file_put_contents($dir . '/' . md5($key) . '.html', str_pad((string)(time() + $ttl), 11, '0', STR_PAD_LEFT) . $html, LOCK_EX);
}
function page_cache_flush(): void {
    foreach (glob(KY_PATH . '/data/cache/pages/*.html') ?: [] as $f) @unlink($f);
}

/**
 * 分页HTML(简洁样式)
 */
function page_html(int $total, int $pageSize, int $page, string $urlPattern): string {
    $totalPage = max(1, (int)ceil($total / $pageSize));
    if ($totalPage <= 1) return '';
    $page = max(1, min($page, $totalPage));
    $html = '<div class="ky-page">';
    $url = function ($p) use ($urlPattern) { return str_replace('{page}', (string)$p, $urlPattern); };
    if ($page > 1) $html .= '<a href="' . e($url($page - 1)) . '">上一页</a>';
    $start = max(1, $page - 2);
    $end = min($totalPage, $page + 2);
    if ($start > 1) $html .= '<a href="' . e($url(1)) . '">1</a>' . ($start > 2 ? '<span>...</span>' : '');
    for ($i = $start; $i <= $end; $i++) {
        $html .= $i == $page ? '<span class="cur">' . $i . '</span>' : '<a href="' . e($url($i)) . '">' . $i . '</a>';
    }
    if ($end < $totalPage) $html .= ($end < $totalPage - 1 ? '<span>...</span>' : '') . '<a href="' . e($url($totalPage)) . '">' . $totalPage . '</a>';
    if ($page < $totalPage) $html .= '<a href="' . e($url($page + 1)) . '">下一页</a>';
    $html .= '</div>';
    return $html;
}

/**
 * 应用密钥(installer生成)
 */
function app_key(): string
{
    static $key = null;
    if ($key === null) {
        $conf = is_file(KY_PATH . '/data/config.php') ? include KY_PATH . '/data/config.php' : [];
        $key = (string)($conf['key'] ?? 'kunying');
    }
    return $key;
}

/**
 * m3u8同源代理地址(解决资源站防盗链/CORS)
 */
function m3u8_proxy_url(string $url): string
{
    $sg = md5($url . '|' . date('Ymd') . '|' . app_key());
    return '/index.php?s=/proxy/m3u8&u=' . rawurlencode($url) . '&sg=' . $sg;
}

/**
 * 广告位输出(后台可独立开关;代码为管理员填写的原始HTML/JS)
 */
function ad_slot(string $key): string {
    if (config($key . '_enable', '0') != '1') return '';
    $code = trim((string)config($key . '_code', ''));
    if ($code === '') return '';
    return '<div class="ad-slot" data-ad="' . e($key) . '">' . $code . '</div>';
}

/**
 * 静态资源版本号(文件修改时间,改动自动失效缓存)
 */
function asset_v(string $rel): string {
    static $c = [];
    if (!isset($c[$rel])) {
        $f = KY_PATH . $rel;
        $c[$rel] = is_file($f) ? (string)filemtime($f) : (string)KY_RELEASE;
    }
    return $c[$rel];
}

/**
 * 资源URL(自动带文件修改时间版本;$sitePath为站内绝对路径,如 /theme/dsv1/static/js/x.js)
 */
function asset_v_url(string $sitePath): string {
    return $sitePath . '?v=' . asset_v($sitePath);
}
