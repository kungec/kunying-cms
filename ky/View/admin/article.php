<?php include __DIR__.'/_header.php'; $pageTitle='文章公告'; ?>
<div class="card">
  <a class="btn sm" href="/admin.php?s=/content/articleform">+ 发布文章</a>
  <div class="tb-wrap"><table class="tb" style="margin-top:12px">
    <tr><th>ID</th><th>标题</th><th>状态</th><th>时间</th><th>操作</th></tr>
    <?php foreach ($list as $a): ?>
    <tr>
      <td><?= (int)$a['id'] ?></td>
      <td><?= e($a['title']) ?></td>
      <td><span class="tag <?= $a['status'] ? 'g' : 'gr' ?>"><?= $a['status'] ? '发布' : '隐藏' ?></span></td>
      <td><?= date('Y-m-d', (int)$a['addtime']) ?></td>
      <td><a class="btn plain sm" href="/admin.php?s=/content/articleform&id=<?= (int)$a['id'] ?>">编辑</a>
          <button class="btn plain sm" onclick="delA(<?= (int)$a['id'] ?>)">删除</button></td>
    </tr>
    <?php endforeach; ?>
  </table></div>
  <?= $pageHtml ?>
</div>
<script>
async function delA(id){if(!confirmDel())return;var d=new FormData();d.append('id',id);var j=await api('/admin.php?s=/content/articledel',d);j.code===1?location.reload():toast(j.msg,false)}
</script>
<?php include __DIR__.'/_footer.php'; ?>
