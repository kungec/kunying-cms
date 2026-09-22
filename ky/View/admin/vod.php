<?php include __DIR__.'/_header.php'; $pageTitle='视频管理'; ?>
<div class="card">
  <div class="searchbar">
    <form method="get" action="/admin.php" style="display:flex;gap:8px;flex-wrap:wrap">
      <input type="hidden" name="s" value="/content/vod">
      <select name="type"><option value="0">全部分类</option><?php foreach ($types as $t): ?><option value="<?= (int)$t['id'] ?>" <?= $typeId == $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?></select>
      <input type="text" name="wd" value="<?= e($wd) ?>" placeholder="按片名搜索">
      <button class="btn sm" type="submit">搜索</button>
    </form>
    <a class="btn sm" href="/admin.php?s=/content/vodform">+ 添加影片</a>
    <button class="btn sm" style="background:#e5322d" onclick="voddels()">🗑 删除选中</button>
  </div>
  <div class="tb-wrap"><table class="tb">
    <tr><th style="width:34px"><input type="checkbox" onclick="document.querySelectorAll('.ck').forEach(c=>c.checked=this.checked)"></th><th>ID</th><th>片名</th><th>分类</th><th>备注</th><th>付费</th><th>热度</th><th>状态</th><th>更新时间</th><th>操作</th></tr>
    <?php foreach ($list as $v): ?>
    <tr>
      <td><input type="checkbox" class="ck" value="<?= (int)$v['id'] ?>"></td>
      <td><?= (int)$v['id'] ?></td>
      <td style="max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><a href="/index.php?s=/vod/detail&id=<?= (int)$v['id'] ?>" target="_blank" style="color:var(--blue)"><?= e($v['name']) ?></a></td>
      <td><?= e($v['tname'] ?? '-') ?></td>
      <td><?= e($v['remarks']) ?></td>
      <td><?= $v['vip'] ? '<span class="tag r">VIP</span>' : ((int)$v['points'] > 0 ? '<span class="tag b">' . (int)$v['points'] . '积分</span>' : '<span class="tag gr">免费</span>') ?></td>
      <td><?= number_format((int)$v['total_hits']) ?></td>
      <td><span class="tag <?= $v['status'] ? 'g' : 'gr' ?>"><?= $v['status'] ? '上架' : '下架' ?></span></td>
      <td><?= date('m-d H:i', (int)$v['updatetime']) ?></td>
      <td><a class="btn plain sm" href="/admin.php?s=/content/vodform&id=<?= (int)$v['id'] ?>">编辑</a> <button class="btn plain sm" onclick="delOne(<?= (int)$v['id'] ?>)">删除</button></td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($list)): ?><tr><td colspan="10" style="color:var(--sub)">暂无数据,可前往「采集管理」一键采集影片</td></tr><?php endif; ?>
  </table></div>
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <button class="btn plain sm" onclick="delSel()">删除选中</button>
    <div><?= $pageHtml ?></div>
  </div>
</div>
<script>
async function delSel(){
  var ids=[...document.querySelectorAll('.ck:checked')].map(c=>c.value);
  if(!ids.length||!confirmDel('确定删除选中的 '+ids.length+' 部影片?'))return;
  var d=new FormData();ids.forEach(i=>d.append('ids[]',i));
  var j=await api('/admin.php?s=/content/voddel',d);
  j.code===1?location.reload():toast(j.msg,false);
}
async function delOne(id){
  if(!confirmDel())return;
  var d=new FormData();d.append('ids[]',id);
  var j=await api('/admin.php?s=/content/voddel',d);
  j.code===1?location.reload():toast(j.msg,false);
}
</script>
<script>
async function voddels(){
  var ids=[];document.querySelectorAll('.ck:checked').forEach(function(c){ids.push(c.value)});
  if(!ids.length){toast('请先勾选影片',false);return}
  if(!confirmDel('确定删除选中的 '+ids.length+' 部影片?删除后不可恢复'))return;
  var d=new FormData();d.append('ids',ids.join(','));
  var j=await api('/admin.php?s=/content/voddels',d);
  j.code===1?location.reload():toast(j.msg,false);
}
</script>
<?php include __DIR__.'/_footer.php'; ?>
