<?php include __DIR__.'/_header.php'; $pageTitle='订单管理'; $tabs=['-1'=>'全部','0'=>'待支付','1'=>'已支付','2'=>'已取消']; ?>
<div class="card">
  <div class="tabs">
    <?php foreach ($tabs as $k => $t): ?><a class="<?= (string)$status === (string)$k ? 'on' : '' ?>" href="/admin.php?s=/user/order&status=<?= $k ?>"><?= $t ?></a><?php endforeach; ?>
    <div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap;align-items:center">
      <b style="font-size:13px">批量清理:</b>
      <button class="btn plain sm" onclick="orderClean(0,0)">🧹 清理全部待支付订单</button>
      <button class="btn plain sm" onclick="orderClean(0,7)">🧹 清理7天前的待支付</button>
      <button class="btn plain sm" onclick="orderClean(0,30)">🧹 清理30天前的待支付</button>
      <button class="btn plain sm" onclick="orderClean(2,0)">🧹 清理全部已取消订单</button>
    </div>
  </div>
  <div class="tb-wrap"><table class="tb">
    <tr><th>订单号</th><th>会员</th><th>套餐</th><th>金额</th><th>支付方式</th><th>状态</th><th>第三方单号</th><th>时间</th><th>操作</th></tr>
    <?php foreach ($list as $o): ?>
    <tr>
      <td style="font-size:12px"><?= e($o['order_no']) ?></td>
      <td><?= e($o['uname'] ?: $o['email']) ?></td>
      <td><?= e($o['title']) ?></td>
      <td>¥<?= number_format((float)$o['amount'], 2) ?></td>
      <td><span class="tag b"><?= e($o['pay_type']) ?></span></td>
      <td><span class="tag <?= $o['status'] == 1 ? 'g' : ($o['status'] == 0 ? 'r' : 'gr') ?>"><?= ['待支付', '已支付', '已取消'][$o['status']] ?? '-' ?></span></td>
      <td style="font-size:12px;color:var(--sub)"><?= e($o['trade_no']) ?></td>
      <td style="font-size:12px;color:var(--sub)"><?= date('m-d H:i', (int)$o['created']) ?></td>
      <td><button class="btn plain sm" onclick="delO(<?= (int)$o['id'] ?>)">删除</button></td>
    </tr>
    <?php endforeach; ?>
  </table></div>
  <?= $pageHtml ?>
</div>
<script>
async function delO(id){if(!confirmDel())return;var d=new FormData();d.append('id',id);var j=await api('/admin.php?s=/user/orderdel',d);j.code===1?location.reload():toast(j.msg,false)}
</script>
<script>
function orderClean(status, days){
  var names = {0:'待支付', 2:'已取消'};
  var tip = days > 0 ? '确定清理 ' + days + ' 天前的' + (names[status]||'') + '订单?' : '确定清理全部' + (names[status]||'') + '订单?';
  if (!confirm(tip + '\n已支付订单不受影响。')) return;
  var d = new FormData(); d.append('status', status); d.append('days', days);
  api('/admin.php?s=/user/orderclean', d).then(function(j){
    j.code === 1 ? location.reload() : toast(j.msg || '失败', false);
  });
}
</script>
<?php include __DIR__.'/_footer.php'; ?>
