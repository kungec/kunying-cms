<?php
/**
 * 前台 - 充值购买与支付
 */
class PayController
{
    /**
     * 充值套餐列表
     */
    public function index()
    {
        if (config('member_enable', '1') != '1') halt_msg('会员系统已关闭');
        $user = Auth::user();
        if (!$user) redirect('/user/login?back=/pay');
        $goods = Db::fetchAll("SELECT * FROM ky_goods WHERE status=1 ORDER BY sort ASC, price ASC");
        View::display('pay/index', ['goods' => $goods]);
    }

    /**
     * 创建订单并跳转/展示支付
     */
    public function create()
    {
        $user = Auth::user();
        if (!$user) json_error('请先登录');
        if (Request::isPost()) Security::csrfCheck();

        $goodsId = Request::jsonPost('goods_id', 0, 'i');
        $payType = Request::jsonPost('pay_type');
        $allow = ['codepay', 'epay', 'usdt', 'wxpay', 'alipay', 'alipayf2f'];
        if (!in_array($payType, $allow, true)) json_error('请选择支付方式');
        $goods = Db::fetch("SELECT * FROM ky_goods WHERE id=? AND status=1", [$goodsId]);
        if (!$goods) json_error('套餐不存在');

        $orderNo = order_no();
        $orderId = Db::insert('ky_order', [
            'order_no' => $orderNo,
            'user_id' => $user['id'],
            'goods_id' => $goods['id'],
            'type' => $goods['days'] > 0 ? 'vip' : 'points',
            'title' => $goods['name'],
            'amount' => $goods['price'],
            'pay_type' => $payType,
            'status' => 0,
            'created' => time(),
        ]);
        try {
            $ret = Pay::create($payType, [
                'order_no' => $orderNo,
                'money' => (float)$goods['price'],
                'title' => $goods['name'],
                'notify_url' => site_url('index.php?s=/api/notify/' . $payType . '/' . $orderNo),
                'return_url' => site_url('index.php?s=/pay/result&ono=' . $orderNo),
            ]);
        } catch (Throwable $t) {
            Db::update('ky_order', ['status' => 2], 'id=?', [$orderId]);
            json_error($t->getMessage());
        }
        if (($ret['type'] ?? '') === 'qrcode') {
            cache_set('qr_' . $orderNo, $ret['qr'], 3600);
        }
        json_ok(['order_no' => $orderNo] + $ret);
    }

    /**
     * 收银台页(二维码/跳转)
     */
    public function cashier()
    {
        $user = Auth::user();
        if (!$user) redirect('/user/login');
        $ono = Request::get('ono');
        $order = Db::fetch("SELECT * FROM ky_order WHERE order_no=? AND user_id=?", [$ono, $user['id']]);
        if (!$order) halt_msg('订单不存在');
        View::display('pay/cashier', ['order' => $order, 'qrData' => (string)(cache_get('qr_' . $ono) ?? '')]);
    }

    /**
     * USDT订单支付信息(收银台展示用)
     */
    public function usdtinfo()
    {
        $user = Auth::user();
        if (!$user) json_error('未登录');
        $ono = trim(Request::get('ono'));
        $order = Db::fetch("SELECT * FROM ky_order WHERE order_no=? AND user_id=? AND pay_type='usdt'", [$ono, $user['id']]);
        if (!$order) json_error('订单不存在');
        if ((int)$order['status'] === 1) json_ok(['address' => (string)config('usdt_address'), 'amount' => (string)($order['usdt_amount'] ?? ''), 'paid' => true]);
        // 计算金额(幂等)
        $addr = trim((string)config('usdt_address', ''));
        $rate = (float)config('usdt_rate', '0');
        if ($addr === '' || $rate <= 0) json_error('USDT收款未配置');
        if ((float)($order['usdt_amount'] ?? 0) <= 0) {
            Pay::create('usdt', ['order_no' => $ono, 'money' => (float)$order['amount'], 'title' => $order['title']]);
            $order = Db::fetch("SELECT * FROM ky_order WHERE id=?", [$order['id']]);
        }
        json_ok(['address' => $addr, 'amount' => number_format((float)$order['usdt_amount'], 2, '.', ''), 'paid' => false]);
    }

