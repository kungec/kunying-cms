<?php
/**
 * 前台 - 首页
 */
class IndexController
{
    public function index()
    {
        $cacheable = !Auth::isLogin() && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET';
        if ($cacheable) {
            $hit = page_cache_get('home');
            if ($hit !== null) { echo $hit; return; }
        }
        $slides = Db::fetchAll("SELECT * FROM ky_slide WHERE status=1 AND (pos='top' OR pos='') ORDER BY sort ASC, id DESC LIMIT 10");
        $movieSlides = Db::fetchAll("SELECT * FROM ky_slide WHERE status=1 AND pos='movie' ORDER BY sort ASC, id DESC LIMIT 8");
        $hot = VodService::list(['order' => 'total_hits DESC'], 10);
        $new = VodService::list(['order' => 'addtime DESC'], 14);
        if (config('kp_slide_source', 'new') === 'hot') {
            $hotSlides = VodService::list(['order' => 'total_hits DESC'], (int)config('kp_slide_count', '6') ?: 6);
        } else {
            $hotSlides = $new;
        }
        $score = VodService::list(['order' => 'score DESC, total_hits DESC'], 10);
        $types = Db::fetchAll("SELECT * FROM ky_type WHERE pid=0 AND status=1 ORDER BY sort ASC, id ASC");
        $topicNew = Db::fetchAll("SELECT * FROM ky_topic WHERE status=1 ORDER BY id DESC LIMIT 4");
        $links = Db::fetchAll("SELECT * FROM ky_link WHERE status=1 ORDER BY sort ASC, id ASC LIMIT 20");
        $announcements = [];
        if (config('show_api_notice', '0') == '1') {
            $announcements = array_slice(License::notices(), 0, 3);
        }
        // 首页分类模块:后台勾选"首页显示"的顶级分类,含子分类影片
        $homeBlocks = [];
        $homeTypes = Db::fetchAll("SELECT * FROM ky_type WHERE pid=0 AND status=1 AND show_home=1 ORDER BY sort ASC, id ASC");
        foreach ($homeTypes as $ht) {
            $ids = [(int)$ht['id']];
            foreach (Db::fetchAll("SELECT id FROM ky_type WHERE pid=?", [$ht['id']]) as $c) {
                $ids[] = (int)$c['id'];
            }
            $in = implode(',', array_map('intval', $ids));
            $list = Db::fetchAll("SELECT * FROM ky_vod WHERE status=1 AND type_id IN ({$in}) ORDER BY id DESC LIMIT 12");
            if ($list) $homeBlocks[] = ['type' => $ht, 'list' => $list];
        }
        $html = View::load('index', compact('slides', 'movieSlides', 'hot', 'new', 'hotSlides', 'score', 'types', 'topicNew', 'links', 'announcements', 'homeBlocks'));
        if ($cacheable) page_cache_set('home', $html, 600);
        echo $html;
    }

    public function article()
    {
        $id = Request::get('id', 0, 'i');
        $article = Db::fetch("SELECT * FROM ky_article WHERE id=? AND status=1", [$id]);
        $articles = Db::fetchAll("SELECT id,title,addtime FROM ky_article WHERE status=1 ORDER BY id DESC LIMIT 20");
        View::display('article', ['article' => $article, 'articles' => $articles]);
    }

    /** 站点地图(缓存1小时) */
    public function sitemap()
    {
        $ck = 'sitemap_xml';
        $xml = cache_get($ck);
        if (!is_string($xml) || $xml === '') {
            $host = (is_https() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
            $rw = config('rewrite_enable', '0') == '1';
            $u = function ($p) use ($host, $rw) { return $host . ($rw ? $p : '/index.php?s=' . ltrim($p, '/')); };
            $urls = [$u('/'), $u('/index.php?s=/index/sitemap')];
            foreach (Db::fetchAll("SELECT id FROM ky_type WHERE status=1 ORDER BY sort ASC, id ASC LIMIT 100") as $t) {
                $urls[] = $u('/index.php?s=/vod/type&id=' . (int)$t['id']);
            }
            foreach (Db::fetchAll("SELECT id,updatetime FROM ky_vod WHERE status=1 ORDER BY updatetime DESC LIMIT 5000") as $v) {
                $urls[] = $u('/index.php?s=/vod/detail&id=' . (int)$v['id']);
            }
            $xml = '<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n';
            foreach ($urls as $link) {
                $xml .= '<url><loc>' . htmlspecialchars($link, ENT_QUOTES) . '</loc></url>
';
            }
            $xml .= '</urlset>';
            cache_set($ck, $xml, 3600);
        }
        header('Content-Type: application/xml; charset=utf-8');
        echo $xml;
    }

    /** RSS订阅源(最新50部) */
    public function rss()
    {
        header('Content-Type: application/rss+xml; charset=utf-8');
        $host = (is_https() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
        $siteName = config('site_name', '坤影影视');
        $rows = Db::fetchAll("SELECT id,name,content,pic,updatetime FROM ky_vod WHERE status=1 ORDER BY updatetime DESC LIMIT 50");
        $nl = chr(10);
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . $nl . '<rss version="2.0"><channel>' . $nl;
        $xml .= '<title>' . htmlspecialchars($siteName, ENT_QUOTES) . '</title>' . $nl;
        $xml .= '<link>' . htmlspecialchars($host, ENT_QUOTES) . '</link>' . $nl;
        $xml .= '<description>' . htmlspecialchars(config('site_description', '最新影视资源'), ENT_QUOTES) . '</description>' . $nl;
        foreach ($rows as $r) {
            $url = $host . (config('rewrite_enable', '0') == '1' ? '/detail-' . (int)$r['id'] . '.html' : '/index.php?s=/vod/detail&id=' . (int)$r['id']);
            $xml .= '<item>' . $nl;
            $xml .= '<title>' . htmlspecialchars($r['name'], ENT_QUOTES) . '</title>' . $nl;
            $xml .= '<link>' . htmlspecialchars($url, ENT_QUOTES) . '</link>' . $nl;
            $xml .= '<description>' . htmlspecialchars(mb_substr(strip_tags((string)$r['content']), 0, 200), ENT_QUOTES) . '</description>' . $nl;
            $xml .= '<pubDate>' . date('r', (int)$r['updatetime']) . '</pubDate>' . $nl;
            $xml .= '</item>' . $nl;
        }
        $xml .= '</channel></rss>';
        echo $xml;
    }
}
