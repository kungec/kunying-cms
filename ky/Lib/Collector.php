<?php
/**
 * 采集器:兼容苹果CMS v10 标准JSON资源接口
 * 内置极速资源采集源
 */
if (!defined('KY_PATH')) exit('Access denied');

class Collector
{
    /** 页内待下载封面队列:url => [rowId...] — collectPage尾部统一并发处理 */
    private static $pendingPics = [];

    /**
     * 采集速度档位 => 请求间隔(微秒):温和/慢速/标准/极速
     */
    public static function speedDelayUs(): int
    {
        $map = ['gentle' => 2500000, 'slow' => 1500000, 'normal' => 800000, 'fast' => 200000];
        return $map[config('collect_speed', 'normal')] ?? 800000;
    }

    /**
     * 采集一页并入库
     * @return array ['total'=>总条数,'added'=>新增,'updated'=>更新,'pagecount'=>总页数,'msg'=>..]
     */
    public static function collectPage(int $apiId, int $page, int $typeId = 0, int $hours = 0): array
    {
        $api = Db::fetch("SELECT * FROM ky_collect_api WHERE id=?", [$apiId]);
        if (!$api) throw new RuntimeException('采集接口不存在');
        $url = rtrim($api['api_url'], '?&/');
        $params = ['ac' => 'videolist', 'pg' => max(1, $page)];
        if ($typeId > 0) $params['t'] = $typeId;
        if ($hours > 0) $params['h'] = $hours;
        $data = Http::getJson($url . '?' . http_build_query($params), 30);
        if (!is_array($data) || (int)($data['code'] ?? 0) !== 1) {
            throw new RuntimeException('资源站接口响应异常');
        }
        self::$pendingPics = [];
        $list = $data['list'] ?? [];
        $added = 0; $updated = 0; $items = [];
        foreach ($list as $item) {
            $r = self::upsertVod($apiId, $item);
            if ($r === 1) $added++;
            elseif ($r === 2) $updated++;
            $pic = trim((string)($item['vod_pic'] ?? ''));
            $items[] = [
                'name' => mb_substr(trim((string)($item['vod_name'] ?? '')), 0, 40),
                'remarks' => mb_substr(trim((string)($item['vod_remarks'] ?? '')), 0, 20),
                'status' => $r, // 1=新增 2=更新 0=跳过
                'pic' => mb_substr($pic, 0, 300),
                'pic_local' => (strpos($pic, '/') === 0),
            ];
        }
        // 页内新片封面并发下载(curl_multi,8路),成功则回写 pic
        self::flushPendingPics();
        return [
            'total' => (int)($data['total'] ?? count($list)),
            'page' => (int)($data['page'] ?? $page),
            'pagecount' => (int)($data['pagecount'] ?? 0),
            'added' => $added,
            'updated' => $updated,
            'items' => array_slice($items, 0, 30),
        ];
    }

    /**
     * 并发下载页内待处理封面并回写(失败影片保留外链)
     */
    private static function flushPendingPics(): void
    {
        if (empty(self::$pendingPics)) return;
        $urls = array_keys(self::$pendingPics);
        $results = self::downloadBatch($urls, 8);
        foreach ($results as $url => $body) {
            $stored = null;
            if (is_string($body) && strlen($body) > 64) {
                $stored = self::storePicBody($url, $body);
            }
            if ($stored !== null) {
                foreach (self::$pendingPics[$url] as $rowId) {
                    Db::update('ky_vod', ['pic' => $stored], 'id=?', [$rowId]);
                }
            }
        }
        self::$pendingPics = [];
    }

