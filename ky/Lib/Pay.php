<?php
/**
 * 支付库:码支付 / 易支付 / USDT免挂TRC20 / 微信官方Native / 支付宝官方 / 支付宝当面付
 * 每个驱动统一返回:
 *   pay() => ['type'=>'redirect','url'=>..] | ['type'=>'qrcode','qr'=>..,'tip'=>..]
 *   notify() => ['out_trade_no'=>..,'trade_no'=>..,'money'=>..] 校验失败返回null
 */
if (!defined('KY_PATH')) exit('Access denied');

class Pay
{
    public const ALI_GATEWAY = 'https://openapi.alipay.com/gateway.php';
    public const WX_GATEWAY = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
    public const TRC20_CONTRACT = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';

    /**
     * 发起支付
     * @param string $payType codepay|epay|usdt|wxpay|alipay|alipayf2f
     * @param array $order ['order_no','money','title','notify_url','return_url']
     */
    public static function create(string $payType, array $order): array
    {
        switch ($payType) {
            case 'codepay':
            case 'epay':
                return self::epayPay($payType, $order);
            case 'usdt':
                return self::usdtPay($order);
            case 'wxpay':
                return self::wxPay($order);
            case 'alipay':
                return self::alipayPage($order);
            case 'alipayf2f':
                return self::alipayF2f($order);
            default:
                throw new InvalidArgumentException('不支持的支付方式');
        }
    }

    /**
     * 异步通知验签,返回订单信息或null
     */
    public static function verifyNotify(string $payType): ?array
    {
        switch ($payType) {
            case 'codepay':
            case 'epay':
                return self::epayNotify();
            case 'usdt':
                return null; // USDT走主动查询,不走notify
            case 'wxpay':
                return self::wxNotify();
            case 'alipay':
            case 'alipayf2f':
                return self::alipayNotify();
            default:
                return null;
        }
    }

    /* ===================== 码支付/易支付(易支付协议) ===================== */

    private static function epayConf(string $type): array
    {
        $prefix = $type === 'codepay' ? 'codepay_' : 'epay_';
        return [
            'gateway' => $type === 'codepay' ? trim((string)config($prefix . 'gateway', 'https://xpay.shw1.com/xpay/epay/submit.php')) : trim((string)config($prefix . 'gateway', '')),
            'pid' => (string)config($prefix . 'pid', ''),
            'key' => (string)config($prefix . 'key', ''),
        ];
    }

    public static function epaySign(array $params, string $key): string
    {
        unset($params['sign'], $params['sign_type']);
        ksort($params, SORT_STRING);
        $parts = [];
        foreach ($params as $k => $v) {
            if ($v === '' || $v === null) continue;
            $parts[] = $k . '=' . $v;
        }
        return md5(implode('&', $parts) . $key);
    }

    private static function epayPay(string $type, array $order): array
    {
        $conf = self::epayConf($type);
        if ($conf['gateway'] === '' || $conf['pid'] === '' || $conf['key'] === '') {
            throw new RuntimeException($type === 'codepay' ? '码支付未配置' : '易支付未配置');
        }
        $params = [
            'pid' => $conf['pid'],
            'type' => $type === 'codepay' ? (config('codepay_channel', 'alipay')) : config('epay_channel', 'alipay'),
            'out_trade_no' => $order['order_no'],
            'notify_url' => $order['notify_url'],
            'return_url' => $order['return_url'],
            'name' => mb_substr($order['title'], 0, 120),
            'money' => number_format((float)$order['money'], 2, '.', ''),
            'sitename' => mb_substr(config('site_name', '坤影CMS'), 0, 60),
        ];
        $params['sign'] = self::epaySign($params, $conf['key']);
        $params['sign_type'] = 'MD5';
        return ['type' => 'redirect', 'url' => $conf['gateway'] . '?' . http_build_query($params)];
    }

    private static function epayNotify(): ?array
    {
        $data = $_POST;
        if (empty($data['out_trade_no'])) return null;
        // 根据订单前缀无法区分渠道,同时尝试两套密钥
        foreach (['codepay', 'epay'] as $type) {
            $conf = self::epayConf($type);
            if ($conf['key'] === '') continue;
            if (hash_equals(self::epaySign($data, $conf['key']), (string)($data['sign'] ?? ''))) {
                if (($data['trade_status'] ?? '') === 'TRADE_SUCCESS') {
                    return [
                        'out_trade_no' => (string)$data['out_trade_no'],
                        'trade_no' => (string)($data['trade_no'] ?? ''),
                        'money' => (float)($data['money'] ?? 0),
                    ];
                }
                return null;
            }
        }
        return null;
    }

