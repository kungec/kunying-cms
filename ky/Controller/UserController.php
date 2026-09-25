<?php
/**
 * 前台 - 用户:注册(邮箱验证)/登录/中心
 */
class UserController
{
    /* ===================== 注册 ===================== */

    public function register()
    {
        if (config('member_enable', '1') != '1') halt_msg('会员系统已关闭');
        if (config('register_enable', '1') != '1') halt_msg('注册功能已关闭');
        if (Request::isPost()) {
            Security::csrfCheck();
            if (Auth::isLogin()) json_error('您已登录');
            $email = strtolower(trim(Request::post('email')));
            $code = trim(Request::post('email_code'));
            $name = trim(Request::post('name', ''));
            $pwd = (string)Request::post('password');
            $re = (string)Request::post('repassword');

            // 人机验证(图形/极验/Turnstile,后台配置)
            [$vok, $vmsg] = Verify::check('user_register');
            if (!$vok) json_error($vmsg);

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_error('邮箱格式不正确');
            if (strlen($pwd) < 6) json_error('密码至少6位');
            if ($pwd !== $re) json_error('两次密码不一致');
            // 邮箱验证码(防爆破:同IP+邮箱 10分钟内最多10次尝试)
            $fk = 'ecf_' . substr(md5(client_ip() . '|' . $email), 0, 16);
            $fails = cache_get($fk);
            if (!is_array($fails)) $fails = ['n' => 0, 't' => time()];
            if ((int)$fails['n'] >= 10 && time() - (int)$fails['t'] < 600) {
                json_error('尝试次数过多,请10分钟后再试');
            }
            $row = Db::fetch("SELECT * FROM ky_email_code WHERE email=? AND code=? AND type='register' AND used=0 AND expire>? ORDER BY id DESC LIMIT 1", [$email, $code, time()]);
            if (!$row) {
                $fails['n'] = (int)$fails['n'] + 1;
                $fails['t'] = time();
                cache_set($fk, $fails, 600);
                json_error('邮箱验证码错误或已过期');
            }
            cache_del($fk);

            if (Db::fetchOne("SELECT id FROM ky_user WHERE email=?", [$email])) json_error('该邮箱已注册');
            $ip = client_ip();
            $regLimit = (int)Db::fetchOne("SELECT COUNT(*) FROM ky_user WHERE reg_ip=? AND reg_time>?", [$ip, time() - 86400]);
            if ($regLimit >= 5) json_error('当前IP今日注册次数已达上限');

            if ($name === '' || mb_strlen($name) > 20) $name = '用户' . rand_str(4, '0123456789');
            Db::update('ky_email_code', ['used' => 1], 'id=?', [$row['id']]);
            $uid = Db::insert('ky_user', [
                'email' => $email,
                'name' => $name,
                'pwd' => password_hash($pwd, PASSWORD_DEFAULT),
                'points' => (int)config('points_register', '0'),
                'status' => 1,
                'reg_ip' => $ip,
                'reg_time' => time(),
                'email_verified' => 1,
            ]);
            $u = Db::fetch("SELECT * FROM ky_user WHERE id=?", [$uid]);
            Auth::login($u);
            json_ok(['redirect' => '/user/center']);
        }
        View::display('user/register', []);
    }

