<?php
/**
 * 前台 - m3u8同源反向代理
 * /index.php?s=/proxy/m3u8&u=<rawurlencode(原始地址)>&sg=<日级签名>
 * 作用:绕过资源站防盗链(Referer/Origin)与跨域限制;播放列表内的相对地址自动改写回代理
 */
class ProxyController
{
    public function m3u8()
    {
        // 立即释放session文件锁:播放器并行拉取分片时不能被session串行化(卡顿主因)
        if (function_exists('session_write_close')) @session_write_close();
        @set_time_limit(300);
        while (ob_get_level() > 0) @ob_end_clean();
        @ini_set('zlib.output_compression', '0');

        $u = trim(Request::get('u', ''));
        $sg = trim(Request::get('sg', ''));
        if ($u === '' || $sg === '' || !preg_match('#^https?://#i', $u)) {
            http_response_code(403);
            exit('forbidden');
        }
        if (!hash_equals(md5($u . '|' . date('Ymd') . '|' . app_key()), $sg)
            && !hash_equals(md5($u . '|' . date('Ymd', time() - 86400) . '|' . app_key()), $sg)) {
            http_response_code(403);
            exit('signature expired');
        }
        // SSRF防护:目标必须是公网地址
        if (!Http::isPublicHttpUrl($u)) {
            http_response_code(403);
            exit('host not allowed');
        }

        if (preg_match('/\.m3u8(\?|#|$)/i', $u)) {
            $this->playlist($u);
            exit;
        }
        $this->segment($u);
        exit;
    }

    /** 播放列表:VOD磁盘缓存1小时,直播(无ENDLIST)不缓存 */
    private function playlist(string $u): void
    {
        header('Content-Type: application/vnd.apple.mpegurl');
        header('Access-Control-Allow-Origin: *');
        $cacheDir = KY_PATH . '/data/cache/m3u8';
        $cache = $cacheDir . '/' . md5($u) . '.m3u8';
        if (is_file($cache) && filesize($cache) > 0 && time() - filemtime($cache) < 3600) {
            $body = file_get_contents($cache);
            if ($body !== false && $body !== '') {
                header('X-Cache: HIT');
                header('Content-Length: ' . strlen($body));
                echo $body;
                return;
            }
        }
        $body = Http::getFollow($u, 20, ['Referer:']);
        if ($body === false || $body === '') {
            http_response_code(502);
            exit('upstream failed');
        }
        $rewritten = $this->rewritePlaylist($body, $u);
        if (strpos($body, '#EXT-X-ENDLIST') !== false) {
            if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
            @file_put_contents($cache, $rewritten, LOCK_EX);
        }
        header('X-Cache: MISS');
        header('Content-Length: ' . strlen($rewritten));
        echo $rewritten;
    }

    /** 视频分片:真流式透传(边收边发,客户端断开即中止上游) */
    private function segment(string $u): void
    {
        header('Content-Type: video/mp2t');
        header('Accept-Ranges: none');
        header('Cache-Control: public, max-age=86400');
        header('Access-Control-Allow-Origin: *');

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => str_replace(' ', '%20', $u),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => config('http_verify_ssl', '1') == '1',
            CURLOPT_USERAGENT => 'Mozilla/5.0 (KunYing Proxy)',
            CURLOPT_REFERER => '',
            CURLOPT_BUFFERSIZE => 262144,
        ]);
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $chunk) {
            static $sent = 0;
            echo $chunk;
            $sent += strlen($chunk);
            if ($sent >= 262144) { $sent = 0; flush(); }
            if (connection_aborted()) return -1; // 观众已离开/seek:中止上游下载
            return strlen($chunk);
        });
        curl_exec($ch);
        curl_close($ch);
    }

    /**
     * 远程图片代理+磁盘缓存
     * /index.php?s=/proxy/img&u=<rawurlencode>&sg=<签名>
     */
    public function img()
    {
        $u = trim(Request::get('u', ''));
        $sg = trim(Request::get('sg', ''));
        if ($u === '' || $sg === '' || !preg_match('#^https?://#i', $u)) {
            http_response_code(403);
            exit('forbidden');
        }
        if (!hash_equals(md5($u . '|' . date('Ymd') . '|' . app_key()), $sg)
            && !hash_equals(md5($u . '|' . date('Ymd', time() - 86400) . '|' . app_key()), $sg)) {
            http_response_code(403);
            exit('signature expired');
        }
        // SSRF防护:目标必须是公网地址
        if (!Http::isPublicHttpUrl($u)) {
            http_response_code(403);
            exit('host not allowed');
        }
        if (!is_dir(KY_PATH . '/data/cache/img')) {
            @mkdir(KY_PATH . '/data/cache/img', 0755, true);
        }
        $cache = KY_PATH . '/data/cache/img/' . md5($u) . '.img';
        if (!is_file($cache) || filesize($cache) === 0) {
            $body = Http::get($u, 12);
            if ($body === false || strlen($body) < 64 || @getimagesizefromstring($body) === false) {
                http_response_code(404);
                exit('image unavailable');
            }
            file_put_contents($cache, $body, LOCK_EX);
        }
        $info = @getimagesize($cache);
        $types = [IMAGETYPE_JPEG => 'image/jpeg', IMAGETYPE_PNG => 'image/png', IMAGETYPE_GIF => 'image/gif', IMAGETYPE_WEBP => 'image/webp'];
        header('Content-Type: ' . ($types[$info[2] ?? 0] ?? 'image/jpeg'));
        header('Cache-Control: public, max-age=86400');
        header('Content-Length: ' . filesize($cache));
        readfile($cache);
        exit;
    }

    /**
     * 改写播放列表:非注释行与URI="..."属性全部指向本站代理
     */
    private function rewritePlaylist(string $body, string $baseUrl): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $body) ?: [];
        $out = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') { $out[] = $line; continue; }
            if ($line[0] === '#') {
                // 改写标签内的 URI="..." 属性
                $out[] = preg_replace_callback('/URI="([^"]+)"/i', function ($m) use ($baseUrl) {
                    return 'URI="' . $this->proxyLink($this->absolutize($m[1], $baseUrl)) . '"';
                }, $line);
                continue;
            }
            $out[] = $this->proxyLink($this->absolutize($line, $baseUrl));
        }
        return implode("\n", $out) . "\n";
    }

    private function absolutize(string $link, string $baseUrl): string
    {
        if (preg_match('#^https?://#i', $link)) return $link;
        $p = parse_url($baseUrl);
        $origin = ($p['scheme'] ?? 'https') . '://' . ($p['host'] ?? '') . (isset($p['port']) ? ':' . $p['port'] : '');
        if ((strpos($link, '//') === 0)) return ($p['scheme'] ?? 'https') . ':' . $link;
        if ((strpos($link, '/') === 0)) return $origin . $link;
        // 相对路径:基于目录
        $dir = isset($p['path']) ? preg_replace('#/[^/]*$#', '/', $p['path']) : '/';
        return $origin . $dir . $link;
    }

    private function proxyLink(string $url): string
    {
        return m3u8_proxy_url($url);
    }
}
