<?php
/** dsv1 收银台(扫码/等待支付) */
$pageTitle = '收银台';
$searchWd = '';
include theme_path('layout/header.php');
?>
<div class="wrap" style="margin-top:86px;max-width:560px">
  <div class="uc-main" style="text-align:center">
    <h3>订单收银台</h3>
    <p style="color:var(--sub);font-size:13px"><?= e($order['title']) ?> · 应付 <b style="color:var(--red2)">¥<?= number_format((float)$order['amount'], 2) ?></b></p>
    <div class="qrbox">
      <div id="qrTip" style="color:var(--sub);font-size:13px">支付完成后本页将自动跳转,请勿关闭</div>
      <div id="qrBox" style="background:#fff;padding:14px;border-radius:12px;width:220px;height:220px;margin:0 auto"></div>
      <?php if ($order['pay_type'] === 'usdt'): ?>
      <div style="color:var(--sub);font-size:13px" id="usdtInfo">正在生成USDT支付信息…</div>
      <div class="addr" id="usdtAddr" onclick="copyAddr()">点击复制收款地址</div>
      <?php endif; ?>
      <div id="state" style="font-size:14px;color:var(--gold)">⏳ 等待支付中…</div>
      <a class="btn-line" href="/user/center?tab=orders">查看订单列表</a>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
var ono=<?= json_encode((string)$order['order_no'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
var payType=<?= json_encode((string)$order['pay_type'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
var done=false;
<?php if ($order['pay_type'] === 'usdt'): ?>
fetch('/index.php?s=/pay/check&ono='+ono).then(function(r){return r.json()}).then(function(){});
function drawUsdt(addr,amount){
  document.getElementById('usdtAddr').textContent=addr;
  document.getElementById('usdtAddr').dataset.a=addr;
  document.getElementById('usdtInfo').textContent='请向该TRC20地址精确转入 '+amount+' USDT(金额含校验尾数,请勿修改)';
  new QRCode(document.getElementById('qrBox'),{text:'tron:'+addr+'?amount='+amount,width:190,height:190});
}
// 触发后端计算usdt金额
fetch('/index.php?s=/pay/usdtinfo&ono='+ono).then(function(r){return r.json()}).then(function(j){
  if(j.code===1)drawUsdt(j.data.address,j.data.amount);
  else document.getElementById('usdtInfo').textContent=j.msg;
});
function copyAddr(){
  var a=document.getElementById('usdtAddr').dataset.a||'';
  if(navigator.clipboard)navigator.clipboard.writeText(a).then(function(){kyToast('地址已复制')});
}
<?php else: ?>
var qrData=<?= json_encode((string)($qrData ?? ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
if(qrData){ new QRCode(document.getElementById('qrBox'),{text:qrData,width:190,height:190}); }
else{
  document.getElementById('qrBox').innerHTML='<div style="color:#888;padding-top:90px;font-size:13px">请在支付页面完成付款</div>';
}
<?php endif; ?>
var timer=setInterval(function(){
  fetch('/index.php?s=/pay/check&ono='+ono).then(function(r){return r.json()}).then(function(j){
    if(j.code===1&&j.data.status===1&&!done){
      done=true;clearInterval(timer);
      document.getElementById('state').textContent='✅ 支付成功,正在跳转…';
      setTimeout(function(){location.href='/pay/result?ono='+ono},900);
    }
  }).catch(function(){});
},4000);
</script>
<?php include theme_path('layout/footer.php'); ?>
