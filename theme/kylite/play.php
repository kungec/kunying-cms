<?php
/** dsv1 播放/详情页 */
$pageTitle = $vod['name'];
$pageDescription = mb_substr(strip_tags((string)$vod['content']), 0, 100);
include theme_path('layout/header.php');
$episodes = $episodes ?? [];
$current = $current ?? null;
$isVipUser = Auth::isVip();
$startPos = ($record && (int)$record['episode'] === (int)$ep) ? (int)$record['position'] : 0;
// 解析规则支持 m3u8:{url} 前缀:对地址做改写后仍由内置播放器直连播放
$parseRule = trim((string)($source['parse'] ?? ''));
$directUrl = '';
$isIframe = false;
if ($parseRule !== '') {
    if (stripos($parseRule, 'm3u8:') === 0) {
        $directUrl = str_replace('{url}', (string)($current['url'] ?? ''), substr($parseRule, 5));
    } else {
        $isIframe = true;
    }
}
// 远程m3u8统一走同源代理(防资源站防盗链/跨域拦截)
$playerSrc = '';
if ($current) {
    $playerSrc = $directUrl !== '' ? $directUrl : (string)$current['url'];
    if ($playerSrc !== '' && preg_match('/\.m3u8(\?|#|$)/i', $playerSrc)) {
        $playerSrc = m3u8_proxy_url($playerSrc);
    }
}
// 构建客户端换源/选集数据(不刷新页面即时切换)
$jsSources = [];
foreach ($sources as $src) {
    $rule = trim((string)($src['parse'] ?? ''));
    $isM3u8Rule = $rule !== '' && stripos($rule, 'm3u8:') === 0;
    $eps = [];
    foreach ($src['episodes'] as $epi) {
        $u = $epi['url'];
        if ($isM3u8Rule) $u = str_replace('{url}', $u, substr($rule, 5));
        $psrc = preg_match('/\.m3u8(\?|#|$)/i', $u) ? m3u8_proxy_url($u) : $u;
        $eps[] = ['name' => $epi['name'], 'url' => $epi['url'], 'psrc' => $psrc];
    }
    $jsSources[] = [
        'name' => $src['name'],
        'iframe' => ($rule !== '' && !$isM3u8Rule) ? $rule : '',
        'episodes' => $eps,
    ];
}
?>
<div class="wrap">
<?= ad_slot('ad_playtop') ?>
<div class="play-wrap">
  <div>
    <div class="player-box" id="playerBox">
      <?php if (!$canPlay): ?>
      <div class="plock">
        <div class="lk"><?= $reason === 'vip' ? '👑' : ($reason === 'buy' || $reason === 'points' ? '💎' : '🔒') ?></div>
        <?php if ($reason === 'login'): ?>
        <p>本片需要登录后观看</p>
        <a href="/user/login?back=<?= rawurlencode('/index.php?s=/vod/detail&id=' . $vod['id'] . '&ep=' . $ep) ?>">立即登录</a>
        <?php elseif ($reason === 'vip'): ?>
        <p>VIP专享影片,开通会员即可畅享全部内容</p>
        <a href="/pay">开通VIP会员</a>
        <?php elseif ($reason === 'buy'): ?>
        <p>本片需消耗 <?= (int)$vod['points'] ?> 积分解锁,当前余额 <?= $curUser['points'] ?? 0 ?> 积分</p>
        <a href="javascript:;" onclick="buyVod(<?= (int)$vod['id'] ?>)">积分解锁本片</a>
        <?php else: ?>
        <p>积分不足,本片需 <?= (int)$vod['points'] ?> 积分解锁</p>
        <a href="/pay">前往充值</a>
        <?php endif; ?>
      </div>
      <?php elseif ($isIframe && $current): ?>
      <iframe src="<?= e(str_replace('{url}', rawurlencode((string)$current['url']), $source['parse'])) ?>" allowfullscreen allow="autoplay; fullscreen" referrerpolicy="no-referrer"></iframe>
      <?php elseif ($current): ?>
      <div id="kunplayer" style="width:100%;height:100%"></div>
      <?php else: ?>
      <div class="plock"><p>暂无播放资源</p></div>
      <?php endif; ?>
    </div>

    <?= ad_slot('ad_playbottom') ?>
    <div class="vdesc" style="margin-top:16px">
      <b style="color:var(--txt);font-size:16px"><?= e($vod['name']) ?></b>
      <span style="color:var(--sub);margin-left:10px"><?= e($vod['remarks']) ?> · 播放来源:<span id="srcName"><?= e($source['name'] ?? '无') ?></span></span>
      <p style="margin-top:10px"><?= nl2text(mb_substr((string)$vod['content'], 0, 1000)) ?></p>
    </div>
  </div>

  <aside class="pinfo">
    <div class="tabs"><a id="tabDetail" class="on" href="javascript:;">详情</a><a id="tabComment" href="javascript:;">评论(<?= count($comments) ?>)</a></div>
    <h1><?= e($vod['name']) ?></h1>
    <div class="pmeta"><?= e($vod['year']) ?> / <?= e($vod['area']) ?> / <?= e($vod['class'] ?: '影视') ?> / <a href="javascript:;" onclick="document.querySelector('.vdesc').scrollIntoView({behavior:'smooth'})">简介></a></div>
    <div class="pscore">站内: <b><?= rtrim(rtrim(number_format((float)$vod['score'], 1), '0'), '.') ?: '-' ?></b><span style="background:var(--green);color:#fff;font-size:11px;padding:1px 6px;border-radius:5px">评分</span></div>
    <div class="pacts">
      <a onclick="toggleFav(<?= (int)$vod['id'] ?>, this)"><span class="ic">☆</span>收藏影片</a>
      <a href="/index.php?s=/index/article&id=list"><span class="ic">💬</span>反馈/求片</a>
      <a onclick="shareFilm()"><span class="ic">🔗</span>影片分享</a>
    </div>
    <div class="ep-h"><b>资源列表</b></div>
    <?php if (count($sources) > 1): ?>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px">
      <?php foreach ($sources as $i => $src): ?>
      <a class="pm <?= $i === (int)$sid ? 'on' : '' ?>" style="padding:6px 12px;font-size:12px" href="javascript:;" onclick="switchSource(<?= $i ?>)"><?= e($src['name']) ?></a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="ep-grid" id="epGrid"></div>
  </aside>
