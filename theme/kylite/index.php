<?php
/** 坤影CMS 默认主题 kylite - 首页 */
$pageTitle = config('site_name', '坤影影视');
include theme_path('layout/header.php');
$allTypes = $types;
?>
<div class="ksearch-card">
  <form class="ksearch-bar" action="/index.php" method="get">
    <input type="hidden" name="s" value="/vod/search">
    <input type="text" name="wd" placeholder="输入关键词" autocomplete="off">
    <button type="submit"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg></button>
  </form>
</div>

<div class="ksec">
  <div class="ksec-h"><b>热门推荐</b></div>
  <div class="ktabs" id="ktabs">
    <button class="on" data-t="0" onclick="kLoad(0,this)">首页</button>
    <?php foreach ($allTypes as $t): ?>
    <button data-t="<?= (int)$t['id'] ?>" onclick="kLoad(<?= (int)$t['id'] ?>,this)"><?= e($t['name']) ?></button>
    <?php endforeach; ?>
  </div>
  <div class="kgrid" id="kGrid">
    <?php foreach (array_slice($hot, 0, 18) as $v): ?>
    <a class="kcard" href="/index.php?s=/vod/detail&id=<?= (int)$v['id'] ?>">
      <div class="kc-pic"><img src="<?= e(pic_url($v['pic'])) ?>" loading="lazy" alt="<?= e($v['name']) ?>">
        <?php if ($v['remarks']): ?><span class="kc-rm"><?= e($v['remarks']) ?></span><?php endif; ?>
      </div>
      <?php if ((float)$v['score'] > 0): ?><div class="kc-score">评分: <?= rtrim(rtrim(number_format((float)$v['score'], 1), '0'), '.') ?></div><?php endif; ?>
      <div class="kc-nm"><?= e($v['name']) ?></div>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<?php foreach ($homeBlocks as $hb): ?>
<div class="ksec">
  <div class="ksec-h"><b><?= e($hb['type']['name']) ?></b><a href="/index.php?s=/vod/type&id=<?= (int)$hb['type']['id'] ?>">更多 ›</a></div>
  <div class="kgrid">
    <?php foreach ($hb['list'] as $v): ?>
    <a class="kcard" href="/index.php?s=/vod/detail&id=<?= (int)$v['id'] ?>">
      <div class="kc-pic"><img src="<?= e(pic_url($v['pic'])) ?>" loading="lazy" alt="<?= e($v['name']) ?>">
        <?php if ($v['remarks']): ?><span class="kc-rm"><?= e($v['remarks']) ?></span><?php endif; ?>
      </div>
      <?php if ((float)$v['score'] > 0): ?><div class="kc-score">评分: <?= rtrim(rtrim(number_format((float)$v['score'], 1), '0'), '.') ?></div><?php endif; ?>
      <div class="kc-nm"><?= e($v['name']) ?></div>
    </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endforeach; ?>

<script>
function kCard(v){
  var sc = v.score > 0 ? '<div class="kc-score">评分: ' + (Math.round(v.score*10)/10) + '</div>' : '';
  var rm = v.remarks ? '<span class="kc-rm">' + String(v.remarks).replace(/[<>&"]/g,'') + '</span>' : '';
  return '<a class="kcard" href="/index.php?s=/vod/detail&id=' + v.id + '"><div class="kc-pic"><img src="' + v.pic + '" loading="lazy">' + rm + '</div>' + sc + '<div class="kc-nm">' + String(v.name).replace(/[<>&"]/g,'') + '</div></a>';
}
function kLoad(tid, btn){
  document.querySelectorAll('#ktabs button').forEach(function(b){ b.classList.toggle('on', b === btn); });
  var grid = document.getElementById('kGrid');
  grid.innerHTML = '<div class="hint" style="grid-column:1/-1;text-align:center;padding:30px;color:var(--sub)">加载中…</div>';
  fetch('/index.php?s=/api/more&type_id=' + tid + '&page=1&order=hits').then(function(r){ return r.json(); }).then(function(j){
    if (!j.code) { grid.innerHTML = '<div class="hint" style="grid-column:1/-1;text-align:center;padding:30px">加载失败</div>'; return; }
    var html = '';
    (j.data.list || []).forEach(function(v){ html += kCard(v); });
    grid.innerHTML = html || '<div class="hint" style="grid-column:1/-1;text-align:center;padding:30px">该分类暂无影片</div>';
  }).catch(function(){ grid.innerHTML = ''; });
}
</script>

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
