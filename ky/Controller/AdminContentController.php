<?php
/**
 * 后台 - 内容管理:影片/分类/幻灯/友链/专题/文章/播放器/采集
 */
class AdminContentController
{
    public function __construct()
    {
        Auth::requireAdmin();
        // 后台所有POST统一CSRF校验(GET视图不受影响)
        if (Request::isPost()) Security::csrfCheck();
    }

    /* ===================== 影片 ===================== */

    public function vod()
    {
        $page = max(1, Request::get('page', 1, 'i'));
        $wd = trim(Request::get('wd', ''));
        $typeId = Request::get('type', 0, 'i');
        $cond = '1'; $params = [];
        if ($wd !== '') { $cond .= ' AND name LIKE ?'; $params[] = '%' . $wd . '%'; }
        if ($typeId > 0) { $cond .= ' AND type_id=?'; $params[] = $typeId; }
        $pageSize = 20;
        $total = (int)Db::fetchOne("SELECT COUNT(*) FROM ky_vod WHERE {$cond}", $params);
        $list = Db::fetchAll("SELECT v.*,t.name tname FROM ky_vod v LEFT JOIN ky_type t ON t.id=v.type_id WHERE {$cond} ORDER BY v.id DESC LIMIT {$pageSize} OFFSET " . (($page - 1) * $pageSize), $params);
        $types = Db::fetchAll("SELECT * FROM ky_type ORDER BY sort ASC");
        $pageHtml = page_html($total, $pageSize, $page, '/admin.php?s=/content/vod&wd=' . urlencode($wd) . '&type=' . $typeId . '&page={page}');
        View::display('vod', compact('list', 'total', 'types', 'wd', 'typeId', 'pageHtml'));
    }

    public function vodform()
    {
        $id = Request::get('id', 0, 'i');
        $vod = $id > 0 ? Db::fetch("SELECT * FROM ky_vod WHERE id=?", [$id]) : null;
        $types = Db::fetchAll("SELECT * FROM ky_type ORDER BY sort ASC");
        View::display('vod_form', ['vod' => $vod, 'types' => $types]);
    }

    public function voddels()
    {
        $ids = array_values(array_filter(array_map('intval', explode(',', (string)Request::post('ids')))));
        if (!$ids) json_error('请先勾选要删除的影片');
        if (count($ids) > 500) json_error('单次最多删除500条');
        $in = implode(',', $ids);
        Db::query("DELETE FROM ky_vod WHERE id IN ({$in})");
        try { Db::query("DELETE FROM ky_comment WHERE vod_id IN ({$in})"); } catch (Throwable $t) {}
        try { Db::query("DELETE FROM ky_fav WHERE vod_id IN ({$in})"); } catch (Throwable $t) {}
        try { Db::query("DELETE FROM ky_play_record WHERE vod_id IN ({$in})"); } catch (Throwable $t) {}
        Admin::log('批量删除影片 ' . count($ids) . ' 部(ID:' . $in . ')');
        json_ok(null, '已删除 ' . count($ids) . ' 部影片');
    }

