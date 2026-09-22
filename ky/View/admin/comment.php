<?php include __DIR__.'/_header.php'; $pageTitle='评论管理'; ?>
<div class="card">
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:12px">
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a class="btn plain sm" style="text-decoration:none" href="/admin.php?s=/user/comment">全部</a>
    <a class="btn plain sm" style="text-decoration:none" href="/admin.php?s=/user/comment&audit=0">待审核</a>
    <a class="btn plain sm" style="text-decoration:none" href="/admin.php?s=/user/comment&audit=1">已显示</a>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <button class="btn plain sm" onclick="cmtClean(0)">🧹 清理待审核评论</button>
    <button class="btn plain sm" style="background:#e5322d" onclick="cmtClean(1)">🗑 清空全部评论</button>
  </div>
</div>
  <div class="tb-wrap"><table class="tb">
    <tr><th>ID</th><th>用户</th><th>影片</th><th>内容</th><th>状态</th><th>时间</th><th>操作</th></tr>
    <?php foreach ($list as $c): ?>
    <tr>
      <td><?= (int)$c['id'] ?></td>
      <td><?= e($c['uname'] ?? '-') ?></td>
      <td><a href="/index.php?s=/vod/detail&id=<?= (int)$c['vod_id'] ?>" target="_blank" style="color:var(--blue)"><?= e($c['vname'] ?? $c['vod_id']) ?></a></td>
      <td style="max-width:340px"><?= e($c['content']) ?></td>
      <td><span class="tag <?= $c['status'] == 1 ? 'g' : ($c['status'] == 0 ? 'b' : 'gr') ?>"><?= [1 => '已显示', 0 => '待审核'][$c['status']] ?? '隐藏' ?></span></td>
      <td style="font-size:12px;color:var(--sub)"><?= date('m-d H:i', (int)$c['created']) ?></td>
      <td>
        <?php if ($c['status'] != 1): ?><button class="btn sm" onclick="setStatus(<?= (int)$c['id'] ?>,1)">✓ 通过</button><?php else: ?><button class="btn plain sm" onclick="setStatus(<?= (int)$c['id'] ?>,0)">隐藏</button><?php endif; ?>
        <button class="btn plain sm" onclick="delC(<?= (int)$c['id'] ?>)">删除</button>
      </td>
    </tr>
    <?php endforeach; ?>
  </table></div>
  <?= $pageHtml ?>
</div>
<script>
async function setStatus(id,s){var d=new FormData();d.append('id',id);d.append('status',s);var j=await api('/admin.php?s=/user/commentstatus',d);j.code===1?location.reload():toast(j.msg,false)}
async function delC(id){if(!confirmDel())return;var d=new FormData();d.append('id',id);var j=await api('/admin.php?s=/user/commentdel',d);j.code===1?location.reload():toast(j.msg,false)}
</script>
<script>
function cmtClean(mode){
  var tip = mode == 1 ? '确定清空全部评论?此操作不可恢复!' : '确定清理全部待审核评论?';
  if (!confirm(tip)) return;
  var d = new FormData(); d.append('mode', mode);
  api('/admin.php?s=/user/commentclean', d).then(function(j){
    j.code === 1 ? location.reload() : toast(j.msg || '失败', false);
  });
}
</script>
<?php include __DIR__.'/_footer.php'; ?>