    /* ===================== USDT 免挂 TRC20 ===================== */

    private static function usdtPay(array $order): array
    {
        $addr = trim((string)config('usdt_address', ''));
        $rate = (float)config('usdt_rate', 0);
        if ($addr === '' || $rate <= 0) throw new RuntimeException('USDT收款未配置');
        // 金额换算 + 订单唯一尾数(分位递增,防止并发金额相同无法匹配)
        $base = round((float)$order['money'] * $rate, 2);
        $seq = (int)Db::fetchOne("SELECT id FROM ky_order WHERE order_no=?", [$order['order_no']]);
        $suffix = ($seq % 40) * 0.01;
        $usdt = round($base + $suffix, 2);
        Db::update('ky_order', ['usdt_amount' => $usdt], 'order_no=?', [$order['order_no']]);
        return [
            'type' => 'qrcode',
            'qr' => $addr,
            'tip' => '请向以下地址(TRC20)转入 ' . number_format($usdt, 2, '.', '') . ' USDT',
            'amount' => number_format($usdt, 2, '.', ''),
            'address' => $addr,
        ];
    }

    /**
     * 主动查询TRC20入账(供轮询接口/定时任务调用)
     * @return array|null 匹配到的 [txid]
     */
    public static function usdtCheck(array $order): ?string
    {
        $addr = trim((string)config('usdt_address', ''));
        $expected = (float)($order['usdt_amount'] ?? 0);
        if ($addr === '' || $expected <= 0) return null;
        $url = 'https://api.trongrid.io/v1/accounts/' . rawurlencode($addr) . '/transactions/trc20?'
            . http_build_query([
                'limit' => 100,
                'only_to' => true,
                'contract_address' => self::TRC20_CONTRACT,
                'min_timestamp' => ((int)$order['created'] - 300) * 1000,
            ]);
        $headers = [];
        $apiKey = trim((string)config('usdt_trongrid_key', ''));
        if ($apiKey !== '') $headers[] = 'TRON-PRO-API-KEY: ' . $apiKey;
        $body = Http::request($url, 'GET', null, 15, $headers);
        $data = $body ? json_decode($body, true) : null;
        if (!is_array($data) || empty($data['data'])) return null;
        foreach ($data['data'] as $tx) {
            if (($tx['to'] ?? '') !== $addr) continue;
            if (($tx['token_info']['address'] ?? '') !== self::TRC20_CONTRACT) continue;
            $amount = ((float)$tx['value']) / 1000000;
            if (abs($amount - $expected) < 0.001) {
                // 确认该txid未被其他订单占用
                $used = Db::fetchOne("SELECT COUNT(*) FROM ky_order WHERE trade_no=? AND order_no<>?", [(string)$tx['transaction_id'], $order['order_no']]);
                if ((int)$used === 0) {
                    return (string)$tx['transaction_id'];
                }
            }
        }
        return null;
    }

    /* ===================== 微信官方 Native(v2) ===================== */

    private static function wxSign(array $params, string $key): string
    {
        unset($params['sign']);
        ksort($params, SORT_STRING);
        $parts = [];
        foreach ($params as $k => $v) {
            if ($v === '' || is_array($v)) continue;
            $parts[] = $k . '=' . $v;
        }
        return strtoupper(md5(implode('&', $parts) . '&key=' . $key));
    }

    private static function xmlToArray(string $xml): ?array
    {
        if (!preg_match('/<[a-z]+>/i', $xml)) return null;
        $old = libxml_disable_entity_loader(true);
        $obj = @simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        libxml_disable_entity_loader($old);
        if ($obj === false) return null;
        $arr = [];
        foreach ((array)$obj as $k => $v) {
            $arr[$k] = is_array($v) ? '' : (string)$v;
        }
        return $arr;
    }

    private static function arrayToXml(array $data): string
    {
        $xml = '<xml>';
        foreach ($data as $k => $v) {
            $xml .= is_numeric($v) ? "<{$k}>{$v}</{$k}>" : "<{$k}><![CDATA[{$v}]]></{$k}>";
        }
        return $xml . '</xml>';
    }

