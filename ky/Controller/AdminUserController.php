<?php
/**
 * 后台 - 用户/订单/套餐/评论管理
 */
class AdminUserController
{
    public function __construct()
    {
        Auth::requireAdmin();
        // 后台所有POST统一CSRF校验(GET视图不受影响)
        if (Request::isPost()) Security::csrfCheck();
    }

    /* ===================== 会员 ===================== */

    public function user()
    {
        $page = max(1, Request::get('page', 1, 'i'));
        $wd = trim(Request::get('wd', ''));
        $cond = '1'; $params = [];
        if ($wd !== '') { $cond .= ' AND (email LIKE ? OR name LIKE ?)'; $params[] = '%' . $wd . '%'; $params[] = '%' . $wd . '%'; }
        $pageSize = 20;
        $total = (int)Db::fetchOne("SELECT COUNT(*) FROM ky_user WHERE {$cond}", $params);
        $list = Db::fetchAll("SELECT * FROM ky_user WHERE {$cond} ORDER BY id DESC LIMIT {$pageSize} OFFSET " . (($page - 1) * $pageSize), $params);
        View::display('user', ['list' => $list, 'total' => $total, 'wd' => $wd, 'pageHtml' => page_html($total, $pageSize, $page, '/admin.php?s=/user/user&wd=' . urlencode($wd) . '&page={page}')]);
    }

    public function usersave()
    {
        if (!Request::isPost()) json_error('非法请求');
        $id = Request::post('id', 0, 'i');
        $user = Db::fetch("SELECT * FROM ky_user WHERE id=?", [$id]);
        if (!$user) json_error('用户不存在');
        $data = [
            'name' => mb_substr(trim(Request::post('name', $user['name'])), 0, 60),
            'points' => max(0, Request::post('points', $user['points'], 'i')),
            'status' => Request::post('status', 1, 'i') ? 1 : 0,
            'vip_expire' => max(0, (int)strtotime((string)Request::post('vip_expire', date('Y-m-d', (int)$user['vip_expire'] ?: time())))),
        ];
        $pwd = (string)Request::post('password');
        if ($pwd !== '') {
            if (strlen($pwd) < 6) json_error('新密码至少6位');
            $data['pwd'] = password_hash($pwd, PASSWORD_DEFAULT);
        }
        Db::update('ky_user', $data, 'id=?', [$id]);
        Admin::log('编辑会员#' . $id);
        json_ok();
    }

    public function userdels()
    {
        $ids = array_values(array_filter(array_map('intval', explode(',', (string)Request::post('ids')))));
        if (!$ids) json_error('请先勾选要删除的会员');
        if (count($ids) > 500) json_error('单次最多删除500条');
        $in = implode(',', $ids);
        Db::query("DELETE FROM ky_user WHERE id IN ({$in})");
        try { Db::query("DELETE FROM ky_comment WHERE user_id IN ({$in})"); } catch (Throwable $t) {}
        try { Db::query("DELETE FROM ky_fav WHERE user_id IN ({$in})"); } catch (Throwable $t) {}
        try { Db::query("DELETE FROM ky_play_record WHERE user_id IN ({$in})"); } catch (Throwable $t) {}
        try { Db::query("DELETE FROM ky_sign WHERE user_id IN ({$in})"); } catch (Throwable $t) {}
        try { Db::query("DELETE FROM ky_order WHERE user_id IN ({$in})"); } catch (Throwable $t) {}
        try { Db::query("DELETE FROM ky_login_fail WHERE account IN (SELECT email FROM ky_user WHERE 1=0)"); } catch (Throwable $t) {}
        Admin::log('批量删除会员 ' . count($ids) . ' 个(ID:' . $in . ')');
        json_ok(null, '已删除 ' . count($ids) . ' 个会员');
    }

    /**
     * 后台添加会员
     */
    public function useradd()
    {
        $email = mb_substr(trim((string)Request::post('email')), 0, 120);
        $name = mb_substr(trim((string)Request::post('name')), 0, 60);
        $pwd = (string)Request::post('password');
        $points = max(0, (int)Request::post('points', 0, 'i'));
        $vipDays = max(0, (int)Request::post('vip_days', 0, 'i'));
        if (!preg_match('#^[^@\s]+@[^@\s]+\.[a-z]{2,}$#i', $email)) json_error('邮箱格式不正确');
        if (strlen($pwd) < 6) json_error('密码至少6位');
        if (Db::fetch("SELECT id FROM ky_user WHERE email=?", [$email])) json_error('该邮箱已注册');
        $id = Db::insert('ky_user', [
            'email' => $email,
            'name' => $name !== '' ? $name : '用户' . mb_substr(md5($email), 0, 6),
            'pwd' => password_hash($pwd, PASSWORD_DEFAULT),
            'points' => $points,
            'vip_expire' => $vipDays > 0 ? time() + $vipDays * 86400 : 0,
            'email_verified' => 1,
            'status' => 1,
            'reg_ip' => client_ip(),
            'reg_time' => time(),
        ]);
        Admin::log('添加会员#' . $id . ':' . $email);
        json_ok(null, '添加成功:' . $email);
    }

    public function userdel()
    {
        $id = Request::post('id', 0, 'i');
        Db::delete('ky_user', 'id=?', [$id]);
        Admin::log('删除会员#' . $id);
        json_ok();
    }

    /**
     * 解除登录锁定
     */
    public function unlock()
    {
        Db::delete('ky_login_fail', 'ban_until>?', [time()]);
        Admin::log('解除全部登录锁定');
        json_ok(null, '已解除所有锁定');
    }

    /* ===================== 套餐 ===================== */

