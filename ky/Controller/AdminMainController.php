<?php
/**
 * 后台 - 登录/仪表盘
 */
class AdminMainController
{
    public function index()
    {
        if (!Auth::admin()) redirect('/admin.php?s=/main/login');
        redirect('/admin.php?s=/main/dashboard');
    }

    public function login()
    {
        if (Auth::admin()) redirect('/admin.php?s=/main/dashboard');
        if (Request::isPost()) {
            Security::csrfCheck();
            $username = trim(Request::post('username'));
            $pwd = (string)Request::post('password');

            [$banned, $remain] = Security::loginLimit('admin', $username);
            if ($banned) json_error('失败次数过多,已锁定' . ceil($remain / 60) . '分钟');

            [$ok, $msg] = Verify::check('admin_login');
            if (!$ok) json_error($msg);

            $admin = Db::fetch("SELECT * FROM ky_admin WHERE username=?", [$username]);
            if (!$admin || !password_verify($pwd, $admin['pwd'])) {
                Security::loginFail('admin', $username);
                json_error('账号或密码错误');
            }
            Security::loginClear('admin', $username);
            Auth::adminLogin($admin);
            Db::update('ky_admin', ['login_time' => time(), 'login_ip' => client_ip()], 'id=?', [$admin['id']]);
            Admin::log('登录后台');
            json_ok(['redirect' => '/admin.php?s=/main/dashboard']);
        }
        View::display('login', []);
    }

    public function logout()
    {
        Auth::adminLogout();
        redirect('/admin.php?s=/main/login');
    }

    public function dashboard()
    {
        Auth::requireAdmin();
        $stats = [
            'vod' => (int)Db::fetchOne("SELECT COUNT(*) FROM ky_vod"),
            'today_vod' => (int)Db::fetchOne("SELECT COUNT(*) FROM ky_vod WHERE addtime>?", [strtotime('today')]),
            'user' => (int)Db::fetchOne("SELECT COUNT(*) FROM ky_user"),
            'today_user' => (int)Db::fetchOne("SELECT COUNT(*) FROM ky_user WHERE reg_time>?", [strtotime('today')]),
            'order_paid' => (int)Db::fetchOne("SELECT COUNT(*) FROM ky_order WHERE status=1"),
            'order_money' => (float)(Db::fetchOne("SELECT COALESCE(SUM(amount),0) FROM ky_order WHERE status=1") ?? 0),
            'today_money' => (float)(Db::fetchOne("SELECT COALESCE(SUM(amount),0) FROM ky_order WHERE status=1 AND paid_time>?", [strtotime('today')]) ?? 0),
            'comment' => (int)Db::fetchOne("SELECT COUNT(*) FROM ky_comment"),
        ];
        $week = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = strtotime("today -{$i} day");
            $week[] = [
                'date' => date('m-d', $d),
                'vod' => (int)Db::fetchOne("SELECT COUNT(*) FROM ky_vod WHERE addtime BETWEEN ? AND ?", [$d, $d + 86400]),
                'money' => (float)(Db::fetchOne("SELECT COALESCE(SUM(amount),0) FROM ky_order WHERE status=1 AND paid_time BETWEEN ? AND ?", [$d, $d + 86400]) ?? 0),
            ];
        }
        $latestOrders = Db::fetchAll("SELECT o.*,u.name,u.email FROM ky_order o LEFT JOIN ky_user u ON u.id=o.user_id ORDER BY o.id DESC LIMIT 8");
        $notices = License::notices();
        View::display('dashboard', compact('stats', 'week', 'latestOrders', 'notices'));
    }

    /**
     * 拉取授权站公告(后台手动刷新)
     */
    public function notice()
    {
        Auth::requireAdmin();
        cache_del('api_notices');
        $list = License::notices();
        json_ok($list);
    }
}