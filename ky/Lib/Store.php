<?php
/**
 * 采集图片存储抽象层
 * 驱动:local(本站自选目录) / ftp / oss(阿里云) / s3(S3兼容:MinIO/B2/COS等)
 * 采集封面统一经 Store::put 上传,成功返回公网URL,失败由调用方回退
 */
if (!defined('KY_PATH')) exit('Access denied');

class Store
{
    /**
     * 当前存储驱动:云盘驱动必须配置完整才启用,否则一律回落本地
     * (默认本地;上传云盘需在后台明确配置并通过「测试存储」验证)
     */
    public static function driver(): string
    {
        $d = (string)config('img_store', 'local');
        if (!in_array($d, ['local', 'ftp', 'oss', 's3'], true)) return 'local';
        if ($d === 'ftp') {
            if ((string)config('ftp_host', '') === '' || (string)config('ftp_user', '') === ''
                || (string)config('ftp_pass', '') === '' || (string)config('ftp_baseurl', '') === '') return 'local';
        }
        if ($d === 'oss') {
            if ((string)config('oss_endpoint', '') === '' || (string)config('oss_bucket', '') === ''
                || (string)config('oss_ak', '') === '' || (string)config('oss_sk', '') === '') return 'local';
        }
        if ($d === 's3') {
            if ((string)config('s3_endpoint', '') === '' || (string)config('s3_bucket', '') === ''
                || (string)config('s3_ak', '') === '' || (string)config('s3_sk', '') === '') return 'local';
        }
        return $d;
    }

    /** 本地驱动:站点内目录(相对站根,默认 /upload/vod) */
    public static function localWebPath(): string
    {
        $d = '/' . trim((string)config('img_dir', '/upload/vod'), '/');
        if (!preg_match('#^/upload(/[a-z0-9_\-]{1,40}){0,4}$#i', $d)) $d = '/upload/vod';
        return rtrim($d, '/');
    }

    private static function localAbs(): string
    {
        return KY_PATH . self::localWebPath();
    }

    /** 远程驱动的外链前缀(不含末尾/) */
    private static function baseUrl(string $driver): string
    {
        $base = '';
        if ($driver === 'ftp') $base = (string)config('ftp_baseurl', '');
        elseif ($driver === 'oss') $base = (string)config('oss_baseurl', '');
        elseif ($driver === 's3') $base = (string)config('s3_baseurl', '');
        if ($base !== '') return rtrim($base, '/');
        // 未填外链域名时用驱动默认公开地址
        if ($driver === 'oss') {
            $ep = rtrim((string)config('oss_endpoint', ''), '/');
            $bucket = trim((string)config('oss_bucket', ''));
            if ($ep !== '' && $bucket !== '') return 'https://' . $bucket . '.' . preg_replace('#^https?://#', '', $ep);
        }
        if ($driver === 's3') {
            $ep = rtrim((string)config('s3_endpoint', ''), '/');
            $bucket = trim((string)config('s3_bucket', ''));
            if ($ep !== '' && $bucket !== '') return rtrim($ep, '/') . '/' . $bucket;
        }
        return '';
    }

    /** 上传内容,返回公网URL;失败返回null */
    public static function put(string $key, string $body): ?string
    {
        $key = ltrim(str_replace('\\', '/', $key), '/');
        $driver = self::driver();
        try {
            switch ($driver) {
                case 'ftp':
                    return self::putFtp($key, $body);
                case 'oss':
                    return self::putSigned($key, $body, 'oss');
                case 's3':
                    return self::putSigned($key, $body, 's3');
                default:
                    return self::putLocal($key, $body);
            }
        } catch (\Throwable $t) {
            error_log('[KunYing Store] ' . $driver . ' 上传失败: ' . $t->getMessage());
            return null;
        }
    }

    private static function putLocal(string $key, string $body): ?string
    {
        $dir = self::localAbs();
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        if (!is_dir($dir)) return null;
        $file = $dir . '/' . $key;
        if (file_put_contents($file, $body, LOCK_EX) === false) return null;
        return self::localWebPath() . '/' . $key;
    }