    private static function wxPay(array $order): array
    {
        $appid = trim((string)config('wxpay_appid', ''));
        $mchid = trim((string)config('wxpay_mchid', ''));
        $key = trim((string)config('wxpay_key', ''));
        if ($appid === '' || $mchid === '' || $key === '') throw new RuntimeException('微信支付未配置');
        $params = [
            'appid' => $appid,
            'mch_id' => $mchid,
            'nonce_str' => rand_str(32),
            'body' => mb_substr($order['title'], 0, 120),
            'out_trade_no' => $order['order_no'],
            'total_fee' => (int)round(((float)$order['money']) * 100),
            'spbill_create_ip' => client_ip(),
            'notify_url' => $order['notify_url'],
            'trade_type' => 'NATIVE',
        ];
        $params['sign'] = self::wxSign($params, $key);
        $res = Http::post(self::WX_GATEWAY, self::arrayToXml($params), 15, ['Content-Type: text/xml']);
        $data = $res ? self::xmlToArray($res) : null;
        if (!$data || ($data['return_code'] ?? '') !== 'SUCCESS' || ($data['result_code'] ?? '') !== 'SUCCESS') {
            $msg = $data['err_code_des'] ?? $data['return_msg'] ?? '微信下单失败';
            throw new RuntimeException('微信下单失败:' . (string)$msg);
        }
        return ['type' => 'qrcode', 'qr' => (string)$data['code_url'], 'tip' => '请使用微信扫码支付'];
    }

    private static function wxNotify(): ?array
    {
        $xml = file_get_contents('php://input');
        $data = $xml ? self::xmlToArray($xml) : null;
        if (!$data) return null;
        $key = trim((string)config('wxpay_key', ''));
        if ($key === '') return null;
        if (!hash_equals(self::wxSign($data, $key), (string)($data['sign'] ?? ''))) return null;
        if (($data['return_code'] ?? '') !== 'SUCCESS' || ($data['result_code'] ?? '') !== 'SUCCESS') return null;
        return [
            'out_trade_no' => (string)$data['out_trade_no'],
            'trade_no' => (string)($data['transaction_id'] ?? ''),
            'money' => ((int)($data['total_fee'] ?? 0)) / 100,
        ];
    }

    /* ===================== 支付宝(RSA2:官方网页支付/当面付) ===================== */

    private static function aliConf(): array
    {
        return [
            'appid' => trim((string)config('alipay_appid', '')),
            'private_key' => trim((string)config('alipay_private_key', '')),
            'public_key' => trim((string)config('alipay_public_key', '')),
        ];
    }

    private static function aliFormatKey(string $key, bool $isPrivate): string
    {
        $key = str_replace(['-----BEGIN RSA PRIVATE KEY-----', '-----END RSA PRIVATE KEY-----', '-----BEGIN PRIVATE KEY-----', '-----END PRIVATE KEY-----', '-----BEGIN PUBLIC KEY-----', '-----END PUBLIC KEY-----', "\r"], '', $key);
        $key = str_replace("\n", '', trim($key));
        $body = chunk_split($key, 64, "\n");
        if ($isPrivate) {
            return "-----BEGIN RSA PRIVATE KEY-----\n" . $body . "-----END RSA PRIVATE KEY-----\n";
        }
        return "-----BEGIN PUBLIC KEY-----\n" . $body . "-----END PUBLIC KEY-----\n";
    }

    private static function aliSignBody(array $params, string $privateKey): ?string
    {
        unset($params['sign'], $params['sign_type']);
        ksort($params, SORT_STRING);
        $parts = [];
        foreach ($params as $k => $v) {
            if ($v === '' || $v === null) continue;
            $parts[] = $k . '=' . $v;
        }
        $str = implode('&', $parts);
        $res = openssl_sign($str, $signature, self::aliFormatKey($privateKey, true), OPENSSL_ALGO_SHA256);
        return $res ? base64_encode($signature) : null;
    }

    private static function aliVerifySign(array $params): bool
    {
        $conf = self::aliConf();
        if ($conf['public_key'] === '') return false;
        $sign = (string)($params['sign'] ?? '');
        if ($sign === '') return false;
        unset($params['sign'], $params['sign_type']);
        ksort($params, SORT_STRING);
        $parts = [];
        foreach ($params as $k => $v) {
            if ($v === '' || $v === null) continue;
            $parts[] = $k . '=' . $v;
        }
        $ok = openssl_verify(implode('&', $parts), base64_decode($sign), self::aliFormatKey($conf['public_key'], false), OPENSSL_ALGO_SHA256);
        return $ok === 1;
    }