    public function vodsave()
    {
        if (!Request::isPost()) json_error('非法请求');
        $id = Request::post('id', 0, 'i');
        $data = [
            'type_id' => Request::post('type_id', 0, 'i'),
            'name' => mb_substr(trim(Request::post('name')), 0, 120),
            'sub' => mb_substr(trim(Request::post('sub')), 0, 120),
            'class' => mb_substr(trim(Request::post('class')), 0, 200),
            'year' => mb_substr(trim(Request::post('year')), 0, 20),
            'area' => mb_substr(trim(Request::post('area')), 0, 40),
            'lang' => mb_substr(trim(Request::post('lang')), 0, 40),
            'remarks' => mb_substr(trim(Request::post('remarks')), 0, 60),
            'score' => min(10, max(0, (float)Request::post('score', 0, 'f'))),
            'director' => mb_substr(trim(Request::post('director')), 0, 400),
            'actor' => mb_substr(trim(Request::post('actor')), 0, 1000),
            'content' => strip_tags(Request::post('content', '', 'raw'), '<br><p><b><strong><a><img>'),
            'pic' => mb_substr(trim(Request::post('pic')), 0, 500),
            'play_from' => trim(Request::post('play_from')),
            'play_url' => (string)Request::post('play_url', '', 'raw'),
            'vip' => Request::post('vip', 0, 'i') ? 1 : 0,
            'points' => max(0, Request::post('points', 0, 'i')),
            'status' => Request::post('status', 1, 'i') ? 1 : 0,
            'updatetime' => time(),
        ];
        if ($data['name'] === '') json_error('片名不能为空');
        if (mb_strlen($data['play_url']) > 1000000) json_error('播放地址过长(上限100万字符)');
        if (preg_match('/<\?php|<\?=|<script[\s>]/i', $data['play_url'] . $data['pic'])) json_error('播放地址包含非法内容');
        if ($id > 0) {
            Db::update('ky_vod', $data, 'id=?', [$id]);
            Admin::log('编辑影片#' . $id);
        } else {
            $data['addtime'] = time();
            $data['api_id'] = 0;
            $data['api_vid'] = '';
            $id = Db::insert('ky_vod', $data);
            Admin::log('添加影片#' . $id);
        }
        json_ok(['id' => $id]);
    }

    public function voddel()
    {
        $ids = Request::post('ids', [], 'a');
        $ids = array_map('intval', (array)$ids);
        $ids = array_filter($ids, fn($v) => $v > 0);
        if (empty($ids)) json_error('未选择');
        Db::query("DELETE FROM ky_vod WHERE id IN (" . implode(',', $ids) . ")");
        Admin::log('删除影片#' . implode(',', $ids));
        json_ok();
    }

    /* ===================== 分类 ===================== */

    public function type()
    {
        $list = Db::fetchAll("SELECT t.*, (SELECT COUNT(*) FROM ky_vod v WHERE v.type_id=t.id) c FROM ky_type t ORDER BY t.sort ASC, t.id ASC");
        View::display('type', ['list' => $list]);
    }

    public function typesave()
    {
        if (!Request::isPost()) json_error('非法请求');
        $id = Request::post('id', 0, 'i');
        $data = [
            'pid' => Request::post('pid', 0, 'i'),
            'name' => mb_substr(trim(Request::post('name')), 0, 30),
            'sort' => Request::post('sort', 0, 'i'),
            'status' => Request::post('status', 1, 'i') ? 1 : 0,
            'show_home' => Request::post('show_home', 0, 'i') ? 1 : 0,
            'icon' => mb_substr(trim((string)Request::post('icon')), 0, 8),
        ];
        if ($data['name'] === '') json_error('名称不能为空');
        if ($data['pid'] === $id && $id > 0) json_error('不能选择自己为父级');
        if ($id > 0) Db::update('ky_type', $data, 'id=?', [$id]);
        else Db::insert('ky_type', $data);
        Admin::log('保存分类:' . $data['name']);
        json_ok();
    }

    public function typedel()
    {
        $id = Request::post('id', 0, 'i');
        if ((int)Db::fetchOne("SELECT COUNT(*) FROM ky_vod WHERE type_id=?", [$id]) > 0) json_error('该分类下有影片,请先转移');
        Db::delete('ky_type', 'id=?', [$id]);
        Admin::log('删除分类#' . $id);
        json_ok();
    }

    /* ===================== 幻灯/友链/专题/文章(通用简单CRUD) ===================== */

    public function slide()
    {
        $list = Db::fetchAll("SELECT * FROM ky_slide ORDER BY sort ASC, id DESC");
        View::display('slide', ['list' => $list]);
    }

