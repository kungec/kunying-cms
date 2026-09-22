<?php include __DIR__.'/_header.php'; $pageTitle='友情链接'; ?>
<div class="card">
  <button class="btn sm" onclick="showForm()">+ 添加友链</button>
  <div class="tb-wrap"><table class="tb" style="margin-top:12px">
    <tr><th>名称</th><th>网址</th><th>排序</th><th>状态</th><th>操作</th></tr>
    <?php foreach ($list as $s): ?>
    <tr>
      <td><?= e($s['name']) ?></td>
      <td style="font-size:12px;color:var(--sub)"><?= e($s['url']) ?></td>
      <td><?= (int)$s['sort'] ?></td>
      <td><span class="tag <?= $s['status'] ? 'g' : 'gr' ?>"><?= $s['status'] ? '显示' : '隐藏' ?></span></td>
      <td><button class="btn plain sm" onclick='showForm(<?= json_encode($s, JSON_UNESCAPED_UNICODE) ?>)'>编辑</button>
          <button class="btn plain sm" onclick="delS(<?= (int)$s['id'] ?>)">删除</button></td>
    </tr>
    <?php endforeach; ?>
  </table></div>
</div>
<div class="modal" id="md"><div class="mbox">
  <h3 id="mtitle">添加友链</h3>
  <form onsubmit="return saveS(event)">
    <input type="hidden" name="id" id="f_id" value="0">
    <div class="fi"><label>名称 *</label><input type="text" name="name" id="f_name" required></div>
    <div class="fi"><label>网址 *</label><input type="text" name="url" id="f_url" required placeholder="https://"></div>
    <div class="row">
      <div class="fi"><label>排序</label><input type="number" name="sort" id="f_sort" value="0"></div>
      <div class="fi"><label>状态</label><select name="status" id="f_status"><option value="1">显示</option><option value="0">隐藏</option></select></div>
    </div>
    <div style="display:flex;gap:10px"><button class="btn" type="submit">保存</button><button class="btn plain" type="button" onclick="md.classList.remove('open')">取消</button></div>
  </form>
</div></div>
<script>
function showForm(s){
  if(s){f_id.value=s.id;f_name.value=s.name;f_url.value=s.url;f_sort.value=s.sort;f_status.value=s.status;mtitle.textContent='编辑友链'}
  else{f_id.value=0;f_name.value='';f_url.value='';f_sort.value=0;f_status.value=1;mtitle.textContent='添加友链'}
  md.classList.add('open');
}
async function saveS(ev){ev.preventDefault();var j=await api('/admin.php?s=/content/linksave',new FormData(ev.target));j.code===1?location.reload():toast(j.msg,false);return false}
async function delS(id){if(!confirmDel())return;var d=new FormData();d.append('id',id);var j=await api('/admin.php?s=/content/linkdel',d);j.code===1?location.reload():toast(j.msg,false)}
</script>
<?php include __DIR__.'/_footer.php'; ?>
