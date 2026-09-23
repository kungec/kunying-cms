<?php
/** 搜索页(默认主题:电影海报模式) */
$pageTitle = $wd !== '' ? '搜索:' . $wd : '搜索';
$searchWd = $wd;
$sm = 'movie';
include theme_path('layout/header.php');
$hotWords = Db::fetchAll("SELECT id, name FROM ky_vod WHERE status=1 ORDER BY total_hits DESC LIMIT 10");
?>
<div class="ksearch-card">
  <form class="ksearch-bar" action="/index.php" method="get">
    <input type="hidden" name="s" value="/vod/search">
    <input type="text" name="wd" value="<?= e($wd) ?>" placeholder="输入影片名称" autocomplete="off">
    <button type="submit"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg></button>
  </form>
  <?php if ($wd === ''): ?>
  <div style="max-width:min(680px,90%);margin:18px auto 0">
    <div style="font-size:13px;color:var(--sub);margin-bottom:10px">🔥 热门搜索</div>
    <div style="display:flex;flex-wrap:wrap;gap:10px">
      <?php foreach ($hotWords as $h): ?>
      <a href="/index.php?s=/vod/search&wd=<?= rawurlencode($h['name']) ?>" style="background:#fff;color:#4a4e57;font-size:13px;padding:7px 18px;border-radius:16px;text-decoration:none;box-shadow:0 1px 4px rgba(0,0,0,.05)"><?= e($h['name']) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<div class="wrap">
  <?php if ($wd !== ''): ?>
  <p style="font-size:13px;color:var(--sub);margin:14px 0">找到 <b style="color:var(--red)"><?= $total ?></b> 部与「<?= e($wd) ?>」相关的影片</p>
  <div class="mgrid">
    <?php foreach ($list as $v): ?>
    <a class="mcard" href="/index.php?s=/vod/detail&id=<?= (int)$v['id'] ?>">
      <div class="pic"><img src="<?= e(pic_url($v['pic'])) ?>" loading="lazy" alt="<?= e($v['name']) ?>">
        <?php if ((float)$v['score'] > 0): ?><span class="bd"><?= rtrim(rtrim(number_format((float)$v['score'], 1), '0'), '.') ?></span><?php endif; ?>
        <?php if ($v['remarks']): ?><span class="rm"><?= e($v['remarks']) ?></span><?php endif; ?>
        <?php if ($v['vip']): ?><span class="vip">vip</span><?php endif; ?>
      </div>
      <div class="nm"><?= e($v['name']) ?></div>
      <div class="ds"><?= e(trim((string)$v['area'] . (($v['area'] && $v['year']) ? ' · ' : '') . (string)$v['year'])) ?></div>
    </a>
    <?php endforeach; ?>
  </div>
  <?php if (empty($list)): ?><p style="text-align:center;color:var(--sub);padding:50px 0">没有找到相关影片,换个关键词试试吧</p><?php endif; ?>
  <?= $pageHtml ?>
  <?php else: ?>
  <div class="ksec">
    <div class="ksec-h"><b>热门推荐</b></div>
    <div class="mgrid">
      <?php foreach (Db::fetchAll("SELECT * FROM ky_vod WHERE status=1 ORDER BY total_hits DESC LIMIT 18") as $v): ?>
      <a class="mcard" href="/index.php?s=/vod/detail&id=<?= (int)$v['id'] ?>">
        <div class="pic"><img src="<?= e(pic_url($v['pic'])) ?>" loading="lazy" alt="<?= e($v['name']) ?>">
          <?php if ((float)$v['score'] > 0): ?><span class="bd"><?= rtrim(rtrim(number_format((float)$v['score'], 1), '0'), '.') ?></span><?php endif; ?>
          <?php if ($v['remarks']): ?><span class="rm"><?= e($v['remarks']) ?></span><?php endif; ?>
          <?php if ($v['vip']): ?><span class="vip">vip</span><?php endif; ?>
        </div>
        <div class="nm"><?= e($v['name']) ?></div>
        <div class="ds"><?= e(trim((string)$v['area'] . (($v['area'] && $v['year']) ? ' · ' : '') . (string)$v['year'])) ?></div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
(function(){
  var si = document.querySelector('.ksearch-bar input[name=wd]');
  if (!si) return;
  var bar = si.closest('.ksearch-bar');
  if (getComputedStyle(bar).position === 'static') bar.style.position = 'relative';
  var box = document.createElement('div');
  box.className = 'ksg';
  box.style.cssText = 'display:none;left:0;right:0;top:calc(100% + 8px);position:absolute;background:#fff;border:1px solid var(--line);border-radius:12px;box-shadow:0 16px 40px rgba(0,0,0,.14);z-index:300;overflow:hidden;max-height:420px;overflow-y:auto';
  bar.appendChild(box);
  var t = null;
  function esc(s){ return String(s||'').replace(/[<>&"]/g,''); }
  function render(w, j){
    if (!j.code || !j.data || !j.data.length) { box.style.display = 'none'; return; }
    var k = w;
    var h = j.data.map(function(v){
      var nm = esc(v.name);
      var hl = k ? nm.split(k).join('<i style="color:var(--red);font-style:normal;font-weight:700">' + k + '</i>') : nm;
      return '<a href="/index.php?s=/vod/detail&id=' + v.id + '" style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-bottom:1px solid #f0f1f3;color:var(--txt)">'
        + '<img src="' + v.pic + '" onerror="this.style.visibility=\'hidden\'" style="width:38px;height:52px;object-fit:cover;border-radius:5px;flex:none;background:#f0f1f3">'
        + '<span style="flex:1;min-width:0"><b style="font-size:13px;font-weight:600">' + hl + '</b><em style="display:block;font-size:12px;color:var(--sub)">' + esc(v.remarks || '') + '</em></span></a>';
    }).join('')
    + '<a class="ksg-all" href="/index.php?s=/vod/search&wd=' + encodeURIComponent(w) + '" style="display:block;text-align:center;padding:9px;color:var(--red);font-weight:600;font-size:12px;background:var(--card2)">查看「' + k + '」的全部搜索结果 →</a>';
    box.innerHTML = h;
    box.style.display = 'block';
  }
  si.addEventListener('input', function(){
    clearTimeout(t);
    var w = si.value.trim();
    if (!w) { box.style.display = 'none'; return; }
    t = setTimeout(function(){
      fetch('/index.php?s=/api/suggest&wd=' + encodeURIComponent(w)).then(function(r){ return r.json(); }).then(function(j){ render(w, j); }).catch(function(){});
    }, 300);
  });
  si.addEventListener('keydown', function(e){
    if (e.key === 'Enter') { e.preventDefault(); si.closest('form').submit(); }
    if (e.key === 'Escape') { box.style.display = 'none'; }
  });
  document.addEventListener('click', function(ev){ if (!box.contains(ev.target) && ev.target !== si) box.style.display = 'none'; });
})();
</script>
<?php include theme_path('layout/footer.php'); ?>
