<?php include __DIR__.'/_header.php'; $pageTitle='安全日志'; ?>
<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <span class="hint">记录后台全部敏感操作,供安全审计使用。</span>
    <button class="btn plain sm" onclick="clearLog()">🧹 清空日志</button>
  </div>
  <div class="tb-wrap" style="margin-top:10px"><table class="tb">
    <tr><th>时间</th><th>管理员</th><th>操作</th><th>IP</th></tr>
    <?php foreach ($list as $l): ?>
    <tr>
      <td style="font-size:12px;color:var(--sub)"><?= date('Y-m-d H:i:s', (int)$l['created']) ?></td>
      <td><?= e($l['username'] ?? $l['admin_id']) ?></td>
      <td><?= e($l['action']) ?></td>
      <td style="font-size:12px"><?= e($l['ip']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table></div>
  <?= $pageHtml ?>
</div>
<script>
async function clearLog(){
  if(!confirmDel('确定清空全部安全日志?'))return;
  var d=new FormData();d.append('_csrf','<?= e(Security::csrfToken()) ?>');
  var j=await api('/admin.php?s=/system/clearlog',d);
  toast(j.msg||'完成',j.code===1);
  if(j.code===1)setTimeout(()=>location.reload(),800);
}
</script>
<?php include __DIR__.'/_footer.php'; ?>
