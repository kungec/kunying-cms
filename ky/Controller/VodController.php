<?php
/**
 * 前台 - 影片:分类列表 / 详情播放 / 搜索 / 专题
 */
class VodController
{
    public function type()
    {
        $typeId = Request::get('id', 0, 'i');
        $page = max(1, Request::get('page', 1, 'i'));
        $cacheable = !Auth::isLogin() && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET';
        if ($cacheable) @session_write_close();
        $class = mb_substr(trim(str_replace(['<', '>', '"', "'"], '', (string)Request::get('class', ''))), 0, 30);
        $year = mb_substr(trim(str_replace(['<', '>', '"', "'"], '', (string)Request::get('year', ''))), 0, 10);
        $area = mb_substr(trim(str_replace(['<', '>', '"', "'"], '', (string)Request::get('area', ''))), 0, 20);
        $order = Request::get('order', 'time');
        $type = Db::fetch("SELECT * FROM ky_type WHERE id=? AND status=1", [$typeId]);

        $cond = 'status=1';
        $params = [];
        if ($type) {
            $childIds = Db::fetchAll("SELECT id FROM ky_type WHERE pid=?", [$type['id']]);
            $ids = array_merge([$type['id']], array_column($childIds, 'id'));
            $cond .= ' AND type_id IN (' . implode(',', array_map('intval', $ids)) . ')';
        }
        foreach (['class' => $class, 'year' => $year, 'area' => $area] as $f => $v) {
            if ($v !== '') { $cond .= " AND {$f} LIKE ?"; $params[] = '%' . $v . '%'; }
        }
        $orderMap = ['time' => 'updatetime DESC', 'hits' => 'total_hits DESC', 'score' => 'score DESC', 'new' => 'addtime DESC'];
        $orderSql = $orderMap[$order] ?? 'updatetime DESC';
        $pageSize = 24;
        $total = (int)Db::fetchOne("SELECT COUNT(*) FROM ky_vod WHERE {$cond}", $params);
        $list = Db::fetchAll("SELECT * FROM ky_vod WHERE {$cond} ORDER BY {$orderSql} LIMIT {$pageSize} OFFSET " . (($page - 1) * $pageSize), $params);
        $types = Db::fetchAll("SELECT * FROM ky_type WHERE pid=0 AND status=1 ORDER BY sort ASC, id ASC");
        $pageHtml = page_html($total, $pageSize, $page, U('vod/type', array_filter([
            'id' => $typeId, 'class' => $class, 'year' => $year, 'area' => $area, 'order' => $order, 'page' => '{page}',
        ], fn($v) => $v !== '' && $v !== 0)));
        if ($cacheable) {
            $ck = 'type_' . md5($typeId . '|' . $class . '|' . $year . '|' . $area . '|' . $order . '|' . $page . '|' . config('site_mode', 'cms'));
            $hit = page_cache_get($ck);
            if ($hit !== null) { guest_cache_headers(60); echo $hit; return; }
            $html = View::load('type', compact('type', 'list', 'types', 'total', 'pageHtml', 'class', 'year', 'area', 'order'));
            page_cache_set($ck, $html, 300);
            guest_cache_headers(60);
            echo $html;
            return;
        }
        View::display('type', compact('type', 'list', 'types', 'total', 'pageHtml', 'class', 'year', 'area', 'order'));
    }

