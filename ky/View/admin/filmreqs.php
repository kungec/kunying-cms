<?php include __DIR__.'/_header.php'; $pageTitle='求片管理'; ?>
<div class="card">
  <b>用户求片</b>
  <p class="hint" style="margin:6px 0 12px">用户提交的影片需求,可作为内容采购参考。标记完成后将从列表移至已完成。</p>
  <div style="overflow-x:auto"><table class="ut">
    <tr><th>时间</th><th>用户</th><th>影片</th><th>备注</th><th>状态</th><th>操作</th></tr>
    <?php foreach (($list ?? []) as $r): ?>
    <tr>
      <td style="font-size:12px"><?= date('m-d H:i', (int)$r['created']) ?></td>
      <td><?= e($r['uname'] ?? ('用户#'.$r['user_id'])) ?></td>
      <td><?= e($r['title']) ?></td>
      <td style="font-size:12px;color:var(--sub)"><?= e(mb_substr((string)$r['note'], 0, 40)) ?></td>
      <td><?= (int)$r['status'] === 1 ? '<span style="color:#1f9d55">已上架</span>' : '<span style="color:#e5a03c">待处理</span>' ?></td>
      <td>
        <?php if ((int)$r['status'] === 0): ?>
        <button class="btn blue" style="padding:4px 10px;font-size:12px" onclick="markDone(<?= (int)$r['id'] ?>)">标记完成</button>
        <?php endif; ?>
        <button class="btn" style="padding:4px 10px;font-size:12px" onclick="delReq(<?= (int)$r['id'] ?>)">删除</button>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($list)): ?><tr><td colspan="6" style="color:var(--sub)">暂无求片</td></tr><?php endif; ?>
  </table></div>
</div>
<script>
async function markDone(id){
  var d = new FormData(); d.append('id', id); d.append('_csrf', window.CSRF);
  var j = await api('/admin.php?s=/content/filmreqdone', d);
  if (j.code === 1) location.reload(); else toast(j.msg, false);
}
async function delReq(id){
  if (!confirmDel('确定删除该求片?')) return;
  var d = new FormData(); d.append('id', id); d.append('_csrf', window.CSRF);
  var j = await api('/admin.php?s=/content/filmreqdel', d);
  if (j.code === 1) location.reload(); else toast(j.msg, false);
}
</script>