    private static function aliCommonParams(string $method, array $order): array
    {
        return [
            'app_id' => self::aliConf()['appid'],
            'method' => $method,
            'format' => 'JSON',
            'charset' => 'utf-8',
            'sign_type' => 'RSA2',
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '1.0',
            'notify_url' => $order['notify_url'],
        ];
    }

    /**
     * 支付宝官方 电脑网站/手机网站支付
     */
    private static function alipayPage(array $order): array
    {
        $conf = self::aliConf();
        if ($conf['appid'] === '' || $conf['private_key'] === '') throw new RuntimeException('支付宝未配置');
        $mobile = preg_match('/mobile|android|iphone|ipad/i', $_SERVER['HTTP_USER_AGENT'] ?? '') ? true : false;
        $method = $mobile ? 'alipay.trade.wap.pay' : 'alipay.trade.page.pay';
        $params = self::aliCommonParams($method, $order);
        $params['return_url'] = $order['return_url'];
        $params['biz_content'] = json_encode([
            'out_trade_no' => $order['order_no'],
            'total_amount' => number_format((float)$order['money'], 2, '.', ''),
            'subject' => mb_substr($order['title'], 0, 120),
            'product_code' => $mobile ? 'FAST_INSTANT_TRADE_PAY' : 'QUICK_WAP_WAY',
        ], JSON_UNESCAPED_UNICODE);
        $sign = self::aliSignBody($params, $conf['private_key']);
        if ($sign === null) throw new RuntimeException('支付宝私钥无效');
        $params['sign'] = $sign;
        return ['type' => 'redirect', 'url' => 'https://openapi.alipay.com/gateway.php?' . http_build_query($params)];
    }

    /**
     * 支付宝当面付(扫码)
     */
    private static function alipayF2f(array $order): array
    {
        $conf = self::aliConf();
        if ($conf['appid'] === '' || $conf['private_key'] === '') throw new RuntimeException('支付宝未配置');
        $params = self::aliCommonParams('alipay.trade.precreate', $order);
        $params['biz_content'] = json_encode([
            'out_trade_no' => $order['order_no'],
            'total_amount' => number_format((float)$order['money'], 2, '.', ''),
            'subject' => mb_substr($order['title'], 0, 120),
        ], JSON_UNESCAPED_UNICODE);
        $sign = self::aliSignBody($params, $conf['private_key']);
        if ($sign === null) throw new RuntimeException('支付宝私钥无效');
        $params['sign'] = $sign;
        $res = Http::post('https://openapi.alipay.com/gateway.php', http_build_query($params), 15, ['Content-Type: application/x-www-form-urlencoded']);
        $data = $res ? json_decode($res, true) : null;
        $resp = $data['alipay_trade_precreate_response'] ?? null;
        if (!is_array($resp) || ($resp['code'] ?? '') !== '10000') {
            $msg = $resp['sub_msg'] ?? '当面付下单失败';
            throw new RuntimeException('支付宝下单失败:' . (string)$msg);
        }
        return ['type' => 'qrcode', 'qr' => (string)$resp['qr_code'], 'tip' => '请使用支付宝扫码支付'];
    }

    private static function alipayNotify(): ?array
    {
        $data = $_POST;
        if (empty($data['out_trade_no'])) return null;
        if (!self::aliVerifySign($data)) return null;
        $status = (string)($data['trade_status'] ?? '');
        if ($status !== 'TRADE_SUCCESS' && $status !== 'TRADE_FINISHED') return null;
        // 校验金额与APPID防串单
        $order = Db::fetch("SELECT * FROM ky_order WHERE order_no=?", [(string)$data['out_trade_no']]);
        if ($order && abs((float)$order['amount'] - (float)($data['total_amount'] ?? 0)) > 0.001) return null;
        if (isset($data['app_id']) && $data['app_id'] !== config('alipay_appid')) return null;
        return [
            'out_trade_no' => (string)$data['out_trade_no'],
            'trade_no' => (string)($data['trade_no'] ?? ''),
            'money' => (float)($data['total_amount'] ?? 0),
        ];
    }
}
