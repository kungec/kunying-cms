<?php include __DIR__.'/_header.php'; $pageTitle='播放器管理'; ?>
<div class="card">
  <button class="btn sm" onclick="showForm()">+ 添加播放器</button>
  <div class="tb-wrap"><table class="tb" style="margin-top:12px">
    <tr><th>标识</th><th>名称</th><th>解析地址</th><th>状态</th><th>操作</th></tr>
    <?php foreach ($list as $p): ?>
    <tr>
      <td><b><?= e($p['code']) ?></b></td>
      <td><?= e($p['name']) ?></td>
      <td style="font-size:12px;max-width:380px;word-break:break-all;"><?= $p['parse'] ? e($p['parse']) : '<span class="tag g">内置直链(m3u8/mp4)</span>' ?></td>
      <td><span class="tag <?= $p['status'] ? 'g' : 'gr' ?>"><?= $p['status'] ? '启用' : '停用' ?></span></td>
      <td>
        <button class="btn plain sm" onclick='showForm(<?= json_encode($p, JSON_UNESCAPED_UNICODE) ?>)'>编辑</button>
        <button class="btn plain sm" onclick="delP(<?= (int)$p['id'] ?>)">删除</button>
      </td>
    </tr>
    <?php endforeach; ?>
  </table></div>
  <p class="hint">播放来源标识对应影片数据中的 play_from;解析地址留空则使用内置播放器直连播放(m3u8自动走hls);填写解析地址时须包含 <b>{url}</b> 占位符,影片将以iframe方式调用解析。</p>
</div>
<div class="modal" id="md">
  <div class="mbox">
    <h3 id="mtitle">添加播放器</h3>
    <form onsubmit="return saveP(event)">
      <input type="hidden" name="id" id="f_id" value="0">
      <div class="row">
        <div class="fi"><label>标识 * (对应play_from)</label><input type="text" name="code" id="f_code" required placeholder="如:jsm3u8"></div>
        <div class="fi"><label>名称 *</label><input type="text" name="name" id="f_name" required></div>
      </div>
      <div class="fi"><label>解析地址(留空=直连播放)</label><input type="text" name="parse" id="f_parse" placeholder="https://jx.example.com/?url={url}"></div>
      <div class="fi"><label>状态</label><select name="status" id="f_status"><option value="1">启用</option><option value="0">停用</option></select></div>
      <div style="display:flex;gap:10px"><button class="btn" type="submit">保存</button><button class="btn plain" type="button" onclick="md.classList.remove('open')">取消</button></div>
    </form>
  </div>
</div>
<script>
function showForm(p){
  if(p){f_id.value=p.id;f_code.value=p.code;f_name.value=p.name;f_parse.value=p.parse;f_status.value=p.status;mtitle.textContent='编辑播放器'}
  else{f_id.value=0;f_code.value='';f_name.value='';f_parse.value='';f_status.value=1;mtitle.textContent='添加播放器'}
  md.classList.add('open');
}
async function saveP(ev){
  ev.preventDefault();
  var j=await api('/admin.php?s=/content/playersave',new FormData(ev.target));
  if(j.code===1){location.reload()}else{toast(j.msg,false)}
  return false;
}
async function delP(id){
  if(!confirmDel())return;
  var d=new FormData();d.append('id',id);
  var j=await api('/admin.php?s=/content/playerdel',d);
  j.code===1?location.reload():toast(j.msg,false);
}
</script>
<?php include __DIR__.'/_footer.php'; ?>
