<?php $pageTitle = $pageTitle ?? '管理后台'; $adminName = $admin['username'] ?? ''; ?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($pageTitle) ?> - 坤影CMS管理后台</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{--bg:#f3f5f9;--side:#191b22;--card:#fff;--line:#e6e9f0;--txt:#242a37;--sub:#7b8394;--red:#e5322d;--blue:#2f6bff;--green:#22a55e}
body{font-family:-apple-system,"PingFang SC","Microsoft YaHei",sans-serif;background:var(--bg);color:var(--txt);font-size:14px}
a{color:inherit;text-decoration:none}
.layout{display:flex;min-height:100vh}
.side{width:216px;background:var(--side);color:#aeb4c2;position:fixed;top:0;bottom:0;left:0;overflow-y:auto;z-index:50;transition:transform .25s}
.side .brand{display:flex;align-items:center;gap:9px;padding:18px 16px;color:#fff;font-weight:700;font-size:16px;border-bottom:1px solid #23262f}
.brand .i{width:26px;height:26px;background:var(--red);border-radius:50%;display:flex;align-items:center;justify-content:center;flex:none}
.brand .i::after{content:"";border-left:8px solid #fff;border-top:5px solid transparent;border-bottom:5px solid transparent;margin-left:2px}
.side .grp{padding:14px 16px 4px;font-size:12px;color:#5d626e}
.side a.mi{display:flex;align-items:center;gap:10px;padding:11px 16px;font-size:14px;border-left:3px solid transparent}
.side a.mi:hover{background:#20232c;color:#fff}
.side a.mi.on{background:#23262f;color:#fff;border-left-color:var(--red)}
.main{flex:1;margin-left:216px;padding:0 20px 40px}
.topbar{display:flex;align-items:center;justify-content:space-between;padding:14px 2px}
.topbar h1{font-size:18px}
.topbar .ops{display:flex;gap:10px;align-items:center}
.card{background:var(--card);border-radius:10px;padding:18px;box-shadow:0 1px 2px rgba(16,24,40,.04);margin-bottom:16px}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:8px 16px;background:var(--red);color:#fff;border:0;border-radius:7px;font-size:13px;cursor:pointer}
.btn.plain{background:#eef0f5;color:var(--txt)}
.btn.blue{background:var(--blue)}
.btn.sm{padding:4px 10px;font-size:12px}
.btn[disabled]{opacity:.5;cursor:not-allowed}
table.tb{width:100%;border-collapse:collapse}
.tb th{text-align:left;font-size:12px;color:var(--sub);font-weight:600;padding:10px 8px;border-bottom:1px solid var(--line);white-space:nowrap}
.tb td{padding:10px 8px;border-bottom:1px solid var(--line);font-size:13px;vertical-align:middle}
.tb tr:hover td{background:#fafbfd}
.form{max-width:620px}
.fi{margin-bottom:14px}
.fi label{display:block;font-size:13px;color:var(--sub);margin-bottom:6px}
input[type=text],input[type=password],input[type=number],input[type=email],input[type=date],select,textarea{width:100%;padding:9px 12px;border:1px solid var(--line);border-radius:7px;font-size:13px;outline:none;background:#fff;color:var(--txt);font-family:inherit}
input:focus,select:focus,textarea:focus{border-color:var(--blue)}
textarea{min-height:80px;resize:vertical}
.row{display:flex;gap:12px}.row>.fi{flex:1}
.hint{font-size:12px;color:var(--sub);margin-top:5px}
.tabs{display:flex;gap:4px;border-bottom:1px solid var(--line);margin-bottom:16px;overflow-x:auto}
.tabs a{padding:10px 14px;font-size:13px;color:var(--sub);white-space:nowrap;border-bottom:2px solid transparent}
.tabs a.on{color:var(--red);border-bottom-color:var(--red);font-weight:600}
.stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}
.stat{background:var(--card);border-radius:10px;padding:16px}
.stat .n{font-size:22px;font-weight:700;margin-top:6px}
.stat .t{font-size:12px;color:var(--sub)}
.stat.red .n{color:var(--red)}
.tag{display:inline-block;padding:2px 8px;border-radius:20px;font-size:12px}
.tag.g{background:#e5f6ec;color:var(--green)}
.tag.r{background:#fdecec;color:var(--red)}
.tag.b{background:#e8efff;color:var(--blue)}
.tag.gr{background:#eef0f5;color:var(--sub)}
.ky-page{display:flex;gap:6px;margin-top:14px;flex-wrap:wrap}
.ky-page a,.ky-page span{padding:6px 11px;border:1px solid var(--line);border-radius:6px;font-size:12px;background:#fff}
.ky-page .cur{background:var(--red);color:#fff;border-color:var(--red)}
.modal{display:none;position:fixed;inset:0;background:rgba(10,12,16,.45);z-index:100;align-items:flex-start;justify-content:center;overflow:auto;padding:6vh 16px}
.modal.open{display:flex}
.modal .mbox{background:#fff;border-radius:12px;padding:22px;width:100%;max-width:560px}
.modal h3{margin-bottom:14px;font-size:16px}
.notice{background:#fff7e8;border:1px solid #ffe2ac;color:#8a5b00;border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:13px}
.searchbar{display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap}
.searchbar input,.searchbar select{width:auto;min-width:140px}
.overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:49}
@media(max-width:900px){
.side{transform:translateX(-100%)}
.side.open{transform:none}
.overlay.show{display:block}
.main{margin-left:0}
.stat-grid{grid-template-columns:repeat(2,1fr)}
.row{flex-direction:column;gap:0}
.tb-wrap{overflow-x:auto}
}
.op-btn{display:inline-flex;align-items:center;gap:6px;padding:7px 16px;border-radius:18px;border:1px solid #e4e6eb;background:#fff;color:#3d4148;font-size:13px;text-decoration:none;transition:.18s;cursor:pointer;font-family:inherit}
.op-btn:hover{color:#e5322d;border-color:#e5322d;background:#fff5f4;transform:translateY(-1px);box-shadow:0 4px 12px rgba(229,50,45,.12)}
.op-btn.exit:hover{color:#7a7f8a;border-color:#c9ccd4;background:#f7f8fb;box-shadow:none}
</style>
</head>
<body>
<div class="layout">
<aside class="side" id="side">
  <div class="brand"><div class="i"></div>坤影CMS</div>
  <a class="mi" href="/admin.php?s=/main/dashboard" data-m="main/dashboard">◈ 仪表盘</a>
  <div class="grp">内容管理</div>
  <a class="mi" href="/admin.php?s=/content/vod" data-m="content/vod">▪ 视频管理</a>
  <a class="mi" href="/admin.php?s=/content/type" data-m="content/type">▪ 分类管理</a>
  <a class="mi" href="/admin.php?s=/content/slide" data-m="content/slide">▪ 幻灯管理</a>
  <a class="mi" href="/admin.php?s=/content/article" data-m="content/article">▪ 文章公告</a>
  <a class="mi" href="/admin.php?s=/content/link" data-m="content/link">▪ 友情链接</a>
  <div class="grp">采集中心</div>
  <a class="mi" href="/admin.php?s=/content/collect" data-m="content/collect">▪ 采集管理</a>
  <a class="mi" href="/admin.php?s=/content/filmreqs" data-m="content/filmreqs">▪ 求片管理</a>
  <a class="mi" href="/admin.php?s=/content/player" data-m="content/player">▪ 播放器管理</a>
  <div class="grp">用户中心</div>
  <a class="mi" href="/admin.php?s=/user/user" data-m="user/user">▪ 会员列表</a>
  <a class="mi" href="/admin.php?s=/user/goods" data-m="user/goods">▪ 充值套餐</a>
  <a class="mi" href="/admin.php?s=/user/order" data-m="user/order">▪ 订单管理</a>
  <a class="mi" href="/admin.php?s=/user/comment" data-m="user/comment">▪ 评论管理</a>
  <div class="grp">应用商店</div>
  <a class="mi" href="/admin.php?s=/system/market&tab=plugin" data-m="system/market">▪ 插件市场</a>
  <a class="mi" href="/admin.php?s=/system/market&tab=template" data-m="system/market">▪ 模板市场</a>
  <div class="grp">系统</div>
  <a class="mi" href="/admin.php?s=/system/setting" data-m="system/setting">▪ 系统设置</a>
  <a class="mi" href="/admin.php?s=/system/images" data-m="system/images">▪ 图片管理</a>
  <a class="mi" href="/admin.php?s=/system/update" data-m="system/update">▪ 在线更新</a>
  <a class="mi" href="/admin.php?s=/system/log" data-m="system/log">▪ 安全日志</a>
</aside>
<div class="overlay" id="ovl" onclick="sideToggle(false)"></div>
<div class="main">
<div class="topbar">
  <div style="display:flex;align-items:center;gap:10px">
    <button class="btn plain sm" style="display:none" id="menuBtn" onclick="sideToggle(true)">☰</button>
    <h1><?= e($pageTitle) ?></h1>
  </div>
  <div class="ops">
    <span style="color:var(--sub);font-size:12px"><?= e($adminName) ?></span>
    <a class="op-btn" href="/" target="_blank" title="在新窗口访问网站">
      <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M3.5 12h17M12 3.2c2.6 2.4 4 5.4 4 8.8s-1.4 6.4-4 8.8c-2.6-2.4-4-5.4-4-8.8s1.4-6.4 4-8.8z"/></svg>
      <span>访问站点</span>
    </a>
    <a class="op-btn" href="javascript:;" onclick="clearCache(this)" title="清理系统缓存(页面/配置缓存立即生效)">
      <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M9 6V4h6v2M6.5 6l1 14h9l1-14"/></svg>
      <span>清理缓存</span>
    </a>
    <a class="op-btn exit" href="/admin.php?s=/main/logout">
      <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 4h4a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-4M10 17l-5-5 5-5M5 12h10"/></svg>
      <span>退出</span>
    </a>
  </div>
  <script>
  async function clearCache(btn){
    if (btn.dataset.busy) return;
    btn.dataset.busy = '1';
    var old = btn.innerHTML;
    btn.innerHTML = '<span>清理中…</span>';
    var j = await api('/admin.php?s=/system/clearcache', new FormData());
    btn.dataset.busy = '';
    btn.innerHTML = old;
    toast(j.msg || (j.code === 1 ? '缓存已清理' : '失败'), j.code === 1);
  }
  </script>
</div>
<script>
window.CSRF = '<?= e(Security::csrfToken()) ?>';
function sideToggle(open){var s=document.getElementById('side'),o=document.getElementById('ovl');s.classList.toggle('open',open);o.classList.toggle('show',open)}
if(window.matchMedia('(max-width:900px)').matches){document.getElementById('menuBtn').style.display='inline-flex'}
var cur='<?= e(($m1 ?? '') . '/' . ($m2 ?? '')) ?>';
document.querySelectorAll('.side a.mi').forEach(function(a){
  if(a.dataset.m===location.search.match(/s=\/([a-z]+\/[a-z]+)/i)?.[1]) a.classList.add('on');
});
async function api(url,data){
  if(data instanceof FormData && !data.has('_csrf')) data.append('_csrf', window.CSRF);
  var r=await fetch(url,{method:'POST',body:data,headers:{'X-Requested-With':'XMLHttpRequest'}});
  var t=await r.text();
  try{return JSON.parse(t)}catch(e){return {code:0,msg:'响应异常:'+t.slice(0,120)}}
}
function toast(msg,ok){
  var d=document.createElement('div');
  d.style.cssText='position:fixed;top:18px;left:50%;transform:translateX(-50%);z-index:999;padding:10px 22px;border-radius:8px;color:#fff;font-size:13px;box-shadow:0 8px 24px rgba(0,0,0,.18);background:'+(ok===false?'#e5322d':'#22a55e');
  d.textContent=msg;document.body.appendChild(d);setTimeout(function(){d.remove()},2500);
}
function confirmDel(msg){return window.confirm(msg||'确定删除?此操作不可恢复')}
</script>
