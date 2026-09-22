<?php include __DIR__.'/_header.php'; $pageTitle='图片管理'; ?>
<div class="card">
  <div style="display:flex;gap:18px;align-items:center;flex-wrap:wrap">
    <span>共 <b><?= (int)$total ?></b> 张本地化影片图片</span>
    <span>占用 <b><?= number_format($totalSize / 1048576, 1) ?> MB</b></span>
    <span style="color:#e5322d">未引用 <b><?= (int)$orphan ?></b> 张</span>
    <button class="btn sm" onclick="imgorphan()">🧹 清理全部未引用图片</button>
    <button class="btn sm" style="background:#e5322d" onclick="imgdels()">🗑 删除选中</button>
  </div>
  <p class="hint" style="margin-top:8px">未引用图片 = 不被任何影片封面/幻灯使用的文件(采集失败残留等),清理后不可恢复,请谨慎操作。自动清理:<?= config('img_auto_clean_enable', '0') == '1' ? '<b style="color:#1f9d55">已开启(每天凌晨自动执行' . e(config('img_clean_last_result', '')) . ')</b>' : '<b>已关闭</b>(可在系统设置开启)' ?></p>
  <div class="tb-wrap"><table class="tb" style="margin-top:12px">
    <tr><th style="width:34px"><input type="checkbox" onclick="document.querySelectorAll('.ck').forEach(c=>c.checked=this.checked)"></th><th>预览</th><th>文件名</th><th>大小</th><th>时间</th><th>引用状态</th></tr>
    <?php foreach ($files as $f): ?>
    <tr>
      <td><input type="checkbox" class="ck" value="<?= e($f['name']) ?>"></td>
      <td><img src="/upload/vod/<?= e($f['name']) ?>" style="height:38px;border-radius:5px" onerror="this.style.opacity=.2"></td>
      <td><code style="font-size:11px"><?= e($f['name']) ?></code></td>
      <td><?= number_format($f['size'] / 1024, 1) ?> KB</td>
      <td style="font-size:12px;color:var(--sub)"><?= date('m-d H:i', (int)$f['time']) ?></td>
      <td><span class="tag <?= $f['used'] ? 'g' : 'r' ?>"><?= $f['used'] ? '使用中' : '未引用' ?></span></td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($files)): ?><tr><td colspan="6" style="color:var(--sub)">暂无本地化图片(开启"采集图片本地化"后这里会展示)</td></tr><?php endif; ?>
  </table></div>
  <div style="display:flex;justify-content:space-between;align-items:center;margin-top:14px;flex-wrap:wrap;gap:10px">
    <div style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--sub)">
      每页显示
      <select onchange="location='/admin.php?s=/system/images&per_page='+this.value+'&page=1'" style="padding:4px 8px;border:1px solid var(--line);border-radius:8px">
        <?php foreach ([50, 100, 200, 500, 1000] as $n): ?><option value="<?= $n ?>" <?= $perPage === $n ? 'selected' : '' ?>><?= $n ?> 张</option><?php endforeach; ?>
      </select>
      共 <?= $total ?> 张
    </div>
    <div style="display:flex;gap:8px;align-items:center">
      <a class="btn plain sm" style="text-decoration:none;<?= $page <= 1 ? 'pointer-events:none;opacity:.4' : '' ?>" href="/admin.php?s=/system/images&per_page=<?= $perPage ?>&page=1">« 首页</a>
      <a class="btn plain sm" style="text-decoration:none;<?= $page <= 1 ? 'pointer-events:none;opacity:.4' : '' ?>" href="/admin.php?s=/system/images&per_page=<?= $perPage ?>&page=<?= max(1, $page - 1) ?>">← 上一页</a>
      <span style="font-size:13px">第 <b><?= $page ?></b> / <?= $pages ?> 页</span>
      <a class="btn plain sm" style="text-decoration:none;<?= $page >= $pages ? 'pointer-events:none;opacity:.4' : '' ?>" href="/admin.php?s=/system/images&per_page=<?= $perPage ?>&page=<?= min($pages, $page + 1) ?>">下一页 →</a>
      <a class="btn plain sm" style="text-decoration:none;<?= $page >= $pages ? 'pointer-events:none;opacity:.4' : '' ?>" href="/admin.php?s=/system/images&per_page=<?= $perPage ?>&page=<?= $pages ?>">末页 »</a>
    </div>
  </div>
</div>
<script>
async function imgdels(){
  var names=[];document.querySelectorAll('.ck:checked').forEach(function(c){names.push(c.value)});
  if(!names.length){toast('请先勾选图片',false);return}
  if(!confirmDel('确定删除选中的 '+names.length+' 张图片?'))return;
  var d=new FormData();d.append('names',names.join(','));
  var j=await api('/admin.php?s=/system/imgdels',d);
  j.code===1?location.reload():toast(j.msg,false);
}
async function imgorphan(){
  if(!confirmDel('确定清理全部未引用图片?'))return;
  var j=await api('/admin.php?s=/system/imgorphan',new FormData());
  j.code===1?location.reload():toast(j.msg,false);
}
</script>
<?php include __DIR__.'/_footer.php'; ?>
