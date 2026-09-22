<?php include __DIR__.'/_header.php'; $pageTitle=$article?'编辑文章':'发布文章'; $a=$article; ?>
<div class="card">
  <form class="form" onsubmit="return save(event)" style="max-width:100%">
    <input type="hidden" name="id" value="<?= (int)($a['id'] ?? 0) ?>">
    <div class="fi"><label>标题 *</label><input type="text" name="title" value="<?= e($a['title'] ?? '') ?>" required></div>
    <div class="fi"><label>内容(支持基本HTML标签)</label><textarea name="content" style="min-height:300px"><?= e($a['content'] ?? '') ?></textarea></div>
    <div class="fi"><label>状态</label><select name="status"><option value="1" <?= ($a['status'] ?? 1) == 1 ? 'selected' : '' ?>>发布</option><option value="0">隐藏</option></select></div>
    <button class="btn" type="submit" id="go">保存</button>
    <a class="btn plain" href="/admin.php?s=/content/article">返回</a>
  </form>
</div>
<script>
async function save(ev){ev.preventDefault();var j=await api('/admin.php?s=/content/articlesave',new FormData(ev.target));if(j.code===1){toast('保存成功');setTimeout(()=>location.href='/admin.php?s=/content/article',600)}else toast(j.msg,false);return false}
</script>
<?php include __DIR__.'/_footer.php'; ?>
