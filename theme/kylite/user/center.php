<?php
/** dsv1 用户中心 */
$pageTitle = '用户中心';
$searchWd = '';
include theme_path('layout/header.php');
$u = Auth::user();
$tabsMap = ['index' => '我的账户', 'fav' => '我的收藏', 'record' => '播放记录', 'orders' => '充值订单'];
?>
<div class="wrap">
<div class="uc-head">
  <div class="uc-av"><?= e(mb_substr((string)$u['name'], 0, 1)) ?></div>
  <div>
    <div class="uc-nm"><?= e($u['name']) ?></div>
    <div class="uc-em"><?= e($u['email']) ?></div>
  </div>
  <div class="uc-badges">
    <div class="uc-badge"><b><?= $u['vip_expire'] > time() ? 'VIP·' . ceil(((int)$u['vip_expire'] - time()) / 86400) . '天' : '未开通' ?></b><span>会员状态</span></div>
    <div class="uc-badge"><b><?= number_format((int)$u['points']) ?></b><span>积分余额</span></div>
  </div>
</div>
<div class="uc-tabs">
  <?php foreach ($tabsMap as $k => $n): ?>
  <a class="<?= $tab === $k ? 'on' : '' ?>" href="/user/center?tab=<?= $k ?>"><?= $n ?></a>
  <?php endforeach; ?>
  <a href="/pay">💎 充值中心</a>
  <a href="/user/logout" style="color:#e5322d">退出登录</a>
</div>
<div class="uc-card">
  <div class="uc-main">
    <?php if ($tab === 'index'): ?>
    <h3>我的账户</h3>
    <div style="display:flex;gap:14px;flex-wrap:wrap;margin-bottom:20px">
      <button class="btn-main" style="width:auto;padding:10px 24px" onclick="doSign()">📅 每日签到 +<?= (int)config('points_sign', '5') ?>积分</button>
      <a class="btn-main" style="width:auto;padding:10px 24px;background:var(--gold);display:inline-block" href="/pay">💎 开通/续费VIP</a>
    </div>
    <p style="color:var(--sub);font-size:13px">签到成功可获得积分,积分可解锁单片;VIP会员在有效期内可观看全部VIP专享影片。</p>
    <script>var signed=<?= (int)$u['sign_day'] === (int)date('Ymd') ? 'true' : 'false' ?>;</script>
    <?php elseif ($tab === 'fav'): ?>
    <h3>我的收藏</h3>
    <div class="uc-grid">
      <?php foreach (($udata['favs'] ?? []) as $v): ?>
      <a class="vcard" href="/index.php?s=/vod/detail&id=<?= (int)$v['id'] ?>">
        <div class="pic"><img src="<?= e(pic_url($v['pic'])) ?>" loading="lazy"></div>
        <div class="nm"><?= e($v['name']) ?></div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php if (empty($udata['favs'])): ?><p style="color:var(--sub)">暂无收藏</p><?php endif; ?>
    <?php elseif ($tab === 'record'): ?>
    <h3>播放记录</h3>
    <div class="uc-grid">
      <?php foreach (($udata['records'] ?? []) as $r): ?>
      <a class="vcard" href="/index.php?s=/vod/detail&id=<?= (int)$r['vod_id'] ?>&ep=<?= (int)$r['episode'] ?>&play=1">
        <div class="pic"><img src="<?= e(pic_url($r['pic'])) ?>" loading="lazy"><span class="rm">看到第<?= (int)$r['episode'] ?>集</span></div>
        <div class="nm"><?= e($r['name']) ?></div>
        <div class="ds"><?= friend_date((int)$r['updated']) ?></div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php if (empty($udata['records'])): ?><p style="color:var(--sub)">暂无播放记录</p><?php endif; ?>
    <?php elseif ($tab === 'orders'): ?>
    <h3>充值订单</h3>
    <div style="overflow-x:auto"><table class="ut">
      <tr><th>订单号</th><th>套餐</th><th>金额</th><th>方式</th><th>状态</th><th>时间</th></tr>
      <?php foreach (($udata['orders'] ?? []) as $o): ?>
      <tr>
        <td style="font-size:12px"><?= e($o['order_no']) ?></td>
        <td><?= e($o['title']) ?></td>
        <td>¥<?= number_format((float)$o['amount'], 2) ?></td>
        <td><?= e($o['pay_type']) ?></td>
        <td><?= ['待支付', '<span style="color:var(--green)">已支付</span>', '已取消'][$o['status']] ?? '-' ?></td>
        <td><?= date('m-d H:i', (int)$o['created']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($udata['orders'])): ?><tr><td colspan="6" style="color:var(--sub)">暂无订单</td></tr><?php endif; ?>
    </table></div>
    <?php endif; ?>
</div>
<script>
function doSign(){
  if(signed)return kyToast('今天已签到',false);
  var d=new FormData();d.append('_csrf','<?= e(Security::csrfToken()) ?>');
  kyPost('/index.php?s=/user/sign',d).then(function(j){kyToast(j.msg,j.code===1);if(j.code===1)setTimeout(function(){location.reload()},800)});
}
</script>
<?php include theme_path('layout/footer.php'); ?>