    public function slidesave()
    {
        if (!Request::isPost()) json_error('非法请求');
        $id = Request::post('id', 0, 'i');
        $slideUrl = trim(Request::post('url'));
        if ($slideUrl !== '' && !preg_match('#^https?://#i', $slideUrl) && $slideUrl[0] !== '/') {
            json_error('跳转链接仅允许http(s)或站内地址');
        }
        $pos = (string)Request::post('pos', 'top');
        $data = [
            'name' => mb_substr(trim(Request::post('name')), 0, 120),
            'pic' => mb_substr(trim(Request::post('pic')), 0, 500),
            'url' => mb_substr($slideUrl, 0, 300),
            'pos' => $pos === 'movie' ? 'movie' : 'top',
            'sort' => Request::post('sort', 0, 'i'),
            'status' => Request::post('status', 1, 'i') ? 1 : 0,
        ];
        if ($data['pic'] === '') json_error('图片地址不能为空');
        if ($id > 0) Db::update('ky_slide', $data, 'id=?', [$id]);
        else Db::insert('ky_slide', $data);
        json_ok();
    }

    public function slidedel()
    {
        Db::delete('ky_slide', 'id=?', [Request::post('id', 0, 'i')]);
        json_ok();
    }

    public function link()
    {
        $list = Db::fetchAll("SELECT * FROM ky_link ORDER BY sort ASC, id DESC");
        View::display('link', ['list' => $list]);
    }

    public function linksave()
    {
        if (!Request::isPost()) json_error('非法请求');
        $id = Request::post('id', 0, 'i');
        $data = [
            'name' => mb_substr(trim(Request::post('name')), 0, 60),
            'url' => filter_var(trim(Request::post('url')), FILTER_VALIDATE_URL) ?: '',
            'sort' => Request::post('sort', 0, 'i'),
            'status' => Request::post('status', 1, 'i') ? 1 : 0,
        ];
        if ($data['name'] === '' || $data['url'] === '') json_error('名称与网址不能为空');
        if ($id > 0) Db::update('ky_link', $data, 'id=?', [$id]);
        else Db::insert('ky_link', $data);
        page_cache_flush();
        Admin::log($id > 0 ? '编辑友链:' . $data['name'] : '添加友链:' . $data['name']);
        json_ok();
    }

    public function linkdel()
    {
        Db::delete('ky_link', 'id=?', [Request::post('id', 0, 'i')]);
        json_ok();
    }

    public function article()
    {
        $page = max(1, Request::get('page', 1, 'i'));
        $pageSize = 20;
        $total = (int)Db::fetchOne("SELECT COUNT(*) FROM ky_article");
        $list = Db::fetchAll("SELECT * FROM ky_article ORDER BY id DESC LIMIT {$pageSize} OFFSET " . (($page - 1) * $pageSize));
        View::display('article', ['list' => $list, 'pageHtml' => page_html($total, $pageSize, $page, '/admin.php?s=/content/article&page={page}')]);
    }

    public function articleform()
    {
        $id = Request::get('id', 0, 'i');
        $article = $id > 0 ? Db::fetch("SELECT * FROM ky_article WHERE id=?", [$id]) : null;
        View::display('article_form', ['article' => $article]);
    }

    public function articlesave()
    {
        if (!Request::isPost()) json_error('非法请求');
        $id = Request::post('id', 0, 'i');
        $data = [
            'title' => mb_substr(trim(Request::post('title')), 0, 200),
            'content' => (string)Request::post('content', '', 'raw'),
            'status' => Request::post('status', 1, 'i') ? 1 : 0,
        ];
        if ($data['title'] === '') json_error('标题不能为空');
        $data['content'] = strip_tags($data['content'], '<br><p><b><strong><a><img><h2><h3><ul><li><blockquote>');
        if ($id > 0) Db::update('ky_article', $data, 'id=?', [$id]);
        else { $data['addtime'] = time(); Db::insert('ky_article', $data); }
        json_ok();
    }