    private static function putFtp(string $key, string $body): ?string
    {
        $host = (string)config('ftp_host', '');
        $user = (string)config('ftp_user', '');
        $pass = (string)config('ftp_pass', '');
        $port = max(1, (int)config('ftp_port', '21')) ?: 21;
        $path = '/' . trim((string)config('ftp_path', ''), '/');
        $base = self::baseUrl('ftp');
        if ($host === '' || $user === '' || $base === '') return null;
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'ftp://' . $host . ':' . $port . $path . '/' . $key,
            CURLOPT_USERPWD => $user . ':' . $pass,
            CURLOPT_CUSTOMREQUEST => 'STOR',
            CURLOPT_UPLOAD => true,
            CURLOPT_INFILE => self::memStream($body),
            CURLOPT_INFILESIZE => strlen($body),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $ok = curl_exec($ch) !== false && curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 0;
        $err = curl_error($ch);
        curl_close($ch);
        return $ok ? $base . '/' . $key : null;
    }

    /** OSS / S3(v2签名,HMAC-SHA1)PUT */
    private static function putSigned(string $key, string $body, string $kind)
    {
        if ($kind === 'oss') {
            $ep = rtrim((string)config('oss_endpoint', ''), '/');
            $bucket = trim((string)config('oss_bucket', ''));
            $ak = (string)config('oss_ak', '');
            $sk = (string)config('oss_sk', '');
            $prefix = 'OSS';
        } else {
            $ep = rtrim((string)config('s3_endpoint', ''), '/');
            $bucket = trim((string)config('s3_bucket', ''));
            $ak = (string)config('s3_ak', '');
            $sk = (string)config('s3_sk', '');
            $prefix = 'AWS';
        }
        if ($ep === '' || $bucket === '' || $ak === '' || $sk === '') return null;
        $resource = '/' . $bucket . '/' . $key;
        $url = ($kind === 'oss'
                ? 'https://' . $bucket . '.' . preg_replace('#^https?://#', '', rtrim((string)config('oss_endpoint', ''), '/'))
                : rtrim((string)config('s3_endpoint', ''), '/') . '/' . $bucket)
            . '/' . $key;
        $contentType = 'application/octet-stream';
        $date = gmdate('D, d M Y H:i:s T');
        $md5 = base64_encode(md5($body, true));
        $sig = base64_encode(hash_hmac('sha1', "PUT\n{$md5}\n{$contentType}\n{$date}\n{$resource}", $sk, true));
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Content-Type: ' . $contentType,
                'Content-MD5: ' . $md5,
                'Date: ' . $date,
                'Authorization: ' . $prefix . ' ' . $ak . ':' . $sig,
            ],
            CURLOPT_INFILE => self::memStream($body),
            CURLOPT_INFILESIZE => strlen($body),
            CURLOPT_UPLOAD => true,
        ]);
        $res = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($res === false || ($code !== 200 && $code !== 201)) {
            error_log('[KunYing Store] ' . $kind . ' 上传失败 HTTP' . $code . ' ' . $err);
            return null;
        }
        $base = self::baseUrl($kind);
        return $base !== '' ? $base . '/' . $key : null;
    }

    private static function memStream(string $body)
    {
        $fp = fopen('php://temp', 'r+');
        fwrite($fp, $body);
        rewind($fp);
        return $fp;
    }

    /** 存储连通性测试:按后台所选驱动严格校验(配置不完整直接报错,不误测本地) */
    public static function test(): array
    {
        $selected = (string)config('img_store', 'local');
        if (in_array($selected, ['ftp', 'oss', 's3'], true) && self::driver() === 'local') {
            return [false, '云盘配置不完整(地址/Bucket/密钥/外链域名有缺失),当前仍按本地存储'];
        }
        $key = 'store-test-' . date('Ymd-His') . '.txt';
        $url = self::put($key, 'kunying-store-test-' . time());
        if ($url === null) return [false, '存储上传失败,请检查配置(可用性/账号/路径/外链域名)'];
        // 本地驱动:探针文件用完即删,不在图片目录残留
        if (self::driver() === 'local') @unlink(KY_PATH . self::localWebPath() . '/' . $key);
        return [true, '上传成功', $url];
    }
}
