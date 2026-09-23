<?php
/** 坤影CMS 默认主题 kylite - 头部 */
$navData = cache_get('kylite_nav');
if (!is_array($navData)) {
    $navTypes = Db::fetchAll("SELECT * FROM ky_type WHERE pid=0 AND status=1 ORDER BY sort ASC, id ASC LIMIT 12");
    $navKids = [];
    foreach (Db::fetchAll("SELECT * FROM ky_type WHERE pid>0 AND status=1 ORDER BY sort ASC, id ASC") as $nk) $navKids[(int)$nk['pid']][] = $nk;
    $navData = [$navTypes, $navKids];
    cache_set('kylite_nav', $navData, 300);
} else {
    list($navTypes, $navKids) = $navData;
}
$curTypeId = ((($GLOBALS['ky_controller'] ?? '') === 'vod') && ($GLOBALS['ky_action'] ?? '') === 'type') ? (int)($_GET['id'] ?? 0) : 0;
$curUser = Auth::user();
$curController = $GLOBALS['ky_controller'] ?? '';
$curAction = $GLOBALS['ky_action'] ?? '';
$siteName = config('site_name', '坤影影视');
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="theme-color" content="#0f1013">
    <link rel="manifest" href="/static/site.webmanifest">
    <link rel="apple-touch-icon" href="/static/img/icon-192.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?= e($pageTitle ?? $siteName) ?> - <?= e($siteName) ?></title>
<meta name="keywords" content="<?= e($pageKeywords ?? config('site_keywords', '')) ?>">
<meta name="description" content="<?= e($pageDescription ?? config('site_description', '')) ?>">
<?php
/* SEO:OG分享卡片+规范链接 */
$seoBase = (is_https() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
$seoTitle = trim((string)($pageTitle ?? '')) !== '' ? e($pageTitle) . ' - ' . e($siteName) : e($siteName);
$seoDesc = e($pageDescription ?? config('site_description', ''));
$seoImg = '';
if (isset($vod) && $vod) {
    $u = pic_url((string)$vod['pic']);
    $seoImg = preg_match('#^https?://#i', $u) ? $u : $seoBase . $u;
}
?>
<meta property="og:site_name" content="<?= e($siteName) ?>">
<meta property="og:title" content="<?= $seoTitle ?>">
<meta property="og:description" content="<?= $seoDesc ?>">
<?php if ($seoImg !== ''): ?><meta property="og:image" content="<?= e($seoImg) ?>"><meta name="twitter:card" content="summary_large_image"><?php endif; ?>
<?php if (isset($vod) && $vod): ?><link rel="canonical" href="<?= $seoBase ?><?= config('rewrite_enable', '0') == '1' ? '/detail-' . (int)$vod['id'] . '.html' : '/index.php?s=/vod/detail&id=' . (int)$vod['id'] ?>"><?php endif; ?>
<link rel="stylesheet" href="<?= theme_url('static/css/main.css') ?>?v=<?= asset_v('/theme/' . active_theme() . '/static/css/main.css') ?>">
<link rel="preload" href="<?= theme_url('static/css/main.css') ?>" as="style">
<script src="<?= theme_url('static/js/main.js') ?>?v=<?= asset_v('/theme/' . active_theme() . '/static/js/main.js') ?>" defer></script>
<?php if (config('captcha_provider', '') === 'geetest'): ?>
<link rel="preconnect" href="https://static.geetest.com" crossorigin>
<link rel="preconnect" href="https://api.geetest.com">
<?php endif; ?>
<script>
function hdrSearchSubmit(ev){
  ev.preventDefault();
  var f = ev.target;
  var wd = f.querySelector("input[name=wd]").value.trim();
  if (!wd) return false;
  location.href = "/index.php?s=/vod/search&wd=" + encodeURIComponent(wd);
  return false;
}
</script>
<script>
/* 暗色模式初始化:尽早执行避免闪白 */
(function(){
  var t = localStorage.getItem('ky_theme');
  if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
    document.documentElement.setAttribute('data-theme','dark');
  }
})();
</script>
</head>
<body>
<header class="ktop">
  <div class="ktop-in">
    <button class="kburger" onclick="document.getElementById('kdr').classList.add('open');document.getElementById('kdrm').classList.add('open')"><span></span><span></span><span></span></button>
    <a class="klogo" href="/"><svg viewBox="0 0 24 24" width="26" height="26"><path d="M4 3l16 9-16 9z" fill="#ff5a5f"/><path d="M4 3l9 9-9 9z" fill="#2f7df6"/><path d="M13 12l7-9v18z" fill="#ffb400"/></svg><?= e(config('site_logo_text', $siteName)) ?></a>
    <nav class="knav">
      <a href="/" class="<?= ($curController ?? '') === 'index' ? 'on' : '' ?>">首页</a>
      <?php
      foreach ($navTypes as $t):
          $kids = $navKids[(int)$t['id']] ?? [];
          $cur = ($curController ?? '') === 'vod' && $curTypeId === (int)$t['id'];
      ?>
      <div class="kitem">
        <a class="<?= $cur ? 'on' : '' ?>" href="/index.php?s=/vod/type&id=<?= (int)$t['id'] ?>"><?= e($t['name']) ?></a>
        <?php if ($kids): ?>
        <div class="ksub">
          <?php foreach ($kids as $k): ?>
          <a class="<?= $curTypeId === (int)$k['id'] ? 'on' : '' ?>" href="/index.php?s=/vod/type&id=<?= (int)$k['id'] ?>"><?= e($k['name']) ?></a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </nav>
    <div class="ksearch-hdr">
      <form class="ksearch-form" action="/index.php" method="get" onsubmit="return hdrSearchSubmit(event)">
        <input type="hidden" name="s" value="/vod/search">
        <input type="text" name="wd" id="hdrWd" placeholder="搜索影片…" autocomplete="off" required>
        <button type="submit" class="ksearch-btn"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg></button>
      </form>
      <div class="ksg" id="hdrSg"></div>
    </div>
    <div class="kuser">
      <a href="/user/login">登录</a>
      <?php if (config('register_enable', '1') == '1'): ?><a class="kreg" href="/user/register">注册</a><?php endif; ?>
    </div>
  </div>