    public function detail()
    {
        $id = Request::get('id', 0, 'i');
        $ep = max(1, Request::get('ep', 1, 'i'));
        $sid = max(0, Request::get('sid', 0, 'i'));
        $vod = Db::fetch("SELECT * FROM ky_vod WHERE id=? AND status=1", [$id]);
        if (!$vod) {
            http_response_code(404);
            View::display('404', ['msg' => '影片不存在']);
            exit;
        }
        // 播放数(去重简化:每IP每小时+1;游客缓存命中时同样计数)
        $hitKey = 'hit_' . $id . '_' . md5(client_ip());
        if (!cache_get($hitKey)) {
            cache_set($hitKey, 1, 3600);
            Db::query("UPDATE ky_vod SET total_hits=total_hits+1 WHERE id=?", [$id]);
        }
        $guestCache = !Auth::isLogin() && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET';
        if ($guestCache) {
            $ck = 'detail_' . md5($id . '|' . $sid . '|' . $ep . '|' . (int)Request::get('play', 0, 'i'));
            $hit = page_cache_get($ck);
            if ($hit !== null) { guest_cache_headers(60); echo $hit; return; }
            guest_cache_headers(60);
        }

        $sources = VodService::parsePlay($vod);
        // 源可用性:只读探测缓存(毫秒级),未探测的源按可用渲染并由后台异步探测,绝不阻塞页面
        $usableIdx = [];
        $allKnown = true;
        foreach ($sources as $i => $src) {
            $r = VodService::probeSource($src);
            if ($r === null) { $allKnown = false; $r = true; }
            if ($r) $usableIdx[] = $i;
        }
        if ($allKnown && !empty($usableIdx) && count($usableIdx) < count($sources)) {
            $remap = []; $newSources = [];
            foreach ($usableIdx as $ni => $oi) { $remap[$oi] = $ni; $newSources[] = $sources[$oi]; }
            $sources = $newSources;
            if (isset($remap[$sid])) $sid = $remap[$sid];
            else $sid = 0;
        }
        $sid = min($sid, max(0, count($sources) - 1));
        // 未明确指定来源时,自动优选可直连播放(m3u8/mp4)的源
        if (!isset($_GET['sid']) && count($sources) > 1) {
            foreach ($sources as $i => $src) {
                $first = (string)($src['episodes'][0]['url'] ?? '');
                if (($src['parse'] ?? '') === '' && preg_match('/\.(m3u8|mp4)(\?|#|$)/i', $first)) {
                    $sid = $i;
                    break;
                }
            }
        }
        $source = $sources[$sid] ?? null;
        $episodes = $source['episodes'] ?? [];
        $ep = min($ep, max(1, count($episodes)));
        $current = $episodes[$ep - 1] ?? null;

        // 访问控制
        $needVip = $vod['vip'] == 1;
        $needPoints = (int)$vod['points'];
        $user = Auth::user();
        $canPlay = true; $reason = '';
        if ($needVip) {
            if (!Auth::isLogin()) { $canPlay = false; $reason = 'login'; }
            elseif (!Auth::isVip()) { $canPlay = false; $reason = 'vip'; }
        } elseif ($needPoints > 0) {
            if (!Auth::isLogin()) { $canPlay = false; $reason = 'login'; }
            elseif (!self::hasBought($id)) {
                if ($user && $user['points'] >= $needPoints) { $canPlay = false; $reason = 'buy'; }
                else { $canPlay = false; $reason = 'points'; }
            }
        }

        // 播放记录/断点
        $record = null;
        if ($user && $current) {
            $record = Db::fetch("SELECT * FROM ky_play_record WHERE user_id=? AND vod_id=?", [$user['id'], $id]);
            if (Request::get('play', 0, 'i') === 1 && $canPlay) {
                self::saveRecord($user['id'], $id, $ep);
            }
        }
        $related = Db::fetchAll("SELECT * FROM ky_vod WHERE status=1 AND type_id=? AND id<>? ORDER BY total_hits DESC LIMIT 12", [$vod['type_id'], $id]);
        $comments = Db::fetchAll(
            "SELECT c.*,u.name,u.avatar FROM ky_comment c LEFT JOIN ky_user u ON u.id=c.user_id WHERE c.vod_id=? AND c.status=1 ORDER BY c.id DESC LIMIT 20",
            [$id]
        );
        $html = View::load('play', compact('vod', 'sources', 'source', 'episodes', 'ep', 'current', 'related', 'comments', 'canPlay', 'reason', 'record'));
        if ($guestCache) page_cache_set('detail_' . md5($id . '|' . $sid . '|' . $ep . '|' . (int)Request::get('play', 0, 'i')), $html, 600);
        echo $html;
        // 页面已送达:断开用户连接,在后台完成源探测(结果写缓存,下次访问生效)
        if (!$allKnown) {
            // 先释放会话锁再断开连接:否则后台探测期间(可达数十秒),用户紧接着的
            // 任何请求都会阻塞在session_start上,表现为"进播放页后回首页卡"
            if (function_exists('session_write_close')) @session_write_close();
            if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
            @set_time_limit(120);
            VodService::probeAsync($sources);
        }
        return;
    }

