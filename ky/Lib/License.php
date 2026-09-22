<?php
/**
 * 授权/市场客户端:与授权控制端(ysapi)通信
 * - 应用市场(插件/模板)列表与安装
 * - 授权校验(带72小时离线宽限)
 * - 系统公告拉取
 * - 自动更新检查
 */
if (!defined('KY_PATH')) exit('Access denied');

class License
{
    /** 内置授权连接(密文随程序分发,后台不可见不可改) */
    private static ?array $conn = null;

    private static function conn(): array
    {
        if (self::$conn === null) {
            $k = 'KyLic#2026x9';
            $dec = function (string $v) use ($k): string {
                $raw = base64_decode($v, true);
                if ($raw === false) return '';
                $out = '';
                for ($i = 0; $i < strlen($raw); $i++) $out .= $raw[$i] ^ $k[$i % strlen($k)];
                return $out;
            };
            self::$conn = [
                'base' => rtrim($dec('Iw04GRAZHR9TRhEXMgwmAAINSklI'), '/'),
                'token' => $dec('ACAfRFMbB3ZwAE0KZj19XyEWdAZ2'),
            ];
        }
        return self::$conn;
    }

    public static function apiBase(): string
    {
        return self::conn()['base'];
    }

    public static function token(): string
    {
        return self::conn()['token'];
    }

    /**
     * 带签名请求控制端
     */
    public static function api(string $path, array $params = [], int $timeout = 15): ?array
    {
        $base = self::apiBase();
        if ($base === '') return null;
        $params['domain'] = $_SERVER['HTTP_HOST'] ?? '';
        $params['token'] = self::token();
        $params['time'] = (string)time();
        $params['site_ver'] = KY_VERSION;
        $params['sign'] = self::makeSign($params);
        $data = Http::getJson($base . $path . '?' . http_build_query($params), $timeout);
        return is_array($data) ? $data : null;
    }

    public static function makeSign(array $params): string
    {
        unset($params['sign']);
        ksort($params, SORT_STRING);
        $parts = [];
        foreach ($params as $k => $v) {
            if ($v === '') continue;
            $parts[] = $k . '=' . $v;
        }
        return md5(implode('&', $parts) . '|' . self::token() . '|kunying');
    }

    /* ===================== 应用市场 ===================== */

    public static function market(): array
    {
        $res = self::api('/api/market/list', [], 12);
        if (!$res || ($res['code'] ?? 0) != 1) return [];
        return is_array($res['data'] ?? null) ? $res['data'] : [];
    }

    /**
     * 获取产品下载地址(需已授权)
     */
    public static function downloadUrl(string $productCode): ?string
    {
        $res = self::api('/api/market/download', ['code' => $productCode], 15);
        if (!$res || ($res['code'] ?? 0) != 1) return null;
        return (string)($res['data']['url'] ?? '');
    }

    /* ===================== 授权校验 ===================== */

    /**
     * 校验某产品授权,结果缓存1小时,离线宽限72小时
     */
    public static function check(string $productCode): bool
    {
        $cacheKey = 'license_' . $productCode;
        $cached = cache_get($cacheKey);
        if (is_array($cached)) return (bool)$cached['ok'];

        // 以控制端为准(未安装的产品也可查询,用于在线安装前的授权校验)
        $res = self::api('/api/license/verify', ['code' => $productCode], 10);
        if (is_array($res)) {
            $ok = ($res['code'] ?? 0) == 1;
            cache_set($cacheKey, ['ok' => $ok], 3600);
            if ($ok) config_set('api_last_ok', (string)time());
            return $ok;
        }

        // 控制端不可达:本地已安装且有授权记录时给72小时宽限
        $local = Db::fetch("SELECT * FROM ky_plugin WHERE code=?", [$productCode]);
        $last = (int)config('api_last_ok', 0);
        if ($local && $local['status'] == 1 && $last > 0 && time() - $last < 86400 * 3) {
            return true;
        }
        cache_set($cacheKey, ['ok' => false], 600);
        return false;
    }

    /* ===================== 公告 ===================== */

    public static function notices(): array
    {
        $cached = cache_get('api_notices');
        if (is_array($cached)) return $cached;
        $res = self::api('/api/notice/list', [], 8);
        $list = [];
        if (is_array($res) && ($res['code'] ?? 0) == 1 && is_array($res['data'] ?? null)) {
            foreach ($res['data'] as $n) {
                $list[] = [
                    'title' => (string)($n['title'] ?? ''),
                    'content' => (string)($n['content'] ?? ''),
                    'level' => (int)($n['level'] ?? 0),
                    'time' => (int)($n['time'] ?? 0),
                ];
            }
            cache_set('api_notices', $list, 3600);
        }
        return $list;
    }

    /* ===================== 在线授权绑定 ===================== */

    /**
     * 用授权码绑定产品(后台手动输入授权码时)
     */
    public static function bind(string $productCode, string $authCode): array
    {
        $res = self::api('/api/license/bind', ['code' => $productCode, 'auth' => $authCode], 15);
        if (!$res) return [false, '授权服务器连接失败'];
        if (($res['code'] ?? 0) != 1) return [false, (string)($res['msg'] ?? '授权失败')];
        $expire = (int)($res['data']['expire'] ?? 0);
        Db::query("INSERT INTO ky_plugin (code,expire,status) VALUES (?,?,1) ON DUPLICATE KEY UPDATE expire=VALUES(expire),status=1", [$productCode, $expire]);
        cache_del('license_' . $productCode);
        return [true, $expire > 0 ? '授权成功,到期时间:' . date('Y-m-d', $expire) : '永久授权成功'];
    }
}