</div>

<section class="sec">
  <div class="sec-h"><h3>相关视频</h3></div>
  <div class="mgrid">
    <?php foreach ($related as $v): $vv = $v; ?>
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
</section>

<section class="sec" id="comments">
  <div class="sec-h"><h3>评论</h3></div>
  <div class="vdesc" style="padding:18px">
  <?php if (Auth::isLogin()): ?>
  <div class="cmt-form">
    <input type="text" id="cmtInput" placeholder="发表你的看法…" maxlength="300">
    <button onclick="postComment(<?= (int)$vod['id'] ?>)">发表</button>
  </div>
  <?php else: ?>
  <p style="color:var(--sub);font-size:13px;margin-bottom:10px"><a href="/user/login" style="color:var(--red2)">登录</a> 后参与评论</p>
  <?php endif; ?>
  <div id="cmtList">
    <?php foreach ($comments as $c): ?>
    <div class="cmt">
      <div class="av"><?= e(mb_substr((string)($c['name'] ?? '客'), 0, 1)) ?></div>
      <div class="bd">
        <span class="nm"><?= e($c['name'] ?? '用户') ?></span><span class="tm"><?= friend_date((int)$c['created']) ?></span>
        <p><?= e($c['content']) ?></p>
      </div>
    </div>
    <?php endforeach; ?>
  <?php if (empty($comments)): ?>
    <p style="color:var(--sub);font-size:13px;padding:14px 0;text-align:center">暂无评论,快来抢沙发~</p>
    <?php endif; ?>
  </div>
</section>
</div>