</header>


<!-- 手机抽屉菜单 -->
<div class="kdrawer-mask" id="kdrm" onclick="kDrawerClose()"></div>
<nav class="kdrawer" id="kdr">
  <div class="kd-logo"><svg viewBox="0 0 24 24" width="24" height="24"><path d="M4 3l16 9-16 9z" fill="#ff5a5f"/><path d="M4 3l9 9-9 9z" fill="#2f7df6"/><path d="M13 12l7-9v18z" fill="#ffb400"/></svg><?= e(config('site_logo_text', $siteName)) ?></div>
  <a class="kd-i <?= ($curController ?? '') === 'index' ? 'on' : '' ?>" href="/">首页推荐</a>
  <?php foreach ($navTypes as $t):
      $kids = $navKids[(int)$t['id']] ?? [];
  ?>
  <a class="kd-i" href="/index.php?s=/vod/type&id=<?= (int)$t['id'] ?>"><?= e($t['name']) ?></a>
  <?php foreach ($kids as $k): ?>
  <div class="kd-sub"><a href="/index.php?s=/vod/type&id=<?= (int)$k['id'] ?>">· <?= e($k['name']) ?></a></div>
  <?php endforeach; ?>
  <?php endforeach; ?>
  <div class="kd-sub"></div>
  <?php if ($curUser): ?>
  <a class="kd-i" href="/user/center">👤 会员中心</a>
  <a class="kd-i" href="/user/history">🕐 观看历史</a>
  <a class="kd-i" href="/pay">💎 会员充值</a>
  <a class="kd-i" href="/user/logout">🚪 退出登录</a>
  <?php else: ?>
  <a class="kd-i" href="/user/login">👤 登录</a>
  <?php if (config('register_enable', '1') == '1'): ?><a class="kd-i" href="/user/register">✍️ 注册账号</a><?php endif; ?>
  <?php endif; ?>
</nav>

<!-- 手机底部Tab栏 -->
<nav class="ktabbar">
  <a href="/" class="<?= ($curController ?? '') === 'index' ? 'on' : '' ?>"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.8L12 3l9 7.8"/><path d="M5.2 9.5V21h13.6V9.5"/></svg><span>首页</span></a>
  <?php $ktFirst = $navTypes[0] ?? null; ?>
  <a href="<?= $ktFirst ? '/index.php?s=/vod/type&id=' . (int)$ktFirst['id'] : '/' ?>" class="<?= ($curController ?? '') === 'vod' && $curTypeId === (int)($ktFirst['id'] ?? 0) ? 'on' : '' ?>"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="2.6"/><path d="M12 3.5v5.9M12 14.6v5.9M3.5 12h5.9M14.6 12h5.9"/></svg><span><?= $ktFirst ? e($ktFirst['name']) : '分类' ?></span></a>
  <a href="/index.php?s=/vod/search" class="<?= ($curController ?? '') === 'vod' && ($curAction ?? '') === 'search' ? 'on' : '' ?>"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg><span>搜索</span></a>
  <a href="/pay" class="<?= ($curController ?? '') === 'pay' ? 'on' : '' ?>"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l2.7 5.6 6.3.9-4.5 4.3 1 6.2-5.5-2.9-5.5 2.9 1-6.2L3 9.5l6.3-.9z"/></svg><span>会员</span></a>
  <a href="<?= $curUser ? '/user/center' : '/user/login' ?>" class="<?= in_array($curController ?? '', ['user', 'pay']) ? 'on' : '' ?>"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5"/></svg><span><?= $curUser ? '我的' : '登录' ?></span></a>
</nav>