    /**
     * curl_multi 并发下载(带跳转跟随),返回 [url => body|null]
     * SSRF防护:仅允许公网http(s)地址,拒绝内网/保留段;协议白名单含重定向
     */
    public static function downloadBatch(array $urls, int $concurrency = 8): array
    {
        $out = [];
        $queue = [];
        foreach (array_unique($urls) as $u) {
            if (Http::isPublicHttpUrl($u)) $queue[] = $u;
            else $out[$u] = null;
        }
        $protocols = CURLPROTO_HTTP | CURLPROTO_HTTPS;
        while ($queue) {
            $batch = array_splice($queue, 0, max(1, $concurrency));
            $mh = curl_multi_init();
            $chs = [];
            foreach ($batch as $i => $u) {
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => str_replace(' ', '%20', $u),
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS => 3,
                    CURLOPT_PROTOCOLS => $protocols,
                    CURLOPT_REDIR_PROTOCOLS => $protocols,
                    CURLOPT_TIMEOUT => 15,
                    CURLOPT_CONNECTTIMEOUT => 8,
                    CURLOPT_SSL_VERIFYPEER => config('http_verify_ssl', '1') == '1',
                    CURLOPT_USERAGENT => 'Mozilla/5.0 (KunYing Collector)',
                ]);
                curl_multi_add_handle($mh, $ch);
                $chs[$i] = $ch;
            }
            do {
                $status = curl_multi_exec($mh, $active);
                if ($active) curl_multi_select($mh, 0.2);
            } while ($active && $status === CURLM_OK);
            foreach ($batch as $i => $u) {
                $body = curl_multi_getcontent($chs[$i]);
                $code = (int)curl_getinfo($chs[$i], CURLINFO_HTTP_CODE);
                $out[$u] = ($body !== false && $code >= 200 && $code < 300) ? $body : null;
                curl_multi_remove_handle($mh, $chs[$i]);
                curl_close($chs[$i]);
            }
            curl_multi_close($mh);
        }
        return $out;
    }

    /**
     * 下载内容入库:按存储驱动上传(local/ftp/oss/s3),返回可访问地址;失败null
     * 附带压缩:JPEG/WebP封面统一重编码为宽≤480px quality80(网格/详情显示足够,流量省6成)
     */
    private static function storePicBody(string $url, string $body): ?string
    {
        $info = @getimagesizefromstring($body);
        if ($info === false) return null;
        $exts = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
        $ext = $exts[$info[2]] ?? 'jpg';
        // 压缩:仅JPEG/WebP位图重编码;GIF(可能动图)与PNG(可能透明)保留原样
        if ($info[2] === IMAGETYPE_JPEG || $info[2] === IMAGETYPE_WEBP) {
            $body = self::shrinkImage($body, $info) ?? $body;
        }
        $key = 'vod/' . md5($url) . '.' . $ext;
        $stored = Store::put($key, $body);
        if ($stored !== null) {
            // 远端存储成功:记录本地标记文件用于后续去重复用
            if (Store::driver() !== 'local') @mkdir(KY_PATH . '/data/store-cache', 0755, true);
            if (Store::driver() !== 'local') @file_put_contents(KY_PATH . '/data/store-cache/' . md5($url), $stored);
            return $stored;
        }
        // 云盘失败:回退本地(自选目录)
        $dir = KY_PATH . Store::localWebPath();
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $file = $dir . '/' . md5($url) . '.' . $ext;
        if (!is_file($file)) file_put_contents($file, $body, LOCK_EX);
        return Store::localWebPath() . '/' . md5($url) . '.' . $ext;
    }

    /**
     * 封面压缩:宽>480px时等比缩至480,quality80;GD不可用/失败返回null(保留原图)
     */
    public static function shrinkImage(string $body, array $info): ?string
    {
        if (!function_exists('imagecreatefromstring')) return null;
        $w = (int)($info[0] ?? 0);
        if ($w <= 480 && strlen($body) < 150000) return null; // 已足够小
        $img = @imagecreatefromstring($body);
        if ($img === false) return null;
        $targetW = 480;
        $targetH = $w > 480 ? (int)round(($info[1] ?? 0) * $targetW / $w) : (int)($info[1] ?? 0);
        if ($targetW < 1 || $targetH < 1) { imagedestroy($img); return null; }
        $out = imagecreatetruecolor($targetW, $targetH);
        imagecopyresampled($out, $img, 0, 0, 0, 0, $targetW, $targetH, $w, (int)($info[1] ?? 0));
        ob_start();
        $ok = imagejpeg($out, null, 80);
        $data = $ok ? (string)ob_get_clean() : null;
        ob_end_clean();
        imagedestroy($img);
        imagedestroy($out);
        // 压缩没收益(小图/异常)则保留原字节
        if ($data === null || strlen($data) >= strlen($body)) return null;
        return $data;
    }

    /**
     * 采集详情(补充播放地址)
     */
    public static function collectDetail(int $apiId, string $apiVid): int
    {
        $api = Db::fetch("SELECT * FROM ky_collect_api WHERE id=?", [$apiId]);
        if (!$api) return 0;
        $url = rtrim($api['api_url'], '?&/');
        $data = Http::getJson($url . '?' . http_build_query(['ac' => 'detail', 'ids' => $apiVid]), 30);
        if (!is_array($data) || empty($data['list'][0])) return 0;
        $r = self::upsertVod($apiId, $data['list'][0]);
        self::flushPendingPics();
        return $r;
    }

    private static function upsertVod(int $apiId, array $item): int
    {
        $apiVid = (string)($item['vod_id'] ?? '');
        $name = trim((string)($item['vod_name'] ?? ''));
        if ($apiVid === '' || $name === '') return 0;

        $remotePic = trim((string)($item['vod_pic'] ?? ''));
        $playFrom = mb_substr(trim((string)($item['vod_play_from'] ?? '')), 0, 200);
        $playUrl = (string)($item['vod_play_url'] ?? '');
        $remarks = mb_substr(trim((string)($item['vod_remarks'] ?? '')), 0, 60);

        // 同来源已有影片
        $exists = Db::fetch("SELECT id, play_from, play_url, pic, remarks FROM ky_vod WHERE api_id=? AND api_vid=?", [$apiId, $apiVid]);

        // 封面去重:影片已有封面绝不重复采集,只有新影片/无封面影片才会下载
        $queuedPic = null;
        if ($exists && $exists['pic'] !== '') {
            $pic = $exists['pic'];
        } elseif (config('collect_img_local', '0') == '1' && preg_match('#^https?://#i', $remotePic)) {
            $known = self::knownPic($remotePic);
            if ($known !== null) {
                $pic = $known;
            } else {
                // 先以外链入库,页尾统一并发下载后回写(采集提速核心)
                $pic = $remotePic;
                $queuedPic = $remotePic;
                self::$pendingPics[$remotePic] = self::$pendingPics[$remotePic] ?? [];
            }
        } else {
            $pic = $remotePic;
        }

        // videolist无播放地址时补拉详情(按采集速度档位限速)
        if ($playUrl === '') {
            usleep(self::speedDelayUs());
            $apiUrl = Db::fetchOne("SELECT api_url FROM ky_collect_api WHERE id=?", [$apiId]);
            $d = Http::getJson(rtrim((string)$apiUrl, '?&/') . '?' . http_build_query(['ac' => 'detail', 'ids' => $apiVid]), 30);
            if (is_array($d) && !empty($d['list'][0])) {
                $playFrom = mb_substr(trim((string)($d['list'][0]['vod_play_from'] ?? $playFrom)), 0, 200);
                $playUrl = (string)($d['list'][0]['vod_play_url'] ?? '');
            }
        }

        // 已存在影片:只增量并入播放地址与更新集数备注,封面/资料不覆盖
        if ($exists) {
            [$mFrom, $mUrl] = self::mergeSources((string)$exists['play_from'], (string)$exists['play_url'], $playFrom, $playUrl);
            $upd = ['play_from' => $mFrom, 'play_url' => $mUrl, 'updatetime' => time()];
            if ($remarks !== '' && $remarks !== (string)$exists['remarks']) $upd['remarks'] = $remarks;
            Db::update('ky_vod', $upd, 'id=?', [$exists['id']]);
            if ($queuedPic !== null) self::$pendingPics[$queuedPic][] = (int)$exists['id'];
            return 2;
        }

        // 同名去重:其他来源的同名影片不重复创建,只增量并入播放地址(后台可关)
        // 含归一化匹配:过滤空格/标点/罗马数字差异后的同名(如"Ⅲ"与"第三季")也视为同一部
        $lockKey = '';
        if (config('collect_dedup_title', '1') == '1') {
            // 多进程并行采集防竞态:同片名跨进程串行化(MySQL命名锁)
            $lockKey = 'kycol:' . md5(self::normalizeName($name));
            if ((int)Db::fetchOne("SELECT GET_LOCK(?, 5)", [$lockKey]) !== 1) $lockKey = '';
        }
        if (config('collect_dedup_title', '1') == '1') {
            $norm = self::normalizeName($name);
            $same = Db::fetch("SELECT id, play_from, play_url, pic, remarks, name FROM ky_vod WHERE (name=? OR name_norm=?) AND (api_id<>? OR api_vid<>?) ORDER BY id ASC LIMIT 1", [$name, $norm, $apiId, $apiVid]);
            if ($same) {
                [$mFrom, $mUrl] = self::mergeSources((string)$same['play_from'], (string)$same['play_url'], $playFrom, $playUrl);
                $upd = ['play_from' => $mFrom, 'play_url' => $mUrl, 'updatetime' => time()];
                if ($same['remarks'] === '' && $remarks !== '') $upd['remarks'] = $remarks;
                if ($same['pic'] === '' && $pic !== '') $upd['pic'] = $pic;
                Db::update('ky_vod', $upd, 'id=?', [$same['id']]);
                if ($queuedPic !== null) self::$pendingPics[$queuedPic][] = (int)$same['id'];
                if ($lockKey !== '') Db::query("SELECT RELEASE_LOCK(?)", [$lockKey]);
                return 2;
            }
        }

        // 新影片:完整入库(分类映射+图片本地化只发生在新片)
        $typeName = trim((string)($item['type_name'] ?? ''));
        if ($typeName === '') {
            $cls = trim((string)($item['vod_class'] ?? ''));
            $typeName = $cls !== '' ? explode(',', $cls)[0] : '未分类';
        }
        $typeId = self::ensureType($typeName);

        $row = [
            'type_id' => $typeId,
            'name' => mb_substr($name, 0, 120),
            'sub' => mb_substr(trim((string)($item['vod_sub'] ?? '')), 0, 120),
            'class' => mb_substr(trim((string)($item['vod_class'] ?? '')), 0, 200),
            'year' => mb_substr(trim((string)($item['vod_year'] ?? '')), 0, 20),
            'area' => mb_substr(trim((string)($item['vod_area'] ?? '')), 0, 40),
            'lang' => mb_substr(trim((string)($item['vod_lang'] ?? '')), 0, 40),
            'remarks' => $remarks,
            'score' => min(10, max(0, (float)($item['vod_douban_score'] ?? 0))),
            'director' => mb_substr(trim((string)($item['vod_director'] ?? '')), 0, 400),
            'actor' => mb_substr(trim((string)($item['vod_actor'] ?? '')), 0, 1000),
            'content' => strip_tags((string)($item['vod_content'] ?? '')),
            'pic' => $pic,
            'play_from' => $playFrom,
            'play_url' => $playUrl,
            'total_hits' => (int)($item['vod_hits'] ?? 0),
            'api_id' => $apiId,
            'api_vid' => $apiVid,
            'name_norm' => self::normalizeName($name),
            'updatetime' => time(),
            'addtime' => time(),
        ];
        Db::insert('ky_vod', $row);
        $newId = (int)Db::pdo()->lastInsertId();
        if ($queuedPic !== null) self::$pendingPics[$queuedPic][] = $newId;
        if ($lockKey !== '') Db::query("SELECT RELEASE_LOCK(?)", [$lockKey]);
        return 1;
    }

    /**
     * 已下载过的封面缓存:本地文件或云盘URL映射,命中则不再下载
     */
    private static function knownPic(string $url): ?string
    {
        $name = md5($url);
        foreach (['jpg', 'png', 'gif', 'webp'] as $e) {
            $f = KY_PATH . Store::localWebPath() . '/' . $name . '.' . $e;
            if (is_file($f)) return Store::localWebPath() . '/' . $name . '.' . $e;
        }
        $m = KY_PATH . '/data/store-cache/' . $name;
        if (is_file($m)) {
            $u = trim((string)@file_get_contents($m));
            if ($u !== '') return $u;
        }
        return null;
    }

    /**
     * 片名归一化:去空格/标点,罗马数字与"第N季"统一,用于重复影片识别
     */
    public static function normalizeName(string $n): string
    {
        $n = mb_strtolower(trim($n));
        $n = str_replace(['　', ' '], '', $n);
        $n = strtr($n, [
            'ⅰ' => '1', 'ⅱ' => '2', 'ⅲ' => '3', 'ⅳ' => '4', 'ⅴ' => '5',
            'ⅵ' => '6', 'ⅶ' => '7', 'ⅷ' => '8', 'ⅸ' => '9', 'ⅹ' => '10',
        ]);
        if (function_exists('mb_convert_kana')) $n = mb_convert_kana($n, 'a', 'UTF-8');
        $n = preg_replace('/第([0-9一二三四五六七八九十]+)季/u', '$1季', $n);
        // 同季拆分标注剥除: "Part 2/Part2" 与名称内已有的季标(Ⅱ/第N季)重复
        $n = preg_replace('/part[0-9]+/u', '', $n);
        // 名称已含中文季数标注(第N季→N季)时,剥离重复的阿拉伯数字季标
        // 例: "无职转生Ⅲ…第三季" 与 "无职转生…第三季" 归一为同一部
        if (preg_match('/[0-9一二三四五六七八九十]+季/u', $n)) {
            $n = preg_replace('/[0-9]+/u', '', $n);
        }
        $n = preg_replace('/[\p{Han}a-z0-9]/u', '', $n) === null ? $n : preg_replace('/[^\p{Han}a-z0-9]/u', '', $n);
        return $n;
    }

    /**
     * 下载远程海报入库(本地自选目录/FTP/OSS/S3由Store驱动),失败返回原地址
     */
    public static function localizePic(string $url): string
    {
        if (!preg_match('#^https?://#i', $url) || !Http::isPublicHttpUrl($url)) return $url;
        $known = self::knownPic($url);
        if ($known !== null) return $known;
        $body = Http::getFollow($url, 12);
        if ($body === false || strlen($body) < 64) return $url;
        $stored = self::storePicBody($url, $body);
        return $stored ?? $url;
    }

    /**
     * 确保分类存在:自动归入大分类(两级),无法归类的建为一级分类
     */
    public static function ensureType(string $typeName): int
    {
        $typeName = mb_substr(trim($typeName), 0, 30);
        if ($typeName === '') $typeName = '未分类';
        if ((int)(Db::fetchOne("SELECT id FROM ky_type WHERE name=?", [$typeName]) ?? 0) > 0) {
            return (int)Db::fetchOne("SELECT id FROM ky_type WHERE name=?", [$typeName]);
        }
        $group = self::classifyGroup($typeName);
        if ($group === null) {
            // 无法归类:建为一级分类
            return Db::insert('ky_type', ['pid' => 0, 'name' => $typeName, 'sort' => 50, 'status' => 1]);
        }
        $gid = self::ensureGroup($group);
        return Db::insert('ky_type', ['pid' => $gid, 'name' => $typeName, 'sort' => 50, 'status' => 1]);
    }

    /**
     * 资源站类型名 → 大分类名
     */
    public static function classifyGroup(string $name): ?string
    {
        if (preg_match('/动漫|漫画|漫剧/u', $name)) return '动漫';
        if (mb_strpos($name, '综艺') !== false) return '综艺';
        if (mb_strpos($name, '纪录') !== false) return '纪录片';
        if (preg_match('/片$/u', $name) || mb_strpos($name, '电影') !== false) return '电影';
        if (mb_strpos($name, '短剧') !== false) return '短剧';
        if (preg_match('/都市|穿越|古装|仙侠|战神|逆袭|赘婿|千金|神医|总裁|闪婚|离婚/u', $name)) return '短剧';
        if (mb_strpos($name, '剧') !== false) return '剧集';
        return null;
    }

    private static function ensureGroup(string $name): int
    {
        $gid = (int)(Db::fetchOne("SELECT id FROM ky_type WHERE name=? AND pid=0", [$name]) ?? 0);
        if ($gid === 0) {
            $gid = Db::insert('ky_type', ['pid' => 0, 'name' => $name, 'sort' => 10, 'status' => 1]);
        }
        return $gid;
    }

    /**
     * 合并多来源播放地址:格式 "来源1$$$来源2" / "集串1$$$集串2"
     * 同名来源用新地址覆盖(资源站已更新),新来源追加
     */
    public static function mergeSources(string $oldFrom, string $oldUrl, string $newFrom, string $newUrl): array
    {
        // 播放地址增量合并:同一来源按"集数URL"去重,只追加本站没有的地址,已有地址绝不重复写入
        $oldFroms = array_values(array_filter(array_map('trim', explode('$$$', $oldFrom))));
        $oldGroups = explode('$$$', $oldUrl);
        $map = [];
        foreach ($oldFroms as $i => $f) {
            $map[$f] = $oldGroups[$i] ?? '';
        }
        $newFroms = array_values(array_filter(array_map('trim', explode('$$$', $newFrom))));
        $newGroups = explode('$$$', $newUrl);
        foreach ($newFroms as $i => $f) {
            $newGroup = $newGroups[$i] ?? '';
            if (trim($newGroup) === '') continue;
            if (!isset($map[$f]) || trim((string)$map[$f]) === '') {
                $map[$f] = $newGroup;
                continue;
            }
            $eps = explode('#', (string)$map[$f]);
            $have = [];
            foreach ($eps as $ep) {
                $pos = strrpos($ep, '$');
                $u = $pos === false ? $ep : substr($ep, $pos + 1);
                if ($u !== '') $have[$u] = 1;
            }
            foreach (explode('#', $newGroup) as $ep) {
                $pos = strrpos($ep, '$');
                $u = $pos === false ? $ep : substr($ep, $pos + 1);
                if ($u === '' || isset($have[$u])) continue;
                $eps[] = $ep;
                $have[$u] = 1;
            }
            $map[$f] = implode('#', $eps);
        }
        return [implode('$$$', array_keys($map)), implode('$$$', array_values($map))];
    }

    /**
     * 定时采集:执行所有开启自动采集的接口(按小时增量,新片与播放地址自动更新)
     * @param bool $force true=立即执行(后台按钮/CLI)
     */
    public static function runDue(bool $force = false): array
    {
        $out = [];
        if (!$force && config('collect_auto_enable', '0') != '1') return $out;
        $apis = Db::fetchAll("SELECT * FROM ky_collect_api WHERE status=1 AND collect_auto=1");
        $i = 0;
        foreach ($apis as $api) {
            if ($i++ > 0) usleep(self::speedDelayUs());
            try {
                // 定时采集统一只抓近12小时更新(新片+播放地址变更)
                $r = self::collectPage((int)$api['id'], 1, 0, 12);
                $out[] = ['api' => $api['name'], 'added' => $r['added'], 'updated' => $r['updated']];
            } catch (\Throwable $t) {
                $out[] = ['api' => $api['name'], 'error' => $t->getMessage()];
            }
        }
        config_set('collect_last_result', json_encode($out, JSON_UNESCAPED_UNICODE));
        config_set('collect_last_auto', (string)time());
        return $out;
    }

    /**
     * 是否到了定时采集时间(访客触发用)
     */
    public static function autoDue(): bool
    {
        if (config('collect_auto_enable', '0') != '1') return false;
        $interval = max(5, (int)config('collect_auto_interval', '60')) * 60;
        return time() - (int)config('collect_last_auto', 0) >= $interval;
    }

    /**
     * 获取资源站的分类列表(用于后台映射展示)
     */
    public static function fetchApiTypes(string $apiUrl): array
    {
        $data = Http::getJson(rtrim($apiUrl, '?&/') . '?' . http_build_query(['ac' => 'videolist', 'pg' => 1]), 30);
        $types = [];
        if (is_array($data)) {
            foreach (($data['class'] ?? []) as $cls) {
                $types[] = ['id' => (int)($cls['type_id'] ?? 0), 'name' => (string)($cls['type_name'] ?? '')];
            }
        }
        return $types;
    }
}