    public function articledel()
    {
        Db::delete('ky_article', 'id=?', [Request::post('id', 0, 'i')]);
        json_ok();
    }

    /* ===================== 播放器 ===================== */

    public function player()
    {
        $list = Db::fetchAll("SELECT * FROM ky_player ORDER BY id ASC");
        View::display('player', ['list' => $list]);
    }

    public function playersave()
    {
        if (!Request::isPost()) json_error('非法请求');
        $id = Request::post('id', 0, 'i');
        $data = [
            'code' => strtolower(preg_replace('/[^a-z0-9_]/', '', trim(Request::post('code')))),
            'name' => mb_substr(trim(Request::post('name')), 0, 60),
            'parse' => mb_substr(trim(Request::post('parse')), 0, 300),
            'status' => Request::post('status', 1, 'i') ? 1 : 0,
        ];
        if ($data['code'] === '') json_error('标识不能为空');
        if ($data['parse'] !== '' && !preg_match('#\{url\}#', $data['parse'])) json_error('解析地址必须包含{url}占位符');
        if ($id > 0) Db::update('ky_player', $data, 'id=?', [$id]);
        else Db::insert('ky_player', $data);
        json_ok();
    }

    public function playerdel()
    {
        Db::delete('ky_player', 'id=?', [Request::post('id', 0, 'i')]);
        json_ok();
    }

    /* ===================== 采集 ===================== */

    public function collect()
    {
        $apis = Db::fetchAll("SELECT * FROM ky_collect_api ORDER BY id ASC");
        $types = Db::fetchAll("SELECT * FROM ky_type WHERE status=1 ORDER BY sort ASC");
        View::display('collect', ['apis' => $apis, 'types' => $types]);
    }

    public function collectsave()
    {
        if (!Request::isPost()) json_error('非法请求');
        $id = Request::post('id', 0, 'i');
        $url = trim(Request::post('api_url'));
        if (!preg_match('#^https?://.+#i', $url)) json_error('接口地址不合法');
        $data = [
            'name' => mb_substr(trim(Request::post('name')), 0, 60),
            'api_url' => mb_substr($url, 0, 300),
            'remark' => mb_substr(trim(Request::post('remark')), 0, 200),
            'status' => Request::post('status', 1, 'i') ? 1 : 0,
            'collect_auto' => Request::post('collect_auto', 0, 'i') ? 1 : 0,
            'collect_hours' => max(1, Request::post('collect_hours', 24, 'i')),
        ];
        if ($id > 0) Db::update('ky_collect_api', $data, 'id=?', [$id]);
        else { $data['addtime'] = time(); Db::insert('ky_collect_api', $data); }
        Admin::log('保存采集接口:' . $data['name']);
        json_ok();
    }

    /**
     * 定时采集全局配置 + 同名去重 + 图片本地化开关
     */
    public function collectglobal()
    {
        if (!Request::isPost()) json_error('非法请求');
        config_set('collect_auto_enable', Request::post('collect_auto_enable', '0') == '1' ? '1' : '0');
        config_set('collect_auto_interval', (string)max(5, Request::post('collect_auto_interval', 60, 'i')));
        config_set('collect_dedup_title', Request::post('collect_dedup_title', '1') == '1' ? '1' : '0');
        config_set('collect_img_local', Request::post('collect_img_local', '0') == '1' ? '1' : '0');
        $sp = (string)Request::post('collect_speed', '');
        if (in_array($sp, ['gentle', 'slow', 'normal', 'fast'], true)) config_set('collect_speed', $sp);
        config_set('collect_threads', (string)max(1, min(8, Request::post('collect_threads', 1, 'i'))));
        // 图片存储配置(local/ftp/oss/s3)
        $store = (string)Request::post('img_store', 'local');
        if (in_array($store, ['local', 'ftp', 'oss', 's3'], true)) config_set('img_store', $store);
        $dir = '/' . trim((string)Request::post('img_dir', '/upload/vod'), '/');
        config_set('img_dir', preg_match('#^/upload(/[a-z0-9_\-]{1,40}){0,4}$#i', $dir) ? rtrim($dir, '/') : '/upload/vod');
        foreach ([
            'img_baseurl' => 200, 'ftp_host' => 120, 'ftp_user' => 60, 'ftp_pass' => 60,
            'ftp_path' => 100, 'ftp_baseurl' => 200,
            'oss_endpoint' => 150, 'oss_bucket' => 60, 'oss_ak' => 80, 'oss_sk' => 120, 'oss_baseurl' => 200,
            's3_endpoint' => 150, 's3_bucket' => 60, 's3_ak' => 80, 's3_sk' => 120, 's3_baseurl' => 200,
        ] as $k => $max) {
            config_set($k, mb_substr(trim((string)Request::post($k, '')), 0, $max));
        }
        config_set('ftp_port', (string)max(1, min(65535, Request::post('ftp_port', 21, 'i'))));
        Admin::log('修改定时采集配置');
        json_ok(null, '已保存');
    }

