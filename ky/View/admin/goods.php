<?php include __DIR__.'/_header.php'; $pageTitle='充值套餐'; ?>
<div class="card">
  <button class="btn sm" onclick="showForm()">+ 添加套餐</button>
  <div class="tb-wrap"><table class="tb" style="margin-top:12px">
    <tr><th>名称</th><th>价格</th><th>积分</th><th>VIP天数</th><th>排序</th><th>状态</th><th>操作</th></tr>
    <?php foreach ($list as $g): ?>
    <tr>
      <td><b><?= e($g['name']) ?></b></td>
      <td>¥<?= number_format((float)$g['price'], 2) ?></td>
      <td><?= (int)$g['points'] ?: '-' ?></td>
      <td><?= (int)$g['days'] ?: '-' ?></td>
      <td><?= (int)$g['sort'] ?></td>
      <td><span class="tag <?= $g['status'] ? 'g' : 'gr' ?>"><?= $g['status'] ? '上架' : '下架' ?></span></td>
      <td><button class="btn plain sm" onclick='showForm(<?= json_encode($g, JSON_UNESCAPED_UNICODE) ?>)'>编辑</button>
          <button class="btn plain sm" onclick="delG(<?= (int)$g['id'] ?>)">删除</button></td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($list)): ?><tr><td colspan="7" style="color:var(--sub)">还没有套餐。建议:积分包(如 10元=100积分)、VIP周卡/月卡/季卡</td></tr><?php endif; ?>
  </table></div>
</div>
<div class="modal" id="md"><div class="mbox">
  <h3 id="mtitle">添加套餐</h3>
  <form onsubmit="return saveG(event)">
    <input type="hidden" name="id" id="f_id" value="0">
    <div class="fi"><label>名称 *</label><input type="text" name="name" id="f_name" required placeholder="如:VIP月卡"></div>
    <div class="row">
      <div class="fi"><label>价格(元) *</label><input type="number" step="0.01" min="0.01" name="price" id="f_price" required></div>
      <div class="fi"><label>积分(0=不加)</label><input type="number" name="points" id="f_points" value="0"></div>
    </div>
    <div class="row">
      <div class="fi"><label>VIP天数(0=不加)</label><input type="number" name="days" id="f_days" value="0"></div>
      <div class="fi"><label>排序</label><input type="number" name="sort" id="f_sort" value="0"></div>
    </div>
    <div class="fi"><label>状态</label><select name="status" id="f_status"><option value="1">上架</option><option value="0">下架</option></select></div>
    <div style="display:flex;gap:10px"><button class="btn" type="submit">保存</button><button class="btn plain" type="button" onclick="md.classList.remove('open')">取消</button></div>
  </form>
</div></div>
<script>
function showForm(g){
  if(g){f_id.value=g.id;f_name.value=g.name;f_price.value=g.price;f_points.value=g.points;f_days.value=g.days;f_sort.value=g.sort;f_status.value=g.status;mtitle.textContent='编辑套餐'}
  else{f_id.value=0;f_name.value='';f_price.value='';f_points.value=0;f_days.value=0;f_sort.value=0;f_status.value=1;mtitle.textContent='添加套餐'}
  md.classList.add('open');
}
async function saveG(ev){ev.preventDefault();var j=await api('/admin.php?s=/user/goodssave',new FormData(ev.target));j.code===1?location.reload():toast(j.msg,false);return false}
async function delG(id){if(!confirmDel())return;var d=new FormData();d.append('id',id);var j=await api('/admin.php?s=/user/goodsdel',d);j.code===1?location.reload():toast(j.msg,false)}
</script>
<?php include __DIR__.'/_footer.php'; ?>
