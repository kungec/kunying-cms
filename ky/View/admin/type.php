<?php include __DIR__.'/_header.php'; $pageTitle='分类管理'; ?>
<div class="card">
  <button class="btn sm" onclick="showForm()">+ 添加分类</button>
  <div class="tb-wrap"><table class="tb" style="margin-top:12px">
    <tr><th>ID</th><th>名称</th><th>影片数</th><th>排序</th><th>状态</th><th>首页显示</th><th>操作</th></tr>
    <?php foreach ($list as $t): ?>
    <tr>
      <td><?= (int)$t['id'] ?></td>
      <td><?= e($t['name']) ?></td>
      <td><?= (int)$t['c'] ?></td>
      <td><?= (int)$t['sort'] ?></td>
      <td><span class="tag <?= $t['status'] ? 'g' : 'gr' ?>"><?= $t['status'] ? '显示' : '隐藏' ?></span></td>
      <td><span class="tag <?= !empty($t['show_home']) ? 'g' : 'gr' ?>"><?= !empty($t['show_home']) ? '是' : '否' ?></span></td>
      <td><button class="btn plain sm" onclick='showForm(<?= json_encode($t, JSON_UNESCAPED_UNICODE) ?>)'>编辑</button>
          <button class="btn plain sm" onclick="delType(<?= (int)$t['id'] ?>)">删除</button></td>
    </tr>
    <?php endforeach; ?>
  </table></div>
</div>
<div class="modal" id="md">
  <div class="mbox">
    <h3 id="mtitle">添加分类</h3>
    <form onsubmit="return saveType(event)">
      <input type="hidden" name="id" id="f_id" value="0">
      <div class="fi"><label>名称 *</label><input type="text" name="name" id="f_name" required></div>
      <div class="row">
        <div class="fi"><label>排序(越小越前)</label><input type="number" name="sort" id="f_sort" value="0"></div>
        <div class="fi"><label>状态</label><select name="status" id="f_status"><option value="1">显示</option><option value="0">隐藏</option></select></div>
      </div>
      <div class="fi"><label>首页显示(勾选后在网站首页显示该分类的视频模块)</label><select name="show_home" id="f_showhome"><option value="0">不显示</option><option value="1">显示</option></select></div>
      <div class="fi"><label>分类图标(emoji,如 🎬 留空则自动匹配)</label><input type="text" name="icon" id="f_icon" maxlength="8" placeholder="🎬"></div>
      <div style="display:flex;gap:10px"><button class="btn" type="submit">保存</button><button class="btn plain" type="button" onclick="hideForm()">取消</button></div>
    </form>
  </div>
</div>
<script>
function showForm(t){
  if(t){f_id.value=t.id;f_name.value=t.name;f_sort.value=t.sort;f_status.value=t.status;f_showhome.value=t.show_home?1:0;mtitle.textContent='编辑分类'}
  else{f_id.value=0;f_name.value='';f_sort.value=0;f_status.value=1;f_showhome.value=0;f_icon.value='';mtitle.textContent='添加分类'}
  md.classList.add('open');
}
function hideForm(){md.classList.remove('open')}
async function saveType(ev){
  ev.preventDefault();
  var j=await api('/admin.php?s=/content/typesave',new FormData(ev.target));
  if(j.code===1){location.reload()}else{toast(j.msg,false)}
  return false;
}
async function delType(id){
  if(!confirmDel())return;
  var d=new FormData();d.append('id',id);
  var j=await api('/admin.php?s=/content/typedel',d);
  j.code===1?location.reload():toast(j.msg,false);
}
</script>
<?php include __DIR__.'/_footer.php'; ?>