    /**
     * 图片存储连通性测试(写探针文件验证配置)
     */
    public function imgstoretest()
    {
        if (!Request::isPost()) json_error('非法请求');
        [$ok, $msg, $url] = Store::test();
        if ($ok) json_ok(['url' => $url], '存储可用,' . $msg . ' ' . $url);
        json_error('存储不可用:' . $msg);
    }

    /**
     * 立即执行一次定时采集(全部启用自动采集的接口,当日增量)
     */
    /**
     * 进入采集接口详情:查看资源站分类,指定资源采集
     */
    /**
     * 预览资源站内容列表(不入库)
     */
    public function collectpreview()
    {
        $id = Request::post('id', 0, 'i');
        $typeId = Request::post('type_id', 0, 'i');
        $page = max(1, Request::post('page', 1, 'i'));
        $api = Db::fetch("SELECT * FROM ky_collect_api WHERE id=?", [$id]);
        if (!$api) json_error('接口不存在');
        $url = rtrim((string)$api['api_url'], '?&/');
        $params = ['ac' => 'videolist', 'pg' => $page];
        if ($typeId > 0) $params['t'] = $typeId;
        $data = Http::getJson($url . '?' . http_build_query($params), 20);
        if (!is_array($data) || (int)($data['code'] ?? 0) !== 1) json_error('资源站接口响应异常,请稍后重试');
        $list = [];
        foreach (($data['list'] ?? []) as $it) {
            $list[] = [
                'id' => (string)($it['vod_id'] ?? ''),
                'name' => mb_substr(trim((string)($it['vod_name'] ?? '')), 0, 60),
                'type' => mb_substr(trim((string)($it['type_name'] ?? '')), 0, 20),
                'remarks' => mb_substr(trim((string)($it['vod_remarks'] ?? '')), 0, 30),
                'time' => (string)($it['vod_time'] ?? ''),
            ];
        }
        json_ok([
            'list' => $list,
            'total' => (int)($data['total'] ?? 0),
            'page' => (int)($data['page'] ?? $page),
            'pagecount' => (int)($data['pagecount'] ?? 0),
        ]);
    }

