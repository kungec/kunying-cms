<?php
/**
 * 前台 - AJAX接口:支付回调/评论/收藏/播放记录/验证码图片
 */
class ApiController
{
    /* ===================== 支付异步通知 ===================== */

    public function notify()
    {
        $type = preg_replace('/[^a-z0-9]/', '', Request::get('type'));
        $ono = preg_replace('/[^A-Za-z0-9]/', '', Request::get('ono'));
        if ($type === '' || $ono === '') { echo 'fail'; exit; }

        if ($type === 'usdt') {
            $order = Db::fetch("SELECT * FROM ky_order WHERE order_no=? AND status=0", [$ono]);
            if ($order) PayController::checkUsdt($order);
            echo 'success'; exit;
        }
        $data = Pay::verifyNotify($type);
        if (!$data || strcasecmp((string)$data['out_trade_no'], $ono) !== 0) { echo 'fail'; exit; }
        PayController::complete((string)$data['out_trade_no'], (string)$data['trade_no'], isset($data['money']) ? (float)$data['money'] : null);
        echo 'success'; exit;
    }

    /* ===================== 评论 ===================== */

    public function comment()
    {
        $user = Auth::user();
        if (!$user) json_error('请先登录');
        if (config('comment_enable', '1') != '1') json_error('评论已关闭');
        if (Request::isPost()) Security::csrfCheck();
        $vid = Request::jsonPost('vod_id', 0, 'i');
        $content = mb_substr(trim(Request::jsonPost('content')), 0, 300);
        if ($content === '') json_error('评论内容不能为空');
        if (!Db::fetchOne("SELECT id FROM ky_vod WHERE id=? AND status=1", [$vid])) json_error('影片不存在');
        // 简单刷评限制:60秒内1条
        $last = Db::fetchOne("SELECT created FROM ky_comment WHERE user_id=? ORDER BY id DESC LIMIT 1", [$user['id']]);
        if ($last && time() - (int)$last < 60) json_error('评论太频繁,稍后再试');
        // 评论审核开关:开启时新评论为待审状态,审核通过后前台可见
        $audit = config('comment_audit', '0') == '1';
        Db::insert('ky_comment', ['user_id' => $user['id'], 'vod_id' => $vid, 'content' => $content, 'status' => $audit ? 0 : 1, 'created' => time()]);
        json_ok(null, $audit ? '评论已提交,审核通过后展示' : '评论成功');
    }

    /* ===================== 收藏 ===================== */

    public function fav()
    {
        $user = Auth::user();
        if (!$user) json_error('请先登录');
        if (Request::isPost()) Security::csrfCheck();
        $vid = Request::jsonPost('vod_id', 0, 'i');
        if (!Db::fetchOne("SELECT id FROM ky_vod WHERE id=? AND status=1", [$vid])) json_error('影片不存在');
        $exists = Db::fetchOne("SELECT id FROM ky_fav WHERE user_id=? AND vod_id=?", [$user['id'], $vid]);
        if ($exists) {
            Db::delete('ky_fav', 'user_id=? AND vod_id=?', [$user['id'], $vid]);
            json_ok(['fav' => false], '已取消收藏');
        }
        Db::insert('ky_fav', ['user_id' => $user['id'], 'vod_id' => $vid, 'created' => time()]);
        json_ok(['fav' => true], '收藏成功');
    }

    /* ===================== 播放进度上报 ===================== */

    public function progress()
    {
        $user = Auth::user();
        if (!$user) json_ok();
        $vid = Request::jsonPost('vod_id', 0, 'i');
        $ep = Request::jsonPost('episode', 1, 'i');
        $pos = (int)Request::jsonPost('position', 0, 'i');
        if ($vid <= 0) json_ok();
        Db::query(
            "INSERT INTO ky_play_record (user_id,vod_id,episode,position,updated) VALUES (?,?,?,?,?)
             ON DUPLICATE KEY UPDATE episode=VALUES(episode),position=VALUES(position),updated=VALUES(updated)",
            [$user['id'], $vid, max(1, $ep), max(0, $pos), time()]
        );
        json_ok();
    }

    /* ===================== 验证码图片 ===================== */

