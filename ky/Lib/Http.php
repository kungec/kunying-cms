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
                        $loc = (strpos($loc, '/') === 0) ? $origin . $loc : $origin . rtrim($path, '/') . '/' . ltrim($loc, '/');
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
}