    /**
     * 扫描疑似重复影片(归一化+编辑距离,人工确认合并)
     */
    public function dupscan()
    {
        @set_time_limit(600);
        $all = Db::fetchAll("SELECT id, name, pic, year, remarks, play_from, type_id, status FROM ky_vod ORDER BY id ASC");
        $norms = []; $seasons = [];
        foreach ($all as $v) {
            $nm = mb_strtolower(trim((string)$v['name']));
            $sn = 1;
            if (preg_match('/第([0-9一二三四五六七八九十]+)季/u', $nm, $m)) {
                $t = strtr($m[1], ['一' => '1', '二' => '2', '三' => '3', '四' => '4', '五' => '5', '六' => '6', '七' => '7', '八' => '8', '九' => '9']);
                $sn = (int)$t;
            }
            $seasons[] = $sn;
            $norms[] = Collector::normalizeName((string)$v['name']);
        }
        $n = count($all);
        $used = [];
        $groups = [];
        for ($i = 0; $i < $n; $i++) {
            if (isset($used[$i])) continue;
            $grp = [$i];
            $used[$i] = 1;
            for ($j = $i + 1; $j < $n; $j++) {
                if (isset($used[$j])) continue;
                $a = $norms[$i]; $b = $norms[$j];
                if ($a === '' || $b === '') continue;
                $same = $a === $b || levenshtein($a, $b) <= max(1, (int)(strlen($a) * 0.12));
                if (!$same) continue;
                if ($seasons[$i] !== $seasons[$j]) continue; // 季数不同=不同作品
                $y1 = trim((string)$all[$i]['year']); $y2 = trim((string)$all[$j]['year']);
                if ($y1 !== '' && $y2 !== '' && $y1 !== $y2) continue; // 年份不同视为不同作品
                $grp[] = $j;
                $used[$j] = 1;
            }
            if (count($grp) > 1) $groups[] = $grp;
        }
        $out = [];
        foreach ($groups as $grp) {
            $members = [];
            foreach ($grp as $idx) {
                $v = $all[$idx];
                $members[] = [
                    'id' => (int)$v['id'],
                    'name' => $v['name'],
                    'pic' => pic_url((string)$v['pic']),
                    'year' => (string)$v['year'],
                    'remarks' => (string)$v['remarks'],
                    'sources' => count(array_filter(explode('$$$', (string)$v['play_from']))),
                    'status' => (int)$v['status'],
                ];
            }
            $out[] = $members;
        }
        json_ok(['groups' => $out, 'scanned' => $n]);
    }

    /**
     * 合并重复影片:播放地址并入主条目,关联数据转移,删除多余条目
     */
    public function dupmerge()
    {
        $keep = (int)Request::post('keep', 0, 'i');
        $ids = array_values(array_filter(array_map('intval', explode(',', (string)Request::post('ids'))), function ($v) use ($keep) { return $v > 0 && $v !== $keep; }));
        if (!$keep || !$ids) json_error('参数不完整');
        $keepVod = Db::fetch("SELECT id, play_from, play_url FROM ky_vod WHERE id=?", [$keep]);
        if (!$keepVod) json_error('主影片不存在');
        foreach ($ids as $mid) {
            $m = Db::fetch("SELECT play_from, play_url FROM ky_vod WHERE id=?", [$mid]);
            if (!$m) continue;
            [$mFrom, $mUrl] = Collector::mergeSources((string)$keepVod['play_from'], (string)$keepVod['play_url'], (string)$m['play_from'], (string)$m['play_url']);
            $keepVod['play_from'] = $mFrom;
            $keepVod['play_url'] = $mUrl;
            foreach (['ky_comment' => 'vod_id', 'ky_fav' => 'vod_id', 'ky_play_record' => 'vod_id'] as $tb => $col) {
                try { Db::query("UPDATE IGNORE {$tb} SET {$col}=? WHERE {$col}=?", [$keep, $mid]); } catch (Throwable $t) {}
            }
            Db::delete('ky_vod', 'id=?', [$mid]);
        }
        Db::update('ky_vod', ['play_from' => $keepVod['play_from'], 'play_url' => $keepVod['play_url'], 'updatetime' => time()], 'id=?', [$keep]);
        page_cache_flush();
        Admin::log('合并重复影片:' . implode(',', $ids) . ' → #' . $keep);
        json_ok(null, '已合并 ' . count($ids) . ' 条重复影片到 #' . $keep);
    }

    public function collectenter()
    {
        $id = Request::get('id', 0, 'i');
        $api = Db::fetch("SELECT * FROM ky_collect_api WHERE id=?", [$id]);
        if (!$api) halt_msg('采集接口不存在');
        View::display('collect_enter', ['api' => $api]);
    }