    public function image()
    {
        $scene = preg_replace('/[^a-z_]/', '', Request::get('scene', 'default'));
        Captcha::output($scene === '' ? 'default' : $scene);
    }

    /* ===================== 瀑布流分页 ===================== */

    public function more()
    {
        // 访客GET:按完整参数缓存300秒(同一筛选组合的"加载更多"零重复查询)
        $guestCache = !Auth::isLogin() && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET';
        $mck = 'more_' . md5((string)($_SERVER['REQUEST_URI'] ?? ''));
        if ($guestCache) {
            $hit = page_cache_get($mck);
            if ($hit !== null) { guest_cache_headers(300); header('Content-Type: application/json; charset=utf-8'); echo $hit; exit; }
        }
        $mode = Request::get('mode', 'home') === 'type' ? 'type' : 'home';
        $page = max(1, Request::get('page', 2, 'i'));
        $pageSize = 24;
        $cond = 'status=1';
        $params = [];
        if ($mode === 'type') {
            $typeId = Request::get('type_id', 0, 'i');
            if ($typeId > 0) {
                $childIds = Db::fetchAll("SELECT id FROM ky_type WHERE pid=?", [$typeId]);
                $ids = array_merge([$typeId], array_column($childIds, 'id'));
                $cond .= ' AND type_id IN (' . implode(',', array_map('intval', $ids)) . ')';
            }
            foreach (['class', 'year', 'area'] as $f) {
                $v = trim(Request::get($f, ''));
                if ($v !== '') { $cond .= " AND {$f} LIKE ?"; $params[] = '%' . $v . '%'; }
            }
        }
        $orderMap = ['time' => 'updatetime DESC', 'hits' => 'total_hits DESC', 'score' => 'score DESC', 'new' => 'addtime DESC'];
        $order = $orderMap[Request::get('order', 'time')] ?? 'updatetime DESC';
        $total = (int)Db::fetchOne("SELECT COUNT(*) FROM ky_vod WHERE {$cond}", $params);
        $offset = ($page - 1) * $pageSize;
        $list = Db::fetchAll("SELECT * FROM ky_vod WHERE {$cond} ORDER BY {$order} LIMIT {$pageSize} OFFSET {$offset}", $params);
        $items = [];
        foreach ($list as $v) {
            $items[] = [
                'id' => (int)$v['id'],
                'name' => $v['name'],
                'pic' => pic_url($v['pic']),
                'remarks' => $v['remarks'],
                'score' => (float)$v['score'],
                'vip' => (int)$v['vip'],
                'year' => $v['year'],
                'ds' => mb_substr($v['class'] ?: (trim($v['area'] . ' ' . $v['year'])), 0, 30),
            ];
        }
        if ($guestCache) {
            $body = json_encode(['code' => 1, 'msg' => 'ok', 'data' => ['list' => $items, 'has_more' => $page * $pageSize < $total, 'next_page' => $page + 1]], JSON_UNESCAPED_UNICODE);
            page_cache_set($mck, $body, 300);
            guest_cache_headers(300);
            header('Content-Type: application/json; charset=utf-8');
            echo $body;
            exit;
        }
        json_ok(['list' => $items, 'has_more' => $page * $pageSize < $total, 'next_page' => $page + 1]);
    }

    /* ===================== 搜索建议 ===================== */

    public function suggest()
    {
        // 联想使用独立限流桶(宽松),不影响正式搜索配额;触发限流时静默返回空
        [$ok] = Security::rateLimit('suggest', 90, 60);
        if (!$ok) json_ok([]);
        $wd = mb_substr(trim(Request::get('wd', '')), 0, 30);
        if ($wd === '') json_ok([]);
        // 热词结果缓存120秒:热门关键词的重复联想打零数据库;浏览器侧同样缓存2分钟
        $ck = 'sg_' . md5($wd);
        $hit = cache_get($ck);
        if ($hit !== null) { header('Cache-Control: public, max-age=120'); json_ok($hit); }
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $wd) . '%';
        $list = Db::fetchAll("SELECT id,name,pic,remarks,year,area FROM ky_vod WHERE status=1 AND name LIKE ? ORDER BY total_hits DESC LIMIT 8", [$like]);
        cache_set($ck, $list, 120);
        header('Cache-Control: public, max-age=120');
        json_ok($list);
    }
}
