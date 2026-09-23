<?php
/** 坤影CMS 默认主题 kylite - 首页(幻灯轮播+分类网格) */
$pageTitle = config('site_name', '坤影影视');
$heroSlides = array_slice(($hot ?: []), 0, 6);
$heroJson = json_encode(array_map(function($v){
    return ['id'=>(int)$v['id'],'name'=>$v['name'],'pic'=>pic_url($v['pic']),'cat'=>trim(explode(',',$v['class']??'')[0]??'')?:'精选','year'=>(string)$v['year'],'remarks'=>(string)$v['remarks']];
}, $heroSlides), JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP);
include theme_path('layout/header.php');
$allTypes = $types;
?>
<!-- 幻灯轮播 -->
<div class="khero" id="kHero">
  <div class="khero-bg" id="kHeroBg1"></div>
  <div class="khero-bg" id="kHeroBg2"></div>
  <div class="khero-shade"></div>
  <button class="khero-arrow pl" id="kHeroPrev" type="button" aria-label="上一部">‹</button>
  <button class="khero-arrow pr" id="kHeroNext" type="button" aria-label="下一部">›</button>
  <div class="wrap khero-in" id="kHeroIn">
    <span class="khero-cat" id="kHeroCat"></span>
    <h1 class="khero-title" id="kHeroTitle"></h1>
    <div class="khero-tags" id="kHeroTags"></div>
    <a class="khero-play" id="kHeroPlay" href="#">▶ 立即播放</a>
  </div>
  <div class="khero-dots" id="kHeroDots"></div>
</div>
<script>
var kHeroSlides = <?= $heroJson ?>;
var kHeroIdx = 0, kHeroTimer = null, kHeroLayer = 0;
(function(){
  var box = document.getElementById('kHeroDots');
  kHeroSlides.forEach(function(d, i){
    var s = document.createElement('i');
    s.onclick = function(){ kHeroGo(i); };
    box.appendChild(s);
  });
})();
function kHeroRender(i){
  var d = kHeroSlides[i]; if(!d) return;
  var first = (kHeroIdx === i && !document.getElementById('kHeroBg1').style.backgroundImage);
  kHeroIdx = i;
  var b1 = document.getElementById('kHeroBg1'), b2 = document.getElementById('kHeroBg2');
  var showEl = kHeroLayer ? b1 : b2, hideEl = kHeroLayer ? b2 : b1;
  kHeroLayer = 1 - kHeroLayer;
  showEl.style.backgroundImage = "url('"+d.pic+"')";
  if (first) { showEl.style.transition = 'none'; }
  showEl.classList.add('on');
  hideEl.classList.remove('on');
  if (first) { void showEl.offsetWidth; showEl.style.transition = ''; }
  showEl.classList.remove('zoom'); void showEl.offsetWidth; showEl.classList.add('zoom');
  document.getElementById('kHeroCat').textContent = d.cat;
  document.getElementById('kHeroTitle').textContent = d.name;
  var tags = document.getElementById('kHeroTags');
  tags.innerHTML = (d.year?'<span>'+d.year+'</span>':'')+(d.remarks?'<span>'+d.remarks.replace(/[<>&"]/g,'')+'</span>':'');
  document.getElementById('kHeroPlay').href = '/index.php?s=/vod/detail&id='+d.id;
  var inn = document.getElementById('kHeroIn');
  inn.classList.remove('anim'); void inn.offsetWidth; inn.classList.add('anim');
  document.querySelectorAll('#kHeroDots i').forEach(function(dot,k){dot.classList.toggle('on',k===i)});
}
function kHeroGo(i){kHeroRender(i);kHeroAuto()}
function kHeroStep(n){kHeroGo((kHeroIdx+n+kHeroSlides.length)%kHeroSlides.length)}
function kHeroAuto(){clearInterval(kHeroTimer);if(kHeroSlides.length>1)kHeroTimer=setInterval(function(){kHeroStep(1)},5500)}
if (kHeroSlides.length > 0) {
  kHeroRender(0); kHeroAuto();
  var hero = document.getElementById('kHero');
  document.getElementById('kHeroPrev').onclick = function(){ kHeroStep(-1); };
  document.getElementById('kHeroNext').onclick = function(){ kHeroStep(1); };
  hero.addEventListener('mouseenter', function(){ clearInterval(kHeroTimer); });
  hero.addEventListener('mouseleave', function(){ kHeroAuto(); });
  var kTx = 0;
  hero.addEventListener('touchstart', function(e){ kTx = e.touches[0].clientX; clearInterval(kHeroTimer); }, {passive:true});
  hero.addEventListener('touchend', function(e){
    var dx = e.changedTouches[0].clientX - kTx;
    if (Math.abs(dx) > 40) { kHeroStep(dx < 0 ? 1 : -1); }
    kHeroAuto();
  }, {passive:true});
}
</script>

<!-- 热门推荐 -->
<div class="ksec">
  <div class="ksec-h"><b>热门推荐</b></div>
  <div class="ktabs" id="ktabs">
    <button class="on" data-t="0" onclick="kLoad(0,this)">首页</button>
    <?php foreach ($allTypes as $t): ?>
    <button data-t="<?= (int)$t['id'] ?>" onclick="kLoad(<?= (int)$t['id'] ?>,this)"><?= e($t['name']) ?></button>
    <?php endforeach; ?>
  </div>
  <div class="kgrid" id="kGrid">
    <?php foreach (array_slice($hot, 6, 18) as $v): ?>
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
<?php include theme_path('layout/footer.php'); ?>
