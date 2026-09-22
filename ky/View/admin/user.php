<?php include __DIR__.'/_header.php'; $pageTitle='会员列表'; ?>
<div class="card">
  <div class="searchbar">
    <form method="get" action="/admin.php" style="display:flex;gap:8px">
      <input type="hidden" name="s" value="/user/user">
      <input type="text" name="wd" value="<?= e($wd) ?>" placeholder="邮箱/昵称">
      <button class="btn sm" type="submit">搜索</button>
    <button class="btn sm" onclick="showAdd()">+ 添加会员</button>
    </form>
    <button class="btn plain sm" onclick="unlockAll()">解除登录锁定</button>
  </div>
  <div style="margin-bottom:10px"><button class="btn sm" style="background:#e5322d" onclick="userdels()">🗑 删除选中</button></div>
  <div class="tb-wrap"><table class="tb">
    <tr><th style="width:34px"><input type="checkbox" onclick="document.querySelectorAll('.ck').forEach(c=>c.checked=this.checked)"></th><th>ID</th><th>邮箱</th><th>昵称</th><th>积分</th><th>VIP到期</th><th>状态</th><th>注册时间/IP</th><th>操作</th></tr>
    <?php foreach ($list as $u): ?>
    <tr>
      <td><input type="checkbox" class="ck" value="<?= (int)$u['id'] ?>"></td>
      <td><?= (int)$u['id'] ?></td>
      <td><?= e($u['email']) ?></td>
      <td><?= e($u['name']) ?></td>
      <td><?= number_format((int)$u['points']) ?></td>
      <td><?= $u['vip_expire'] > time() ? '<span class="tag r">' . date('Y-m-d', (int)$u['vip_expire']) . '</span>' : '<span class="tag gr">非VIP</span>' ?></td>
      <td><span class="tag <?= $u['status'] ? 'g' : 'r' ?>"><?= $u['status'] ? '正常' : '禁用' ?></span></td>
      <td style="font-size:12px;color:var(--sub)"><?= date('m-d H:i', (int)$u['reg_time']) ?><br><?= e($u['reg_ip']) ?></td>
      <td><button class="btn plain sm" onclick='editU(<?= json_encode($u, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) ?>)'>编辑</button>
          <button class="btn plain sm" onclick="delU(<?= (int)$u['id'] ?>)">删除</button></td>
    </tr>
    <?php endforeach; ?>
  </table></div>
  <?= $pageHtml ?>
</div>
<div class="modal" id="md"><div class="mbox">
  <h3>编辑会员</h3>
  <form onsubmit="return saveU(event)">
    <input type="hidden" name="id" id="f_id" value="0">
    <div class="fi"><label>昵称</label><input type="text" name="name" id="f_name"></div>
    <div class="row">
      <div class="fi"><label>积分</label><input type="number" name="points" id="f_points" value="0"></div>
      <div class="fi"><label>VIP到期日</label><input type="date" name="vip_expire" id="f_vip"></div>
    </div>
    <div class="fi"><label>重置密码(留空不修改)</label><input type="text" name="password" id="f_pwd" placeholder="至少6位"></div>
    <div class="fi"><label>状态</label><select name="status" id="f_status"><option value="1">正常</option><option value="0">禁用</option></select></div>
    <div style="display:flex;gap:10px"><button class="btn" type="submit">保存</button><button class="btn plain" type="button" onclick="md.classList.remove('open')">取消</button></div>
  </form>
</div></div>
<script>
function editU(u){
  f_id.value=u.id;f_name.value=u.name;f_points.value=u.points;
  f_vip.value=u.vip_expire>0?new Date(u.vip_expire*1000).toISOString().slice(0,10):'';f_status.value=u.status;f_pwd.value='';
  md.classList.add('open');
}
async function saveU(ev){ev.preventDefault();var j=await api('/admin.php?s=/user/usersave',new FormData(ev.target));if(j.code===1){toast('已保存');md.classList.remove('open');setTimeout(()=>location.reload(),500)}else toast(j.msg,false);return false}
async function delU(id){if(!confirmDel('确定删除该会员?其订单记录将保留'))return;var d=new FormData();d.append('id',id);var j=await api('/admin.php?s=/user/userdel',d);j.code===1?location.reload():toast(j.msg,false)}
async function unlockAll(){var j=await api('/admin.php?s=/user/unlock',new FormData());toast(j.msg||'完成',j.code===1)}
</script>
<script>
async function userdels(){
  var ids=[];document.querySelectorAll('.ck:checked').forEach(function(c){ids.push(c.value)});
  if(!ids.length){toast('请先勾选会员',false);return}
  if(!confirmDel('确定删除选中的 '+ids.length+' 个会员?其评论/收藏/订单将一并删除'))return;
  var d=new FormData();d.append('ids',ids.join(','));
  var j=await api('/admin.php?s=/user/userdels',d);
  j.code===1?location.reload():toast(j.msg,false);
}
</script>
<div class="modal" id="amd"><div class="mbox">
  <h3>添加会员</h3>
  <form onsubmit="return doAdd(event)">
    <div class="fi"><label>邮箱 *</label><input type="email" name="email" id="a_email" required placeholder="user@example.com"></div>
    <div class="fi"><label>昵称</label><input type="text" name="name" id="a_name" placeholder="留空自动生成"></div>
    <div class="fi"><label>初始密码 *(至少6位)</label><input type="text" name="password" id="a_pwd" required placeholder="6位以上"></div>
    <div class="row">
      <div class="fi"><label>赠送积分</label><input type="number" name="points" id="a_points" value="0" min="0"></div>
      <div class="fi"><label>VIP天数(0=不开通)</label><input type="number" name="vip_days" id="a_vip" value="0" min="0"></div>
    </div>
    <p class="hint">添加后邮箱即已验证,会员可直接登录。</p>
    <div style="display:flex;gap:10px"><button class="btn" type="submit">保存</button><button class="btn plain" type="button" onclick="document.getElementById('amd').classList.remove('open')">取消</button></div>
  </form>
</div></div>
<script>
function showAdd(){ document.getElementById('amd').classList.add('open'); }
async function doAdd(ev){
  ev.preventDefault();
  var j = await api('/admin.php?s=/user/useradd', new FormData(ev.target));
  if (j.code === 1) { toast(j.msg || '添加成功', true); setTimeout(function(){ location.reload(); }, 700); }
  else toast(j.msg || '失败', false);
  return false;
}
</script>
<?php include __DIR__.'/_footer.php'; ?>
