<?php include __DIR__.'/_header.php'; $pageTitle='幻灯管理'; ?>
<div class="card">
  <button class="btn sm" onclick="showForm()">+ 添加幻灯</button>
  <div class="tb-wrap"><table class="tb" style="margin-top:12px">
    <tr><th>名称</th><th>图片</th><th>链接</th><th>显示位置</th><th>排序</th><th>状态</th><th>操作</th></tr>
    <?php foreach ($list as $s): ?>
    <tr>
      <td><?= e($s['name']) ?></td>
      <td><img src="<?= e(pic_url($s['pic'])) ?>" style="height:38px;border-radius:5px"></td>
      <td style="font-size:12px;color:var(--sub);max-width:260px;word-break:break-all"><?= e($s['url']) ?></td>
      <td><?= (int)$s['sort'] ?></td>
      <td><span class="tag <?= $s['status'] ? 'g' : 'gr' ?>"><?= $s['status'] ? '显示' : '隐藏' ?></span></td>
      <td><button class="btn plain sm" onclick='showForm(<?= json_encode($s, JSON_UNESCAPED_UNICODE) ?>)'>编辑</button>
          <button class="btn plain sm" onclick="delS(<?= (int)$s['id'] ?>)">删除</button></td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($list)): ?><tr><td colspan="7" style="color:var(--sub)">顶部轮播为空时将自动展示最新影片海报;「电影模式大图」供电影/爱奇艺风格的首页大图幻灯使用,为空时自动展示热播影片</td></tr><?php endif; ?>
  </table></div>
</div>
<div class="modal" id="md"><div class="mbox">
  <h3 id="mtitle">添加幻灯</h3>
  <form onsubmit="return saveS(event)">
    <input type="hidden" name="id" id="f_id" value="0">
    <div class="fi"><label>名称 *</label><input type="text" name="name" id="f_name" required></div>
    <div class="fi"><label>图片地址 *(建议1920x640)</label><input type="text" name="pic" id="f_pic" required></div>
    <div class="fi"><label>跳转链接</label><input type="text" name="url" id="f_url"></div>
    <div class="fi"><label>显示位置</label><select name="pos" id="f_pos"><option value="top">顶部轮播(CMS/瀑布流模式首页)</option><option value="movie">电影模式大图幻灯(海报墙顶部)</option></select></div>
    <div class="row">
      <div class="fi"><label>排序</label><input type="number" name="sort" id="f_sort" value="0"></div>
      <div class="fi"><label>状态</label><select name="status" id="f_status"><option value="1">显示</option><option value="0">隐藏</option></select></div>
    </div>
    <div style="display:flex;gap:10px"><button class="btn" type="submit">保存</button><button class="btn plain" type="button" onclick="md.classList.remove('open')">取消</button></div>
  </form>
</div></div>
<script>
function showForm(s){
  if(s){f_id.value=s.id;f_name.value=s.name;f_pic.value=s.pic;f_url.value=s.url;f_sort.value=s.sort;f_status.value=s.status;f_pos.value=s.pos||'top';mtitle.textContent='编辑幻灯'}
  else{f_id.value=0;f_name.value='';f_pic.value='';f_url.value='';f_sort.value=0;f_status.value=1;f_pos.value='top';mtitle.textContent='添加幻灯'}
  md.classList.add('open');
}
async function saveS(ev){ev.preventDefault();var j=await api('/admin.php?s=/content/slidesave',new FormData(ev.target));j.code===1?location.reload():toast(j.msg,false);return false}
async function delS(id){if(!confirmDel())return;var d=new FormData();d.append('id',id);var j=await api('/admin.php?s=/content/slidedel',d);j.code===1?location.reload():toast(j.msg,false)}
</script>
<?php include __DIR__.'/_footer.php'; ?>
