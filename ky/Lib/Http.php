<?php
/**
 * HTTP请求封装(cURL)
 */
if (!defined('KY_PATH')) exit('Access denied');

class Http
{
    public static function get(string $url, int $timeout = 15, array $headers = [])
    {
        return self::request($url, 'GET', null, $timeout, $headers);
    }

    public static function post(string $url, $data = null, int $timeout = 15, array $headers = [])
    {
        return self::request($url, 'POST', $data, $timeout, $headers);
    }

    public static function request(string $url, string $method = 'GET', $data = null, int $timeout = 15, array $headers = [])
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) return false;
        // 仅允许 http/https
        $scheme = strtolower(parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) return false;
        if (!function_exists('curl_init')) return false;

        $ch = curl_init();
        // SSL校验可通过配置关闭(仅用于无有效证书的内部环境,生产建议开启)
        $verify = function_exists('config') ? config('http_verify_ssl', '1') === '1' : true;
        $opts = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => $verify,
            CURLOPT_SSL_VERIFYHOST => $verify ? 2 : 0,
            CURLOPT_USERAGENT => 'KunYingCMS/' . KY_VERSION,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        ];
        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            if (is_array($data)) {
                $opts[CURLOPT_POSTFIELDS] = http_build_query($data);
            } else {
                $opts[CURLOPT_POSTFIELDS] = (string)$data;
            }
        }
        if (!empty($headers)) {
            $opts[CURLOPT_HTTPHEADER] = $headers;
        }
        curl_setopt_array($ch, $opts);
        // 响应大小上限20MB:超大远程响应读入内存会拖垮FPM
        $len = 0;
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $chunk) use (&$len) {
            $len += strlen($chunk);
            if ($len > 20 * 1048576) return -1;
            return strlen($chunk);
        });
        $body = curl_exec($ch);
        $err = curl_errno($ch);
        curl_close($ch);
        if ($err) return false;
        return $body;
    }

    public static function getJson(string $url, int $timeout = 15)
    {
        $body = self::get($url, $timeout);
        if ($body === false) return null;
        $data = json_decode($body, true);
        return is_array($data) ? $data : null;
    }

    /**
     * 下载文件到本地路径
     */
    public static function download(string $url, string $savePath, int $timeout = 120): bool
    {
        $body = self::request($url, 'GET', null, $timeout);
        if ($body === false || $body === '') return false;
        return file_put_contents($savePath, $body, LOCK_EX) !== false;
    }
    /**
     * GET并受控跟随跳转(每一跳重新校验公网地址,防SSRF)
     */
    public static function getFollow(string $url, int $timeout = 15, array $headers = [], int $maxRedirects = 3)
    {
        $verify = function_exists('config') ? config('http_verify_ssl', '1') === '1' : true;
        for ($i = 0; $i <= $maxRedirects; $i++) {
            if (!self::isPublicHttpUrl($url)) return false;
            // DNS pin:连接钉在已校验的公网IP上,防重绑定TOCTOU(每一跳重新解析校验)
            $pin = self::resolvePin($url);
            if ($pin === null) return false;
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_HEADER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => $verify,
                CURLOPT_SSL_VERIFYHOST => $verify ? 2 : 0,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; KunYingCMS/' . KY_VERSION . ')',
                CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            ]);
            if ($pin) curl_setopt($ch, CURLOPT_RESOLVE, $pin);
            if (!empty($headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $res = curl_exec($ch);
            if ($res === false) { curl_close($ch); return false; }
            $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $hlen = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            curl_close($ch);
            if ($code >= 300 && $code < 400) {
                $hdr = substr((string)$res, 0, $hlen);
                if (preg_match('/^Location:\s*(\S+)/mi', $hdr, $m)) {
                    $loc = trim($m[1]);
                    $pp = parse_url($url);
                    $origin = ($pp['scheme'] ?? 'https') . '://' . ($pp['host'] ?? '') . (isset($pp['port']) ? ':' . $pp['port'] : '');
                    if (stripos($loc, 'http') !== 0) {
                        $path = $pp['path'] ?? '/';
                        // 相对地址按RFC 3986解析到"所在目录"(去文件名),而非拼到原路径尾部
                        $dir = rtrim(substr($path, 0, (int)strrpos($path, '/') + 1), '/');
                        $loc = (strpos($loc, '/') === 0) ? $origin . $loc : $origin . $dir . '/' . ltrim($loc, '/');
                    }
                    $url = $loc;
                    continue;
                }
                return false;
            }
            if ($code >= 200 && $code < 300) {
                return substr((string)$res, $hlen);
            }
            return false;
        }
        return false;
    }

    /**
     * SSRF防护:仅允许解析到公网地址的http(s)链接
     */
    public static function isPublicHttpUrl(string $url): bool
    {
        $p = parse_url($url);
        if (!$p || !in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true)) return false;
        $host = strtolower($p['host'] ?? '');
        if ($host === '') return false;
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }
        if ($host === 'localhost') return false;
        $ip = gethostbyname($host);
        if ($ip === $host) return false;
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    /**
     * DNS固定解析pin:主机解析→公网校验→CURLOPT_RESOLVE把连接钉在已校验IP上,
     * 关闭"校验时公网、连接时被DNS重绑定到内网"的TOCTOU窗口。
     * 返回CURLOPT_RESOLVE数组;主机本身就是IP字面量无需pin(调用前已校验);解析失败返回null。
     */
    public static function resolvePin(string $url): ?array
    {
        $p = parse_url($url);
        $host = strtolower((string)($p['host'] ?? ''));
        if ($host === '') return null;
        if (filter_var($host, FILTER_VALIDATE_IP)) return []; // IP直连,无需pin
        $port = (int)($p['port'] ?? (strtolower($p['scheme'] ?? '') === 'https' ? 443 : 80));
        $ip = gethostbyname($host);
        if ($ip === $host || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null; // 解析失败或解析到内网/保留段
        }
        return ["{$host}:{$port}:{$ip}"];
    }
}
