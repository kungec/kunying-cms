<?php
/** dsv1 充值中心 */
$pageTitle = '充值中心';
$searchWd = '';
include theme_path('layout/header.php');
$payList = [
    ['k' => 'alipay', 'n' => '支付宝(官方)'],
    ['k' => 'alipayf2f', 'n' => '支付宝当面付'],
    ['k' => 'wxpay', 'n' => '微信支付(官方)'],
    ['k' => 'codepay', 'n' => '码支付'],
    ['k' => 'epay', 'n' => '易支付'],
    ['k' => 'usdt', 'n' => 'USDT-TRC20'],
];
$enabled = array_filter($payList, function ($p) {
    $prefix = $p['k'] === 'codepay' ? 'codepay_' : ($p['k'] === 'epay' ? 'epay_' : '');
    switch ($p['k']) {
        case 'alipay': case 'alipayf2f':
            return config('alipay_appid') && config('alipay_private_key');
        case 'wxpay':
            return config('wxpay_appid') && config('wxpay_mchid') && config('wxpay_key');
        case 'usdt':
            return config('usdt_address') && (float)config('usdt_rate', '0') > 0;
        default:
            return config($prefix . 'gateway') && config($prefix . 'pid') && config($prefix . 'key');
    }
});
?>
<div class="wrap" style="margin-top:86px;max-width:860px">
  <div class="uc-main">
    <h3>选择充值套餐</h3>
    <?php if (empty($goods)): ?><p style="color:var(--sub)">暂无充值套餐,请联系站长在后台配置</p><?php endif; ?>
    <div class="paycards">
      <?php foreach ($goods as $g): ?>
      <div class="paycard <?= $g['days'] > 0 ? '' : '' ?>" data-gid="<?= (int)$g['id'] ?>" onclick="selGoods(this)">
        <div><?= $g['days'] > 0 ? '👑 ' : '💎 ' ?><?= e($g['name']) ?></div>
        <div class="pr">¥<?= number_format((float)$g['price'], 2) ?></div>
        <div class="ds"><?= $g['days'] > 0 ? 'VIP ' . (int)$g['days'] . ' 天' : '' ?><?= $g['points'] > 0 ? ($g['days'] > 0 ? ' + ' : '') . (int)$g['points'] . ' 积分' : '' ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <h3 style="margin-top:26px">选择支付方式</h3>
    <div class="paymethods" id="pmBox">
      <?php foreach ($enabled as $p): ?>
      <div class="pm" data-k="<?= $p['k'] ?>" onclick="selPay(this)"><?= $p['n'] ?></div>
      <?php endforeach; ?>
      <?php if (count($enabled) < 6): ?><span style="color:var(--sub);font-size:12px;align-self:center">(站长未开通的方式不显示)</span><?php endif; ?>
    </div>
    <button class="btn-main" id="goBtn" onclick="doPay()">立即支付</button>
  </div>
</div>
<script>
var goodsId=0,payType='';
function selGoods(el){
  document.querySelectorAll('.paycard').forEach(function(c){c.classList.remove('on')});
  el.classList.add('on');goodsId=el.dataset.gid;
}
function selPay(el){
  document.querySelectorAll('.pm').forEach(function(c){c.classList.remove('on')});
  el.classList.add('on');payType=el.dataset.k;
}
async function doPay(){
  if(!goodsId)return kyToast('请选择充值套餐',false);
  if(!payType)return kyToast('请选择支付方式',false);
  var d=new FormData();d.append('goods_id',goodsId);d.append('pay_type',payType);d.append('_csrf','<?= e(Security::csrfToken()) ?>');
  var j=await kyPost('/index.php?s=/pay/create',d);
  if(j.code!==1)return kyToast(j.msg,false);
  if(j.data.type==='redirect'){location.href=j.data.url;return}
  location.href='/pay/cashier?ono='+j.data.order_no;
}
</script>
<?php include theme_path('layout/footer.php'); ?>
