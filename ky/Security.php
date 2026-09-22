<?php
/**
 * 坤影CMS - 安全组件:CSRF、登录限流、会话加固
 */
if (!defined('KY_PATH')) exit('Access denied');

class Security
{
    /**
     * 启动安全会话
     */
    public static function session(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        $https = is_https();
        session_name('KYSID');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
        if (empty($_SESSION['_created'])) {
            $_SESSION['_created'] = time();
        }
        // 会话空闲超时 24h
        if (isset($_SESSION['_last']) && time() - $_SESSION['_last'] > 86400) {
            session_unset();
            session_regenerate_id(true);
            $_SESSION['_created'] = time();
        }
        $_SESSION['_last'] = time();
    }

    /**
     * CSRF Token
     */
    public static function csrfToken(): string
    {
        self::session();
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['_csrf'];
    }

    public static function csrfField(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::csrfToken()) . '">';
    }

    /**
     * 校验CSRF(表单或JSON)
     */
    public static function csrfCheck(): void
    {
        self::session();
        $token = $_POST['_csrf'] ?? (Request::jsonBody()['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        if (!is_string($token) || $token === '' || !hash_equals($_SESSION['_csrf'] ?? '', $token)) {
            if (Request::isAjax()) json_error('页面已过期,请刷新后重试', 403);
            halt_msg('非法请求,请刷新页面重试');
        }
    }

    /**
     * 登录失败限流:同账号+同IP 失败5次封禁1小时
     * @return array [banned(bool), remain_seconds]
     */
    public static function loginLimit(string $type, string $account): array
    {
        $ip = client_ip();
        $row = Db::fetch("SELECT * FROM ky_login_fail WHERE `type`=? AND account=? AND ip=?", [$type, $account, $ip]);
        if ($row && $row['ban_until'] > 0) {
            if ($row['ban_until'] > time()) {
                return [true, $row['ban_until'] - time()];
            }
            // 封禁已过,清除记录
            Db::delete('ky_login_fail', "`type`=? AND account=? AND ip=?", [$type, $account, $ip]);
            return [false, 0];
        }
        return [false, 0];
    }

    /**
     * 记录一次失败;达到5次设置1小时封禁,返回当前封禁状态
     */
    public static function loginFail(string $type, string $account): array
    {
        $ip = client_ip();
        Db::query(
            "INSERT INTO ky_login_fail (`type`,account,ip,fails,ban_until,updated_at) VALUES (?,?,?,1,0,?)
             ON DUPLICATE KEY UPDATE fails=fails+1, updated_at=VALUES(updated_at)",
            [$type, $account, $ip, time()]
        );
        $row = Db::fetch("SELECT * FROM ky_login_fail WHERE `type`=? AND account=? AND ip=?", [$type, $account, $ip]);
        if ($row && $row['fails'] >= 5) {
            Db::update('ky_login_fail', ['ban_until' => time() + 3600], "`type`=? AND account=? AND ip=?", [$type, $account, $ip]);
            return [true, 3600];
        }
        $left = 5 - (int)($row['fails'] ?? 0);
        return [false, $left];
    }

    public static function loginClear(string $type, string $account): void
    {
        Db::delete('ky_login_fail', "`type`=? AND account=? AND ip=?", [$type, $account, client_ip()]);
    }

    /**
     * 通用频率限制(按桶+客户端IP,文件缓存实现)
     * @return array [是否放行, 剩余额度]
     */
    public static function rateLimit(string $bucket, int $max, int $window): array
    {
        $key = 'rl_' . substr(md5($bucket . '|' . client_ip()), 0, 18);
        $now = time();
        $c = cache_get($key);
        if (!is_array($c) || !isset($c['start']) || $now - (int)$c['start'] >= $window) {
            $c = ['start' => $now, 'n' => 0];
        }
        $c['n'] = (int)$c['n'] + 1;
        cache_set($key, $c, $window);
        return [$c['n'] <= $max, max(0, $max - $c['n'])];
    }
}

function halt_msg(string $msg, string $backUrl = ''): void
{
    $back = $backUrl !== '' ? $backUrl : ($_SERVER['HTTP_REFERER'] ?? '/');
    echo '<!doctype html><html><head><meta charset="utf-8"><title>提示</title></head><body style="font-family:sans-serif;background:#101014;color:#eee;display:flex;flex-direction:column;align-items:center;justify-content:center;height:100vh;gap:16px"><p>' . e($msg) . '</p><a style="color:#e5322d" href="' . e($back) . '">返回</a></body></html>';
    exit;
}