    public function goods()
    {
        $list = Db::fetchAll("SELECT * FROM ky_goods ORDER BY sort ASC, price ASC");
        View::display('goods', ['list' => $list]);
    }

    public function goodssave()
    {
        if (!Request::isPost()) json_error('非法请求');
        $id = Request::post('id', 0, 'i');
        $data = [
            'name' => mb_substr(trim(Request::post('name')), 0, 60),
            'price' => max(0.01, (float)Request::post('price', 0, 'f')),
            'points' => max(0, Request::post('points', 0, 'i')),
            'days' => max(0, Request::post('days', 0, 'i')),
            'sort' => Request::post('sort', 0, 'i'),
            'status' => Request::post('status', 1, 'i') ? 1 : 0,
        ];
        if ($data['name'] === '') json_error('名称不能为空');
        if ($data['points'] === 0 && $data['days'] === 0) json_error('积分或天数至少填写一项');
        if ($id > 0) Db::update('ky_goods', $data, 'id=?', [$id]);
        else Db::insert('ky_goods', $data);
        json_ok();
    }

    public function goodsdel()
    {
        Db::delete('ky_goods', 'id=?', [Request::post('id', 0, 'i')]);
        json_ok();
    }

    /* ===================== 订单 ===================== */

    public function order()
    {
        $page = max(1, Request::get('page', 1, 'i'));
        $status = Request::get('status', -1, 'i');
        $cond = '1'; $params = [];
        if ($status >= 0) { $cond .= ' AND o.status=?'; $params[] = $status; }
        $pageSize = 20;
        $total = (int)Db::fetchOne("SELECT COUNT(*) FROM ky_order o WHERE {$cond}", $params);
        $list = Db::fetchAll(
            "SELECT o.*,u.name uname,u.email FROM ky_order o LEFT JOIN ky_user u ON u.id=o.user_id WHERE {$cond} ORDER BY o.id DESC LIMIT {$pageSize} OFFSET " . (($page - 1) * $pageSize),
            $params
        );
        View::display('order', ['list' => $list, 'status' => $status, 'pageHtml' => page_html($total, $pageSize, $page, '/admin.php?s=/user/order&status=' . $status . '&page={page}')]);
    }

    /**
     * 批量清理订单(按状态)
     */
    public function orderclean()
    {
        $status = (int)Request::post('status', -1, 'i');
        $days = max(0, (int)Request::post('days', 0, 'i'));
        if (!in_array($status, [0, 2], true)) json_error('仅允许清理待支付或已取消订单');
        $cond = 'status=' . $status;
        $params = [];
        if ($days > 0) { $cond .= ' AND created < ?'; $params[] = time() - $days * 86400; }
        $n = (int)Db::fetchOne("SELECT COUNT(*) FROM ky_order WHERE {$cond}", $params);
        if ($n === 0) json_error('没有符合条件的订单');
        Db::query("DELETE FROM ky_order WHERE {$cond}", $params);
        Admin::log('清理订单:状态' . $status . ' 共' . $n . '条(超过' . $days . '天)');
        page_cache_flush();
        json_ok(null, '已清理 ' . $n . ' 条订单');
    }

    public function orderdel()
    {
        Db::delete('ky_order', 'id=?', [Request::post('id', 0, 'i')]);
        Admin::log('删除订单#' . Request::post('id', 0, 'i'));
        json_ok();
    }

    /* ===================== 评论 ===================== */

    public function comment()
    {
        $page = max(1, Request::get('page', 1, 'i'));
        $audit = Request::get('audit', -1, 'i');
        $pageSize = 20;
        $cond = '1';
        if ($audit == 0) $cond = 'status=0';
        elseif ($audit == 1) $cond = 'status=1';
        $total = (int)Db::fetchOne("SELECT COUNT(*) FROM ky_comment WHERE {$cond}");
        $list = Db::fetchAll(
            "SELECT c.*,u.name uname,v.name vname FROM ky_comment c LEFT JOIN ky_user u ON u.id=c.user_id LEFT JOIN ky_vod v ON v.id=c.vod_id WHERE {$cond} ORDER BY c.id DESC LIMIT {$pageSize} OFFSET " . (($page - 1) * $pageSize)
        );
        View::display('comment', ['list' => $list, 'audit' => $audit, 'pageHtml' => page_html($total, $pageSize, $page, '/admin.php?s=/user/comment&audit=' . $audit . '&page={page}')]);
    }

    /**
     * 批量清理评论(mode=0清理待审核 mode=1清空全部)
     */
    public function commentclean()
    {
        $mode = (int)Request::post('mode', 0, 'i');
        if ($mode === 1) {
            $n = (int)Db::fetchOne("SELECT COUNT(*) FROM ky_comment");
            Db::query("DELETE FROM ky_comment");
            Admin::log('清空全部评论 共' . $n . '条');
            json_ok(null, '已清空全部评论');
        }
        $n = (int)Db::fetchOne("SELECT COUNT(*) FROM ky_comment WHERE status=0");
        Db::query("DELETE FROM ky_comment WHERE status=0");
        Admin::log('清理待审核评论 共' . $n . '条');
        json_ok(null, '已清理 ' . $n . ' 条待审核评论');
    }

    public function commentdel()
    {
        Db::delete('ky_comment', 'id=?', [Request::post('id', 0, 'i')]);
        json_ok();
    }

    public function commentstatus()
    {
        $id = Request::post('id', 0, 'i');
        $status = Request::post('status', 1, 'i') ? 1 : 0;
        Db::update('ky_comment', ['status' => $status], 'id=?', [$id]);
        json_ok();
    }
}