<script>
(function(){
  var d = document.getElementById('tabDetail'), c = document.getElementById('tabComment');
  if (!d || !c) return;
  function setActive(which){
    d.classList.toggle('on', which === 'detail');
    c.classList.toggle('on', which === 'comment');
  }
  d.addEventListener('click', function(){ setActive('detail'); window.scrollTo({top: 0, behavior: 'smooth'}); });
  c.addEventListener('click', function(){ setActive('comment'); var el = document.getElementById('comments'); if (el) el.scrollIntoView({behavior: 'smooth'}); });
})();
</script>
<script src="<?= theme_url('static/player/hls.js') ?>?v=<?= asset_v('/theme/dsv1/static/player/hls.js') ?>"></script>
<script src="<?= theme_url('static/player/kunplayer.js') ?>?v=<?= asset_v('/theme/dsv1/static/player/kunplayer.js') ?>"></script>
<script>
var kyVod = <?= json_encode([
    'id' => (int)$vod['id'],
    'name' => $vod['name'],
    'epRemarks' => $vod['remarks'],
    'canPlay' => (bool)$canPlay,
    'poster' => pic_url($vod['pic']),
    'csrf' => Security::csrfToken(),
    'sources' => $jsSources,
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
var curSid = <?= (int)$sid ?>, curEp = <?= (int)$ep ?>, player = null;
var kyAuto = <?= config('player_autoplay', '1') == '1' ? 'true' : 'false' ?>;

function renderEpGrid(){
  var eps = kyVod.sources[curSid].episodes, h = '';
  for (var i = 0; i < eps.length; i++) {
    h += '<a class="' + (i+1===curEp ? 'on' : '') + '" href="javascript:;" onclick="playEpisode(' + (i+1) + ')">' + eps[i].name.replace(/[<>&"]/g,'') + '</a>';
  }
  document.getElementById('epGrid').innerHTML = h || '<p style="color:var(--sub);font-size:12px">暂无选集</p>';
  var srcTabs = document.querySelectorAll('.pinfo a.pm');
  srcTabs.forEach && srcTabs.forEach(function(a,i){ a.classList.toggle('on', i===curSid); });
  document.getElementById('srcName').textContent = kyVod.sources[curSid].name;
}
function buildPlayer(autoplay){
  var box = document.getElementById('kunplayer');
  if (!box) return;
  if (player) { try { player.destroy(); } catch(e) {} player = null; }
  box.innerHTML = '<div id="kunplayerInner" style="width:100%;height:100%"></div>';
  var src = kyVod.sources[curSid], ep = src.episodes[curEp-1];
  if (!ep) return;
  var recordPos = (curSid === startSid && curEp === startEp) ? startPos : 0;
  player = new KunPlayer({
    container: '#kunplayerInner',
    src: ep.psrc,
    type: /m3u8/i.test(ep.psrc) ? 'm3u8' : 'mp4',
    poster: kyVod.poster,
    start: recordPos,
    vodId: kyVod.id,
    episode: curEp,
    autoplay: !!autoplay,
    onNext: function(){ var n = src.episodes[curEp]; if (n) playEpisode(curEp+1); },
    onProgress: function(pos){
      fetch('/index.php?s=/api/progress',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({vod_id:kyVod.id,episode:curEp,position:pos,_csrf:kyVod.csrf})}).catch(function(){});
      try {
        var arr = JSON.parse(localStorage.getItem('ky_watch_hist') || '[]').filter(function(x){ return x.id !== kyVod.id; });
        arr.unshift({ id: kyVod.id, name: kyVod.name, pic: kyVod.poster, remarks: (src.episodes[curEp-1] && curEp > 1) ? '第' + curEp + '集' : (kyVod.epRemarks || ''), ep: curEp, time: Date.now() });
        localStorage.setItem('ky_watch_hist', JSON.stringify(arr.slice(0, 50)));
      } catch(e) {}
    }
  });
  if (autoplay) { try { player.video.play(); } catch(e) {} }
}
function switchSource(i){
  if (i === curSid) return;
  curSid = i; curEp = 1;
  renderEpGrid(); buildPlayer(true);
}
function playEpisode(n){
  curEp = n;
  renderEpGrid(); buildPlayer(true);
  try { history.replaceState(null, '', '/index.php?s=/vod/detail&id=' + kyVod.id + '&sid=' + curSid + '&ep=' + curEp); } catch(e) {}
}
var startSid = curSid, startEp = curEp, startPos = <?= (int)$startPos ?>;
renderEpGrid();
</script>
<?php if ($canPlay && $current && !$isIframe && $playerSrc !== ''): ?>
<script>
buildPlayer(kyAuto);
</script>
<?php endif; ?>
<script>
function toggleFav(id,el){
  var d=new FormData();d.append('vod_id',id);d.append('_csrf','<?= e(Security::csrfToken()) ?>');
  kyPost('/index.php?s=/api/fav',d).then(function(j){
    kyToast(j.msg,j.code===1);
    if(j.code===1&&el)el.querySelector('.ic').textContent=j.data.fav?'★':'☆';
  });
}
function postComment(id){
  var inp=document.getElementById('cmtInput'),t=inp.value.trim();
  if(!t)return kyToast('请输入评论内容',false);
  var d=new FormData();d.append('vod_id',id);d.append('content',t);d.append('_csrf','<?= e(Security::csrfToken()) ?>');
  kyPost('/index.php?s=/api/comment',d).then(function(j){
    kyToast(j.msg,j.code===1);
    if(j.code===1){inp.value='';setTimeout(function(){location.reload()},700)}
  });
}
function buyVod(id){
  var d=new FormData();d.append('id',id);d.append('_csrf','<?= e(Security::csrfToken()) ?>');
  kyPost('/index.php?s=/pay/buyvod',d).then(function(j){
    kyToast(j.msg,j.code===1);
    if(j.code===1)setTimeout(function(){location.reload()},800);
  });
}
function shareFilm(){
  var url=location.href;
  if(navigator.clipboard){navigator.clipboard.writeText(url).then(function(){kyToast('链接已复制')})}
}
</script>
<?php include theme_path('layout/footer.php'); ?>
