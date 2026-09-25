<?php
/**
 * 坤影CMS - 认证组件(前台用户 / 后台管理员)
 */
if (!defined('KY_PATH')) exit('Access denied');

class Auth
{
    /* ===================== 前台用户 ===================== */

    public static function userId(): int
    {
        return (int)($_SESSION['user_id'] ?? 0);
    }

    public static function user(): ?array
    {
        static $user = false;
        if ($user === false) {
            $uid = self::userId();
            $user = $uid > 0 ? Db::fetch("SELECT * FROM ky_user WHERE id=? AND status=1", [$uid]) : null;
        }
        return $user;
    }

    public static function isLogin(): bool
    {
        return self::userId() > 0;
    }

    public static function login(array $user): void
    {
        Security::session();
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        // 顶栏登录态显示用的明文cookie(缓存页JS修正脚本读取)
        setcookie('ky_name', (string)$user['name'], 0, '/', '', is_https(), false);
        Db::update('ky_user', [
            'last_login_time' => time(),
            'last_login_ip' => client_ip(),
        ], 'id=?', [$user['id']]);
    }

    public static function logout(): void
    {
        Security::session();
        unset($_SESSION['user_id']);
        setcookie('ky_name', '', time() - 3600, '/');
    }

    public static function isVip(): bool
    {
        $u = self::user();
        return $u && $u['vip_expire'] > 0 && $u['vip_expire'] > time();
    }

    /* ===================== 后台管理员 ===================== */

    public static function adminId(): int
    {
        return (int)($_SESSION['admin_id'] ?? 0);
    }

    public static function admin(): ?array
    {
        static $admin = false;
        if ($admin === false) {
            $id = self::adminId();
            $admin = $id > 0 ? Db::fetch("SELECT * FROM ky_admin WHERE id=?", [$id]) : null;
        }
        return $admin;
    }

    public static function adminLogin(array $admin): void
    {
        Security::session();
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int)$admin['id'];
        // 会话令牌绑定当前密码哈希:改密后所有旧会话自动失效
        $_SESSION['admin_token'] = self::adminSessionToken($admin);
    }

    private static function adminSessionToken(array $admin): string
    {
        return hash_hmac('sha256', (string)$admin['pwd'], 'ky_admin_session');
    }

    public static function adminLogout(): void
    {
        Security::session();
        unset($_SESSION['admin_id'], $_SESSION['admin_token']);
    }

    /**
     * 后台鉴权守卫:未登录跳转登录页;POST请求校验CSRF
     */
    public static function requireAdmin(): void
    {
        $admin = self::admin();
        if (!$admin) {
            if (Request::isAjax()) json_error('登录已失效,请重新登录', 401);
            redirect('/admin.php?s=/main/login');
        }
        // 会话令牌校验:令牌绑定密码哈希,密码变更后旧会话立即失效
        $expect = self::adminSessionToken($admin);
        if (!hash_equals($expect, (string)($_SESSION['admin_token'] ?? ''))) {
            self::adminLogout();
            if (Request::isAjax()) json_error('登录已失效,请重新登录', 401);
            redirect('/admin.php?s=/main/login');
        }
        if (Request::isPost()) {
            Security::csrfCheck();
        }
    }
}