    /**
     * 拉取资源站的分类列表(ac=list)
     */
    public function collectclasses()
    {
        $id = Request::post('id', 0, 'i');
        $api = Db::fetch("SELECT * FROM ky_collect_api WHERE id=?", [$id]);
        if (!$api) json_error('接口不存在');
        $url = rtrim((string)$api['api_url'], '?&/') . '?ac=list';
        $data = Http::getJson($url, 20);
        if (!is_array($data)) json_error('资源站接口连接失败,请检查接口地址');
        if ((int)($data['code'] ?? 0) !== 1 && empty($data['class'])) json_error('接口未返回分类数据');
        json_ok([
            'classes' => array_values($data['class'] ?? []),
            'total' => (int)($data['total'] ?? 0),
        ]);
    }

    /**
     * 保存采集速度档位(各采集区下拉即时生效)
     */
    public function speedsave()
    {
        $v = (string)Request::post('speed', 'normal');
        if (!in_array($v, ['gentle', 'slow', 'normal', 'fast'], true)) $v = 'normal';
        config_set('collect_speed', $v);
        json_ok(null, '已保存:' . $v);
    }

    public function filmreqs()
    {
        $list = Db::fetchAll("SELECT r.*,u.name uname FROM ky_film_request r LEFT JOIN ky_user u ON u.id=r.user_id ORDER BY r.status ASC, r.id DESC LIMIT 200");
        View::display('filmreqs', ['list' => $list]);
    }

    public function filmreqdone()
    {
        if (!Request::isPost()) json_error('非法请求');
        $id = Request::post('id', 0, 'i');
        Db::update('ky_film_request', ['status' => 1], 'id=?', [$id]);
        Admin::log('求片标记完成');
        json_ok();
    }

    public function filmreqdel()
    {
        if (!Request::isPost()) json_error('非法请求');
        Db::delete('ky_film_request', 'id=?', [Request::post('id', 0, 'i')]);
        Admin::log('删除求片');
        json_ok();
    }

    public function collectauto()
    {
        @set_time_limit(300);
        $ret = Collector::runDue(true);
        Admin::log('手动执行定时采集');
        $added = 0; $updated = 0; $errs = [];
        foreach ($ret as $r) {
            if (isset($r['error'])) { $errs[] = $r['api'] . ':' . $r['error']; continue; }
            $added += $r['added']; $updated += $r['updated'];
        }
        json_ok(['added' => $added, 'updated' => $updated, 'errors' => $errs], "新增{$added} 更新{$updated}" . ($errs ? ' 部分失败:' . implode(';', $errs) : ''));
    }

    public function collectdel()
    {
        Db::delete('ky_collect_api', 'id=?', [Request::post('id', 0, 'i')]);
        json_ok();
    }

    /**
     * 执行采集(AJAX,每次采一页)
     */
    public function collectrun()
    {
        @set_time_limit(600);
        $apiId = Request::post('api_id', 0, 'i');
        $page = max(1, Request::post('page', 1, 'i'));
        $typeId = Request::post('type_id', 0, 'i');
        $hours = Request::post('hours', 0, 'i');
        try {
            $ret = Collector::collectPage($apiId, $page, $typeId, $hours);
            Admin::log("采集:接口#{$apiId} 第{$page}页 新增{$ret['added']} 更新{$ret['updated']}");
            $ret['pic_local_mode'] = config('collect_img_local', '0') == '1';
            json_ok($ret);
        } catch (Throwable $t) {
            json_error($t->getMessage());
        }
    }

    public function collecttypes()
    {
        $apiId = Request::post('api_id', 0, 'i');
        $api = Db::fetch("SELECT * FROM ky_collect_api WHERE id=?", [$apiId]);
        if (!$api) json_error('接口不存在');
        $types = Collector::fetchApiTypes($api['api_url']);
        json_ok($types);
    }
}
