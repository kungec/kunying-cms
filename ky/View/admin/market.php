<?php include __DIR__.'/_header.php'; $pageTitle=['mode'=>'模式市场','template'=>'模板市场'][$tab] ?? '插件市场'; ?>
<?php if (!$apiOk): ?>
<div class="notice">⚠ <?= e($apiMsg) ?><br>无法连接授权服务器,请稍后重试或联系开发者处理。</div>
<?php endif; ?>
<div class="card">
  <div class="tabs">
    <a class="<?= $tab === 'plugin' ? 'on' : '' ?>" href="/admin.php?s=/system/market&tab=plugin">插件</a>
    <a class="<?= $tab === 'template' ? 'on' : '' ?>" href="/admin.php?s=/system/market&tab=template">模板</a>
    <a class="<?= $tab === 'mode' ? 'on' : '' ?>" href="/admin.php?s=/system/market&tab=mode">模式</a>
  </div>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px">
    <?php foreach ($items as $it): ?>
    <div style="border:1px solid var(--line);border-radius:10px;overflow:hidden">
      <div style="height:120px;background:linear-gradient(135deg,#232733,#3a4152);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:18px"><?= e(mb_substr((string)($it['name'] ?? ''), 0, 12)) ?></div>
      <div style="padding:14px">
        <div style="display:flex;justify-content:space-between;align-items:center">
          <b><?= e($it['name'] ?? '') ?></b>
          <span class="tag b">v<?= e($it['version'] ?? '1.0') ?></span>
        </div>
        <p class="hint" style="margin:8px 0;min-height:32px"><?= e($it['description'] ?? '') ?></p>
        <?php if (($it['type'] ?? '') === 'mode'): ?><p style="margin:0 0 8px;font-size:12px;color:#1f9d55">✓ 激活后前往 系统设置 → 影视站模式 切换启用</p><?php endif; ?>
        <div style="display:flex;gap:8px;align-items:center">
          <?php if ($it['installed']): ?>
            <?php if ($it['type'] === 'template'): ?>
              <button class="btn sm" onclick="setTpl('<?= e($it['code']) ?>')"><?= config('site_template', 'kylite') === $it['code'] ? '使用中' : '启用模板' ?></button>
            <?php else: ?>
              <button class="btn sm" onclick="togglePlugin('<?= e($it['code']) ?>')"><?= $it['installed']['status'] ? '停用' : '启用' ?></button>
            <?php endif; ?>
            <button class="btn plain sm" onclick="uninstall('<?= e($it['code']) ?>')">卸载</button>
          <?php elseif (!empty($it['authorized'])): ?>
            <button class="btn sm" onclick="install('<?= e($it['code']) ?>','<?= e($it['type'] ?? 'plugin') ?>')"><?= ($it['type'] ?? '') === 'mode' ? '一键激活' : '在线安装' ?></button>
          <?php elseif (!empty($it['price'])): ?>
            <a class="btn sm" style="text-decoration:none" href="<?= e(License::apiBase()) ?>/buy?code=<?= urlencode($it['code']) ?>&domain=<?= urlencode($_SERVER['HTTP_HOST'] ?? '') ?>" target="_blank">¥<?= number_format((float)$it['price'],2) ?> 购买</a>
          <?php elseif ($it['installed'] ?? false): ?>
            <?php // 已安装免费产品 ?>
          <?php else: ?>
            <button class="btn plain sm" onclick="install('<?= e($it['code']) ?>','<?= e($it['type'] ?? 'plugin') ?>')">免费安装</button>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if ($apiOk && empty($items)): ?><p class="hint">该分类下暂无产品</p><?php endif; ?>
  </div>
  <div style="margin-top:18px;border-top:1px solid var(--line);padding-top:14px">
    <b style="font-size:13px">本地上传安装</b>
    <form onsubmit="return doUpload(event)" id="upArea" style="margin-top:10px;border:2px dashed var(--line);border-radius:12px;padding:26px 18px;text-align:center;cursor:pointer;transition:border-color .2s,background .2s" onclick="document.getElementById('pkg').click()" ondragover="event.preventDefault();this.style.borderColor='var(--red2)';this.style.background='rgba(229,50,45,.04)'" ondragleave="this.style.borderColor='var(--line)';this.style.background=''" ondrop="event.preventDefault();this.style.borderColor='var(--line)';this.style.background='';var f=event.dataTransfer.files[0];if(f){var d=new DataTransfer();d.items.add(f);document.getElementById('pkg').files=d.files;document.getElementById('upName').textContent=f.name}">
      <div style="font-size:30px;line-height:1">📦</div>
      <p style="margin:8px 0 4px;font-size:14px;font-weight:600">点击选择 或 拖拽 zip 安装包到此处</p>
      <p class="hint" id="upName" style="margin:0">支持插件/模板 zip 包(根目录含 manifest.json);在线安装需产品已授权</p>
      <input type="file" name="package" id="pkg" accept=".zip" required style="display:none" onchange="document.getElementById('upName').textContent=this.files[0]?this.files[0].name:'支持插件/模板 zip 包(根目录含 manifest.json);在线安装需产品已授权'">
      <div style="margin-top:12px"><button class="btn sm" type="submit">上传安装</button></div>
    </form>
  </div>
</div>
<script>
async function install(code,type){
  var d=new FormData();d.append('code',code);d.append('type',type);
  var j=await api('/admin.php?s=/system/install',d);
  toast(j.msg||'完成',j.code===1);
  if(j.code===1)setTimeout(()=>location.reload(),800);
}
async function uninstall(code){
  if(!confirmDel('确定卸载该产品?'))return;
  var d=new FormData();d.append('code',code);
  var j=await api('/admin.php?s=/system/uninstall',d);
  toast(j.msg||'完成',j.code===1);
  if(j.code===1)setTimeout(()=>location.reload(),800);
}
async function setTpl(code){
  var d=new FormData();d.append('code',code);
  var j=await api('/admin.php?s=/system/settemplate',d);
  toast(j.msg||'完成',j.code===1);
  if(j.code===1)setTimeout(()=>location.reload(),800);
}
async function togglePlugin(code){
  var d=new FormData();d.append('code',code);
  var j=await api('/admin.php?s=/system/plugintoggle',d);
  toast(j.msg||'完成',j.code===1);
  if(j.code===1)setTimeout(()=>location.reload(),600);
}
async function doUpload(ev){
  ev.preventDefault();
  var j=await api('/admin.php?s=/system/upload',new FormData(ev.target));
  toast(j.msg||'完成',j.code===1);
  if(j.code===1)setTimeout(()=>location.reload(),800);
  return false;
}
</script>
<?php include __DIR__.'/_footer.php'; ?>