    /**
     * 订单状态轮询(收银台AJAX)
     */
    public function check()
    {
        $user = Auth::user();
        if (!$user) json_error('未登录');
        $ono = trim(Request::get('ono'));
        $order = Db::fetch("SELECT * FROM ky_order WHERE order_no=? AND user_id=?", [$ono, $user['id']]);
        if (!$order) json_error('订单不存在');
        if ($order['status'] == 0 && $order['pay_type'] === 'usdt') {
            self::checkUsdt($order);
            $order = Db::fetch("SELECT * FROM ky_order WHERE id=?", [$order['id']]);
        }
        json_ok(['status' => (int)$order['status']]);
    }

    /**
     * USDT入账查询
     */
    public static function checkUsdt(array $order): void
    {
        $txid = Pay::usdtCheck($order);
        if ($txid !== null) {
            self::complete($order['order_no'], $txid);
        }
    }

    /**
     * 支付完成发货(积分/VIP)
     */
    public static function complete(string $orderNo, string $tradeNo, ?float $money = null): bool
    {
        $order = Db::fetch("SELECT * FROM ky_order WHERE order_no=? AND status=0", [$orderNo]);
        if (!$order) return false;
        // 回调金额与订单金额不符即拒绝入账(防可改金额渠道绕过)
        if ($money !== null && abs($money - (float)$order['amount']) > 0.01) return false;
        Db::begin();
        try {
            $n = Db::update('ky_order', ['status' => 1, 'trade_no' => mb_substr($tradeNo, 0, 60), 'paid_time' => time()], 'order_no=? AND status=0', [$orderNo]);
            if ($n !== 1) throw new RuntimeException('订单状态已变更');
            $user = Db::fetch("SELECT * FROM ky_user WHERE id=? FOR UPDATE", [$order['user_id']]);
            if (!$user) throw new RuntimeException('用户不存在');
            $goods = Db::fetch("SELECT * FROM ky_goods WHERE id=?", [$order['goods_id']]);
            if ($goods) {
                if ($goods['days'] > 0) {
                    $base = max(time(), (int)$user['vip_expire']);
                    Db::update('ky_user', ['vip_expire' => $base + $goods['days'] * 86400], 'id=?', [$user['id']]);
                }
                if ($goods['points'] > 0) {
                    Db::update('ky_user', ['points' => $user['points'] + $goods['points']], 'id=?', [$user['id']]);
                }
            }
            Db::commit();
            return true;
        } catch (Throwable $t) {
            Db::rollback();
            return false;
        }
    }

    /**
     * 支付结果页
     */
    public function result()
    {
        $user = Auth::user();
        if (!$user) redirect('/user/login');
        $ono = Request::get('ono');
        $order = Db::fetch("SELECT * FROM ky_order WHERE order_no=? AND user_id=?", [$ono, $user['id']]);
        View::display('pay/result', ['order' => $order]);
    }

    /**
     * 购买单部付费影片(积分)
     */
    public function buyvod()
    {
        $user = Auth::user();
        if (!$user) json_error('请先登录');
        if (Request::isPost()) Security::csrfCheck();
        $vid = Request::jsonPost('id', 0, 'i');
        $vod = Db::fetch("SELECT * FROM ky_vod WHERE id=? AND status=1 AND points>0", [$vid]);
        if (!$vod) json_error('影片不存在或无需购买');
        if (VodController::hasBought($vid)) json_ok(null, '已购');
        Db::begin();
        try {
            $u = Db::fetch("SELECT points FROM ky_user WHERE id=? FOR UPDATE", [$user['id']]);
            if ((int)$u['points'] < (int)$vod['points']) throw new RuntimeException('积分不足,请先充值');
            Db::update('ky_user', ['points' => $u['points'] - (int)$vod['points']], 'id=?', [$user['id']]);
            Db::insert('ky_user_vod', ['user_id' => $user['id'], 'vod_id' => $vid, 'points' => (int)$vod['points'], 'created' => time()]);
            Db::commit();
            json_ok(['points' => $u['points'] - (int)$vod['points']], '购买成功');
        } catch (Throwable $t) {
            Db::rollback();
            json_error($t->getMessage());
        }
    }
}