    /**
     * 发送邮箱验证码
     */
    public function sendcode()
    {
        if (config('register_enable', '1') != '1' && config('member_enable', '1') != '1') json_error('功能未开放');
        Security::csrfCheck();
        // 每IP限流:防跨站刷信(邮件炸弹/SMTP配额燃烧)
        [$ok] = Security::rateLimit('sendcode', 10, 3600);
        if (!$ok) json_error('操作过于频繁,请稍后再试');
        if (config('smtp_host', '') === '') json_error('邮件服务未配置,请联系站长');
        // 频率限制:同IP 60秒1条,同邮箱 10分钟3条
        $ip = client_ip();
        if ((int)Db::fetchOne("SELECT COUNT(*) FROM ky_email_code WHERE created>?", [time() - 60]) > 200) {
            json_error('发送繁忙,请稍后再试');
        }
        $email = strtolower(trim(Request::post('email', '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_error('邮箱格式不正确');
        $last = Db::fetchOne("SELECT created FROM ky_email_code WHERE email=? ORDER BY id DESC LIMIT 1", [$email]);
        if ($last && time() - (int)$last < 60) json_error('发送过于频繁,请1分钟后再试');
        $count = (int)Db::fetchOne("SELECT COUNT(*) FROM ky_email_code WHERE email=? AND created>?", [$email, time() - 600]);
        if ($count >= 3) json_error('该邮箱验证码发送次数已达上限');

        $code = rand_str(6, '0123456789');
        Db::insert('ky_email_code', [
            'email' => $email, 'code' => $code, 'type' => 'register',
            'expire' => time() + 600, 'used' => 0, 'created' => time(),
        ]);
        $ok = Mailer::sendCode($email, $code);
        if (!$ok) json_error('邮件发送失败,请检查邮箱或稍后再试');
        json_ok(null, '验证码已发送,请查收邮箱');
    }

    /* ===================== 登录 ===================== */

    public function login()
    {
        if (config('member_enable', '1') != '1') halt_msg('会员系统已关闭');
        if (Request::isPost()) {
            Security::csrfCheck();
            if (Auth::isLogin()) json_ok(['redirect' => '/user/center']);
            $email = strtolower(trim(Request::post('email')));
            $pwd = (string)Request::post('password');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_error('邮箱格式不正确');

            // 同账号同IP 5次失败封1小时
            [$banned, $remain] = Security::loginLimit('user', $email);
            if ($banned) json_error('登录失败次数过多,该账号已被临时锁定,请' . ceil($remain / 60) . '分钟后再试');

            // 人机验证
            [$ok, $msg] = Verify::check('user_login');
            if (!$ok) json_error($msg);

            $u = Db::fetch("SELECT * FROM ky_user WHERE email=?", [$email]);
            if (!$u || !password_verify($pwd, $u['pwd'])) {
                [$b, $left] = Security::loginFail('user', $email);
                json_error($b ? '失败次数过多,账号已锁定1小时' : '账号或密码错误,还可尝试' . max(0, $left - 1) . '次');
            }
            if ($u['status'] != 1) json_error('账号已被禁用,请联系管理员');
            Security::loginClear('user', $email);
            Auth::login($u);
            json_ok(['redirect' => '/user/center']);
        }
        $redirect = Request::get('back', '');
        View::display('user/login', ['redirect' => $redirect]);
    }

    public function logout()
    {
        Auth::logout();
        redirect('/');
    }

    /* ===================== 求片 ===================== */

    public function filmreq()
    {
        $user = Auth::user();
        if (!$user) json_error('请先登录');
        if (!Request::isPost()) json_error('非法请求');
        Security::csrfCheck();
        $title = mb_substr(trim((string)Request::post('title')), 0, 60);
        $note = mb_substr(trim((string)Request::post('note')), 0, 300);
        if ($title === '') json_error('请填写影片名称');
        // 限流:同用户10分钟1条
        $last = Db::fetchOne("SELECT created FROM ky_film_request WHERE user_id=? ORDER BY id DESC LIMIT 1", [$user['id']]);
        if ($last && time() - (int)$last < 600) json_error('提交太频繁,请10分钟后再试');
        Db::insert('ky_film_request', ['user_id' => $user['id'], 'title' => $title, 'note' => $note, 'status' => 0, 'created' => time()]);
        json_ok(null, '求片已提交,我们会尽快上架!');
    }

    /* ===================== 账号设置(昵称/密码) ===================== */

    public function account()
    {
        $user = Auth::user();
        if (!$user) json_error('请先登录');
        if (!Request::isPost()) json_error('非法请求');
        Security::csrfCheck();
        $act = (string)Request::post('act');
        if ($act === 'nickname') {
            $name = trim((string)Request::post('name'));
            if (mb_strlen($name) < 2 || mb_strlen($name) > 20) json_error('昵称需2-20个字符');
            if (preg_match('/[<>"\'\\\/]/u', $name)) json_error('昵称含非法字符');
            Db::update('ky_user', ['name' => $name], 'id=?', [$user['id']]);
            json_ok(null, '昵称已更新');
        }
        if ($act === 'password') {
            $old = (string)Request::post('oldpwd');
            $new = (string)Request::post('newpwd');
            $re = (string)Request::post('repwd');
            if (!password_verify($old, (string)$user['pwd'])) json_error('当前密码错误');
            if (strlen($new) < 6) json_error('新密码至少6位');
            if ($new !== $re) json_error('两次密码不一致');
            Db::update('ky_user', ['pwd' => password_hash($new, PASSWORD_DEFAULT)], 'id=?', [$user['id']]);
            json_ok(null, '密码已修改,下次登录请使用新密码');
        }
        json_error('未知操作');
    }

    /* ===================== 忘记密码 ===================== */

    public function forgot()
    {
        if (config('member_enable', '1') != '1') halt_msg('会员系统已关闭');
        if (Request::isPost()) {
            Security::csrfCheck();
            $step = (string)Request::post('step', 'send');
            $email = strtolower(trim(Request::post('email', '')));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_error('邮箱格式不正确');
            $user = Db::fetch("SELECT * FROM ky_user WHERE email=?", [$email]);
            if ($step === 'send') {
                // 统一话术防邮箱枚举(不泄露邮箱是否已注册)
                if (!$user) json_ok(null, '若该邮箱已注册,重置码已发送,请查收邮箱');
                if ((int)Db::fetchOne("SELECT COUNT(*) FROM ky_email_code WHERE created>?", [time() - 60]) > 200) json_error('发送繁忙,请稍后再试');
                $last = Db::fetchOne("SELECT created FROM ky_email_code WHERE email=? AND type='reset' ORDER BY id DESC LIMIT 1", [$email]);
                if ($last && time() - (int)$last < 60) json_error('发送过于频繁,请1分钟后再试');
                if ((int)Db::fetchOne("SELECT COUNT(*) FROM ky_email_code WHERE email=? AND type='reset' AND created>?", [$email, time() - 600]) >= 3) json_error('验证码发送次数已达上限');
                if (config('smtp_host', '') === '') json_error('邮件服务未配置,请联系站长');
                [$mok] = Security::rateLimit('forgotmail', 10, 3600);
                if (!$mok) json_error('操作过于频繁,请稍后再试');
                $code = rand_str(6, '0123456789');
                Db::insert('ky_email_code', ['email' => $email, 'code' => $code, 'type' => 'reset', 'expire' => time() + 600, 'used' => 0, 'created' => time()]);
                if (!Mailer::sendCode($email, $code)) json_error('邮件发送失败,请稍后再试');
                json_ok(null, '重置码已发送,请查收邮箱');
            }
            // step=reset:验证并重置
            $code = trim((string)Request::post('code'));
            $pwd = (string)Request::post('password');
            $re = (string)Request::post('repassword');
            if (!$user) json_error('该邮箱未注册');
            if (strlen($pwd) < 6) json_error('新密码至少6位');
            if ($pwd !== $re) json_error('两次密码不一致');
            // 重置尝试限流:同IP+邮箱 10次/10分钟
            $fk = 'frs_' . substr(md5(client_ip() . '|' . $email), 0, 16);
            $fails = cache_get($fk);
            if (!is_array($fails)) $fails = ['n' => 0, 't' => time()];
            if ((int)$fails['n'] >= 10 && time() - (int)$fails['t'] < 600) json_error('尝试次数过多,请10分钟后再试');
            $row = Db::fetch("SELECT * FROM ky_email_code WHERE email=? AND code=? AND type='reset' AND used=0 AND expire>? ORDER BY id DESC LIMIT 1", [$email, $code, time()]);
            if (!$row) {
                $fails['n'] = (int)$fails['n'] + 1;
                $fails['t'] = time();
                cache_set($fk, $fails, 600);
                json_error('重置码错误或已过期');
            }
            cache_del($fk);
            Db::update('ky_email_code', ['used' => 1], 'id=?', [$row['id']]);
            Db::update('ky_user', ['pwd' => password_hash($pwd, PASSWORD_DEFAULT)], 'id=?', [$user['id']]);
            json_ok(['redirect' => '/user/login'], '密码已重置,请用新密码登录');
        }
        View::display('user/forgot', []);
    }

    /* ===================== 用户中心 ===================== */

    /**
     * 观看历史
     */
    public function history()
    {
        $user = Auth::user();
        if (!$user) redirect('/user/login');
        $records = Db::fetchAll(
            "SELECT r.*, v.name, v.pic, v.remarks FROM ky_play_record r JOIN ky_vod v ON v.id=r.vod_id WHERE r.user_id=? ORDER BY r.updated DESC LIMIT 60",
            [$user['id']]
        );
        View::display('user/history', ['records' => $records]);
    }

    /**
     * 当前用户云端播放记录(JSON,历史抽屉用)
     */
    public function recordjson()
    {
        $user = Auth::user();
        if (!$user) json_error('请先登录');
        $list = Db::fetchAll(
            "SELECT r.vod_id AS id, r.episode AS ep, r.updated AS time, v.name, v.pic, v.remarks FROM ky_play_record r JOIN ky_vod v ON v.id=r.vod_id WHERE r.user_id=? ORDER BY r.updated DESC LIMIT 50",
            [$user['id']]
        );
        json_ok(['list' => $list, 'logged' => 1]);
    }

    public function historyclear()
    {
        $user = Auth::user();
        if (!$user) json_error('请先登录');
        if (!Request::isPost()) json_error('非法请求');
        Security::csrfCheck();
        Db::delete('ky_play_record', 'user_id=?', [$user['id']]);
        json_ok(null, '观看历史已清空');
    }

    public function center()
    {
        if (config('member_enable', '1') != '1') halt_msg('会员系统已关闭');
        $user = Auth::user();
        if (!$user) redirect('/user/login');
        $tab = Request::get('tab', 'index');
        $data = [];
        if ($tab === 'filmreq') {
            $data['filmreqs'] = Db::fetchAll(
                "SELECT * FROM ky_film_request WHERE user_id=? ORDER BY id DESC LIMIT 20",
                [$user['id']]
            );
        } elseif ($tab === 'fav') {
            $data['favs'] = Db::fetchAll(
                "SELECT v.* FROM ky_fav f JOIN ky_vod v ON v.id=f.vod_id WHERE f.user_id=? ORDER BY f.id DESC LIMIT 100",
                [$user['id']]
            );
        } elseif ($tab === 'record') {
            $data['records'] = Db::fetchAll(
                "SELECT r.*,v.name,v.pic,v.remarks FROM ky_play_record r JOIN ky_vod v ON v.id=r.vod_id WHERE r.user_id=? ORDER BY r.updated DESC LIMIT 100",
                [$user['id']]
            );
        } elseif ($tab === 'orders') {
            $data['orders'] = Db::fetchAll("SELECT * FROM ky_order WHERE user_id=? ORDER BY id DESC LIMIT 50", [$user['id']]);
        }
        View::display('user/center', ['tab' => $tab, 'udata' => $data]);
    }

    /**
     * 每日签到领积分
     */
    public function sign()
    {
        $user = Auth::user();
        if (!$user) json_error('请先登录');
        if (Request::isPost()) Security::csrfCheck();
        $day = (int)date('Ymd');
        $points = max(1, (int)config('points_sign', '5'));
        // 原子签到:sign_day守卫防并发重复加积分,points走SQL自增避免旧值覆盖
        $stmt = Db::query("UPDATE ky_user SET points=points+{$points}, sign_day={$day} WHERE id=" . (int)$user['id'] . " AND sign_day<>{$day}");
        if ((int)$stmt->rowCount() !== 1) json_error('今天已签到');
        Db::insert('ky_sign', ['user_id' => $user['id'], 'day' => $day, 'points' => $points]);
        json_ok(['points' => $points], '签到成功,积分+' . $points);
    }
}
