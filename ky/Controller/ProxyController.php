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
        @set_time_limit(300);
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

        $isList = preg_match('/\.m3u8(\?|#|$)/i', $u);
        $body = Http::getFollow($u, 20, ['Referer:']);
        if ($body === false || $body === '') {
            http_response_code(502);
            exit('upstream failed');
        }

        if ($isList) {
            header('Content-Type: application/vnd.apple.mpegurl');
            echo $this->rewritePlaylist($body, $u);
        } else {
            header('Content-Type: video/mp2t');
            header('Accept-Ranges: none');
            header('Cache-Control: public, max-age=3600');
            echo $body;
        }
        exit;
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
        if (str_starts_with($link, '//')) return ($p['scheme'] ?? 'https') . ':' . $link;
        if (str_starts_with($link, '/')) return $origin . $link;
        // 相对路径:基于目录
        $dir = isset($p['path']) ? preg_replace('#/[^/]*$#', '/', $p['path']) : '/';
        return $origin . $dir . $link;
    }

    private function proxyLink(string $url): string
    {
        return m3u8_proxy_url($url);
    }
}
