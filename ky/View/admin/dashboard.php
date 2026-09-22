<?php include __DIR__.'/_header.php'; $pageTitle='仪表盘'; ?>
<div class="stat-grid">
  <div class="stat red"><div class="t">影片总数</div><div class="n"><?= number_format($stats['vod']) ?></div><div class="t">今日新增 <?= $stats['today_vod'] ?></div></div>
  <div class="stat"><div class="t">会员总数</div><div class="n"><?= number_format($stats['user']) ?></div><div class="t">今日注册 <?= $stats['today_user'] ?></div></div>
  <div class="stat"><div class="t">成交订单</div><div class="n"><?= number_format($stats['order_paid']) ?></div><div class="t">累计 ¥<?= number_format($stats['order_money'], 2) ?></div></div>
  <div class="stat red"><div class="t">今日收入</div><div class="n">¥<?= number_format($stats['today_money'], 2) ?></div><div class="t">评论 <?= number_format($stats['comment']) ?> 条</div></div>
</div>

<?php if (!empty($notices)): ?>
<div class="notice" style="margin-top:16px">
  <b>📢 授权站公告</b>
  <div style="margin-top:8px;display:grid;gap:6px">
    <?php foreach ($notices as $n): ?>
    <div><span class="tag <?= $n['level'] > 0 ? 'r' : 'b' ?>"><?= $n['level'] > 0 ? '重要' : '通知' ?></span> <?= e($n['title']) ?> <span style="color:var(--sub);font-size:12px"><?= date('m-d', (int)$n['time']) ?></span>
      <?php if ($n['content']): ?><div style="color:#6b5320;margin-top:2px;white-space:pre-wrap"><?= e($n['content']) ?></div><?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="card" style="margin-top:16px">
  <b>近7日数据</b>
  <table class="tb" style="margin-top:10px">
    <tr><th>日期</th><?php foreach ($week as $d): ?><td style="text-align:center"><?= e($d['date']) ?></td><?php endforeach; ?></tr>
    <tr><th>新增影片</th><?php foreach ($week as $d): ?><td style="text-align:center"><?= $d['vod'] ?></td><?php endforeach; ?></tr>
    <tr><th>当日收入</th><?php foreach ($week as $d): ?><td style="text-align:center">¥<?= number_format($d['money'], 2) ?></td><?php endforeach; ?></tr>
  </table>
</div>

<div class="card">
  <b>最新订单</b>
  <div class="tb-wrap"><table class="tb" style="margin-top:10px">
    <tr><th>订单号</th><th>会员</th><th>套餐</th><th>金额</th><th>方式</th><th>状态</th><th>时间</th></tr>
    <?php if (empty($latestOrders)): ?><tr><td colspan="7" style="color:var(--sub)">暂无订单</td></tr><?php endif; ?>
    <?php foreach ($latestOrders as $o): ?>
    <tr>
      <td><?= e($o['order_no']) ?></td>
      <td><?= e($o['name'] ?: $o['email']) ?></td>
      <td><?= e($o['title']) ?></td>
      <td>¥<?= number_format((float)$o['amount'], 2) ?></td>
      <td><?= e($o['pay_type']) ?></td>
      <td><span class="tag <?= $o['status'] == 1 ? 'g' : 'gr' ?>"><?= $o['status'] == 1 ? '已支付' : '待支付' ?></span></td>
      <td><?= $o['paid_time'] > 0 ? date('m-d H:i', (int)$o['paid_time']) : date('m-d H:i', (int)$o['created']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table></div>
</div>
<?php include __DIR__.'/_footer.php'; ?>
