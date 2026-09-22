<?php include __DIR__.'/_header.php'; $pageTitle='在线更新'; ?>
<div class="card" style="max-width:640px">
  <div style="display:flex;justify-content:space-between;align-items:center">
    <div>
      <div class="hint">当前版本</div>
      <b style="font-size:20px">坤影CMS v<?= e(KY_VERSION) ?></b>
    </div>
    <div style="text-align:right">
      <?php if ($info === null): ?>
        <span class="tag gr">无法连接控制端</span>
      <?php elseif ($info['has_update']): ?>
        <span class="tag r">有新版本 v<?= e($info['version']) ?></span>
      <?php else: ?>
        <span class="tag g">已是最新版本</span>
      <?php endif; ?>
    </div>
  </div>
  <?php if ($info && $info['changelog']): ?>
  <div class="hint" style="margin-top:14px;white-space:pre-wrap;background:#f7f8fb;padding:12px;border-radius:8px"><?= e($info['changelog']) ?></div>
  <?php endif; ?>
  <div style="display:flex;gap:10px;margin-top:16px">
    <button class="btn plain" onclick="doCheck()">检查更新</button>
    <?php if ($info && $info['has_update']): ?><button class="btn" id="up" onclick="doUpdate()">一键升级到 v<?= e($info['version']) ?></button><?php endif; ?>
  </div>
  <p class="hint">更新前系统自动备份被覆盖文件至 data/cache/backup_*;data 与 install 目录不会被覆盖。</p>
</div>
<script>
async function doCheck(){location.reload()}
async function doUpdate(){
  var up=document.getElementById('up');up.disabled=true;up.textContent='正在升级,请勿关闭页面…';
  var j=await api('/admin.php?s=/system/updateapply',new FormData());
  toast(j.msg||'完成',j.code===1);
  up.textContent=j.msg||'完成';
  if(j.code===1)setTimeout(()=>location.reload(),2000);
}
</script>
<?php include __DIR__.'/_footer.php'; ?>
