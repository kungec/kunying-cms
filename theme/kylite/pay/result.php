<?php
/** kylite 支付结果页 */
$pageTitle = '支付结果';
$searchWd = '';
include theme_path('layout/header.php');
$paid = $order && (int)$order['status'] === 1;
?>
<div class="wrap" style="margin-top:14px;max-width:560px">
  <div class="uc-main" style="text-align:center;padding:50px 22px">
    <div style="font-size:52px"><?= $paid ? '✅' : '⏳' ?></div>
    <h3 style="margin:12px 0 6px"><?= $paid ? '支付成功!' : '订单尚未支付' ?></h3>
    <?php if ($order): ?>
    <p style="color:var(--sub);font-size:13px"><?= e($order['title']) ?> · ¥<?= number_format((float)$order['amount'], 2) ?></p>
    <?php endif; ?>
    <div style="display:flex;gap:12px;justify-content:center;margin-top:22px;flex-wrap:wrap">
      <a class="btn-main" style="width:auto;padding:10px 28px" href="/user/center?tab=orders">查看订单</a>
      <a class="btn-line" style="width:auto;padding:10px 28px;margin:0" href="/">返回首页</a>
    </div>
  </div>
</div>
<?php include theme_path('layout/footer.php'); ?>