    public static function hasBought(int $vodId): bool
    {
        $uid = Auth::userId();
        if ($uid === 0) return false;
        $row = Db::fetchOne("SELECT id FROM ky_user_vod WHERE user_id=? AND vod_id=?", [$uid, $vodId]);
        return $row !== null;
    }

    public static function saveRecord(int $uid, int $vodId, int $ep): void
    {
        Db::query(
            "INSERT INTO ky_play_record (user_id,vod_id,episode,position,updated) VALUES (?,?,?,?,?)
             ON DUPLICATE KEY UPDATE episode=VALUES(episode),updated=VALUES(updated)",
            [$uid, $vodId, $ep, time()]
        );
    }

    public function search()
    {
        // 搜索频率限制(后台可配)
        if (config('search_limit_enable', '1') == '1') {
            [$ok] = Security::rateLimit('search', max(1, (int)config('search_limit_times', '30')), max(5, (int)config('search_limit_window', '60')));
            if (!$ok) {
                Request::isAjax() ? json_error('搜索太频繁,请稍后再试') : halt_msg('搜索太频繁,请稍后再试');
            }
        }
        $wd = trim(Request::get('wd', ''));
        $page = max(1, Request::get('page', 1, 'i'));
        $list = []; $total = 0; $pageSize = 24;
        // 访客搜索结果缓存:热词重复搜索零数据库(浏览器同样缓存60秒)
        $searchCacheable = !Auth::isLogin() && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && $wd !== '';
        if ($searchCacheable) @session_write_close();
        $sck = 'search_' . md5($wd . '|' . $page);
        if ($searchCacheable) {
            $shit = page_cache_get($sck);
            if ($shit !== null) { guest_cache_headers(60); echo $shit; return; }
        }
        if ($wd !== '') {
            if (!preg_match('/^[\x{4e00}-\x{9fa5}A-Za-z0-9_\- \x{0080}-\x{FFFF}]{1,60}$/u', $wd)) {
                $wd = '';
            } else {
                $total = (int)Db::fetchOne("SELECT COUNT(*) FROM ky_vod WHERE status=1 AND name LIKE ?", ['%' . $wd . '%']);
                $list = Db::fetchAll("SELECT * FROM ky_vod WHERE status=1 AND name LIKE ? ORDER BY total_hits DESC LIMIT {$pageSize} OFFSET " . (($page - 1) * $pageSize), ['%' . $wd . '%']);
            }
        }
        $types = Db::fetchAll("SELECT * FROM ky_type WHERE pid=0 AND status=1 ORDER BY sort ASC, id ASC");
        $pageHtml = $wd !== '' ? page_html($total, $pageSize, $page, U('vod/search', ['wd' => $wd, 'page' => '{page}'])) : '';
        if ($searchCacheable && $total > 0) {
            page_cache_set($sck, View::load('search', compact('list', 'total', 'wd', 'types', 'pageHtml')), 300);
            guest_cache_headers(60);
        }
        View::display('search', compact('list', 'total', 'wd', 'types', 'pageHtml'));
    }

    public function topic()
    {
        $id = Request::get('id', 0, 'i');
        $topic = Db::fetch("SELECT * FROM ky_topic WHERE id=? AND status=1", [$id]);
        $topics = Db::fetchAll("SELECT * FROM ky_topic WHERE status=1 ORDER BY id DESC");
        $list = [];
        if ($topic) {
            // 专题内容里每行一个影片ID或名称
            $ids = [];
            foreach (preg_split('/[\s,，]+/u', (string)$topic['content']) ?: [] as $seg) {
                if (preg_match('/^\d+$/', $seg)) $ids[] = (int)$seg;
            }
            if (!empty($ids)) {
                $ids = array_slice(array_map('intval', $ids), 0, 60);
                $list = Db::fetchAll("SELECT * FROM ky_vod WHERE status=1 AND id IN (" . implode(',', $ids) . ")");
            }
        }
        $types = Db::fetchAll("SELECT * FROM ky_type WHERE pid=0 AND status=1 ORDER BY sort ASC, id ASC");
        View::display('topic', compact('topic', 'topics', 'list', 'types'));
    }
}
