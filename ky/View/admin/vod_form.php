<?php include __DIR__.'/_header.php'; $pageTitle=$vod?'编辑影片':'添加影片'; $v=$vod; ?>
<div class="card">
  <form id="f" class="form" onsubmit="return save(event)">
    <input type="hidden" name="id" value="<?= (int)($v['id'] ?? 0) ?>">
    <div class="row">
      <div class="fi"><label>片名 *</label><input type="text" name="name" value="<?= e($v['name'] ?? '') ?>" required></div>
      <div class="fi"><label>副名</label><input type="text" name="sub" value="<?= e($v['sub'] ?? '') ?>"></div>
    </div>
    <div class="row">
      <div class="fi"><label>分类</label><select name="type_id"><?php foreach ($types as $t): ?><option value="<?= (int)$t['id'] ?>" <?= ($v['type_id'] ?? 0) == $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?></select></div>
      <div class="fi"><label>备注(如:更新至第10集)</label><input type="text" name="remarks" value="<?= e($v['remarks'] ?? '') ?>"></div>
    </div>
    <div class="row">
      <div class="fi"><label>类型(逗号分隔)</label><input type="text" name="class" value="<?= e($v['class'] ?? '') ?>" placeholder="动作,科幻"></div>
      <div class="fi"><label>年份</label><input type="text" name="year" value="<?= e($v['year'] ?? '') ?>"></div>
    </div>
    <div class="row">
      <div class="fi"><label>地区</label><input type="text" name="area" value="<?= e($v['area'] ?? '') ?>"></div>
      <div class="fi"><label>语言</label><input type="text" name="lang" value="<?= e($v['lang'] ?? '') ?>"></div>
      <div class="fi"><label>评分(0-10)</label><input type="number" step="0.1" min="0" max="10" name="score" value="<?= e((string)($v['score'] ?? '0')) ?>"></div>
    </div>
    <div class="row">
      <div class="fi"><label>导演</label><input type="text" name="director" value="<?= e($v['director'] ?? '') ?>"></div>
      <div class="fi"><label>演员</label><input type="text" name="actor" value="<?= e($v['actor'] ?? '') ?>"></div>
    </div>
    <div class="fi"><label>封面图地址</label><input type="text" name="pic" value="<?= e($v['pic'] ?? '') ?>" placeholder="https://..."></div>
    <div class="fi"><label>简介</label><textarea name="content"><?= e($v['content'] ?? '') ?></textarea></div>
    <div class="fi"><label>播放来源标识(多组用$$$分隔,如 jsm3u8)</label><input type="text" name="play_from" value="<?= e($v['play_from'] ?? '') ?>" placeholder="jsm3u8"></div>
    <div class="fi"><label>播放地址(组间$$$,集间#,名称$地址)</label><textarea name="play_url" style="min-height:120px;font-family:monospace;font-size:12px" placeholder="第01集$http://xxx/1.m3u8#第02集$http://xxx/2.m3u8"><?= e($v['play_url'] ?? '') ?></textarea></div>
    <div class="row">
      <div class="fi"><label>观看权限</label>
        <select name="vip">
          <option value="0" <?= ($v['vip'] ?? 0) == 0 ? 'selected' : '' ?>>免费观看</option>
          <option value="1" <?= ($v['vip'] ?? 0) == 1 ? 'selected' : '' ?>>VIP专享</option>
        </select>
      </div>
      <div class="fi"><label>积分购买(0=不用购买,需积分支付解锁)</label><input type="number" name="points" min="0" value="<?= (int)($v['points'] ?? 0) ?>"></div>
    </div>
    <div class="row">
      <div class="fi"><label>状态</label>
        <select name="status"><option value="1" <?= ($v['status'] ?? 1) == 1 ? 'selected' : '' ?>>上架</option><option value="0" <?= isset($v) && $v['status'] == 0 ? 'selected' : '' ?>>下架</option></select>
      </div>
    </div>
    <button class="btn" type="submit" id="go">保存</button>
    <a class="btn plain" href="/admin.php?s=/content/vod">返回列表</a>
  </form>
</div>
<script>
async function save(ev){
  ev.preventDefault();
  var go=document.getElementById('go');go.disabled=true;
  var j=await api('/admin.php?s=/content/vodsave',new FormData(ev.target));
  if(j.code===1){toast('保存成功');setTimeout(()=>location.href='/admin.php?s=/content/vod',600)}
  else{toast(j.msg,false);go.disabled=false}
  return false;
}
</script>
<?php include __DIR__.'/_footer.php'; ?>
