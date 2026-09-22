<?php include __DIR__.'/_header.php'; $pageTitle='接口采集 - ' . $api['name']; ?>
<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <b style="font-size:16px"><?= e($api['name']) ?></b>
    <a class="btn plain sm" href="/admin.php?s=/content/collect" style="text-decoration:none">← 返回采集管理</a>
  </div>
  <p class="hint" style="margin-top:8px">接口地址:<code style="font-size:12px"><?= e($api['api_url']) ?></code><?= $api['remark'] ? ' · ' . e($api['remark']) : '' ?></p>
</div>

<div class="card">
  <b>资源站分类</b>
  <div style="margin-top:10px"><button class="btn sm" onclick="loadClasses()">⟳ 拉取资源站分类</button></div>
  <div id="clsBox" style="display:flex;flex-wrap:wrap;gap:8px;margin-top:12px"></div>
  <p class="hint" id="clsHint" style="margin-top:8px">点击"拉取资源站分类"获取该接口提供的全部资源类别;点击类别会在下方列出该分类的资源内容。</p>
</div>

<div class="card">
  <b>资源站内容</b>
  <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px;flex-wrap:wrap;gap:8px">
    <span class="hint" id="pvInfo" style="margin:0">选择分类后自动加载资源列表</span>
    <div style="display:flex;gap:8px;align-items:center">
      <button class="btn plain sm" onclick="pvGo(-1)">← 上一页</button>
      <span>第 <b id="pvPage">1</b> / <b id="pvCount">1</b> 页</span>
      <button class="btn plain sm" onclick="pvGo(1)">下一页 →</button>
      <button class="btn plain sm" onclick="loadPreview(pvPage)">⟳ 刷新</button>
    </div>
  </div>
  <div id="pvBox" style="margin-top:10px"><p class="hint">暂未加载,请先拉取分类并选择分类</p></div>
</div>

<div class="card">
  <b>采集入库</b>
  <div id="crumb" style="margin-top:10px;background:#f7f8fb;border-radius:10px;padding:10px 16px;font-size:13px;color:var(--sub);display:flex;align-items:center;gap:8px;flex-wrap:wrap">
    <span>🗂</span><b style="color:var(--text)"><?= e($api['name']) ?></b><span>▸</span>
    <span id="bcCls" style="color:var(--text)">全部资源</span><span>▸</span>
    <span>第 <b id="bcPage" style="color:var(--text)">-</b> / <span id="bcCount">-</span> 页</span>
    <span id="imgBadge" style="margin-left:auto;display:none" class="tag">封面模式</span>
  </div>
  <div class="row" style="margin-top:14px;max-width:860px">
    <div class="fi"><label>采集范围</label><select id="e_hours"><option value="0">全量(按页采集)</option><option value="24">仅最近24小时更新</option><option value="12">仅最近12小时</option><option value="6">仅最近6小时</option></select></div>
    <div class="fi"><label>起始页</label><input type="number" id="e_page" value="1" min="1"></div>
    <div class="fi" style="align-self:flex-end"><button class="btn" id="e_go" onclick="startCollect()">▶ 开始采集入库</button><button class="btn plain" id="e_stop" style="display:none" onclick="running=true">⏹ 停止</button></div>
  </div>
  <div id="progWrap" style="display:none;margin-top:12px">
    <div style="height:10px;background:#eef0f5;border-radius:6px;overflow:hidden"><div id="progBar" style="height:100%;width:0;background:linear-gradient(90deg,#e5322d,#ff7a45);border-radius:6px;transition:width .4s"></div></div>
  </div>
  <div id="statCards" style="display:none;gap:12px;margin-top:12px;flex-wrap:wrap">
    <div style="flex:1;min-width:120px;background:#f0fbf4;border-radius:10px;padding:10px 14px"><div style="font-size:12px;color:var(--sub)">本次新增</div><div id="stAdd" style="font-size:22px;font-weight:800;color:#1f9d55">0</div></div>
    <div style="flex:1;min-width:120px;background:#eef4ff;border-radius:10px;padding:10px 14px"><div style="font-size:12px;color:var(--sub)">本次更新</div><div id="stUpd" style="font-size:22px;font-weight:800;color:#2f6fed">0</div></div>
    <div style="flex:1;min-width:120px;background:#fff8ec;border-radius:10px;padding:10px 14px"><div style="font-size:12px;color:var(--sub)">封面图片</div><div id="stPic" style="font-size:14px;font-weight:700;margin-top:4px">—</div></div>
    <div style="flex:1;min-width:120px;background:#f7f8fb;border-radius:10px;padding:10px 14px"><div style="font-size:12px;color:var(--sub)">采集进度</div><div id="stProg" style="font-size:14px;font-weight:700;margin-top:4px">—</div></div>
  </div>
  <p class="hint" style="margin-top:10px">采集入库会按预览的资源列表逐页拉取详情写入本站(含播放地址),过程自动翻页;点击"停止"将在当前页完成后暂停。</p>
  <div id="feedList" style="margin-top:10px;display:flex;flex-direction:column;gap:6px"></div>
</div>

<script>
var apiId = <?= (int)$api['id'] ?>;
var running = false, selClass = 0, pvPage = 1, pvCount = 1;
function esc(s){ return String(s || '').replace(/[<>&"]/g, ''); }

async function loadClasses(){
  var box = document.getElementById('clsBox');
  box.innerHTML = '<span class="hint">拉取中…</span>';
  var d = new FormData(); d.append('id', apiId);
  var j = await api('/admin.php?s=/content/collectclasses', d);
  if (j.code !== 1) { box.innerHTML = '<span style="color:#e5322d;font-size:13px">' + (j.msg || '拉取失败') + '</span>'; return; }
  var cls = j.data.classes || [];
  var html = '<label style="display:inline-flex;align-items:center;gap:5px;border:1px solid var(--line);border-radius:8px;padding:6px 12px;cursor:pointer"><input type="radio" name="cls" value="0" data-n="全部资源" checked onchange="pickClass(this)">全部资源</label>';
  cls.forEach(function (c) {
    var n = String(c.type_name).replace(/[<>&"]/g, '');
    html += '<label style="display:inline-flex;align-items:center;gap:5px;border:1px solid var(--line);border-radius:8px;padding:6px 12px;cursor:pointer"><input type="radio" name="cls" value="' + c.type_id + '" data-n="' + n + '" onchange="pickClass(this)">' + n + '</label>';
  });
  box.innerHTML = html;
  document.getElementById('clsHint').textContent = '资源站共 ' + cls.length + ' 个分类,资源总片数 ' + (j.data.total || 0) + '。点击分类查看资源内容。';
  loadPreview(1);
}

function pickClass(input){
  selClass = +input.value || 0;
  document.getElementById('curCls').value = input.getAttribute('data-n') || '全部资源';
  loadPreview(1);
}

async function loadPreview(page){
  pvPage = Math.max(1, page || 1);
  var box = document.getElementById('pvBox');
  box.innerHTML = '<span class="hint">拉取资源站内容中…</span>';
  var d = new FormData();
  d.append('id', apiId); d.append('type_id', selClass); d.append('page', pvPage);
  var j = await api('/admin.php?s=/content/collectpreview', d);
  if (j.code !== 1) { box.innerHTML = '<span style="color:#e5322d;font-size:13px">' + (j.msg || '拉取失败') + '</span>'; return; }
  pvCount = Math.max(1, +j.data.pagecount || 1);
  pvPage = +j.data.page || pvPage;
  document.getElementById('pvPage').textContent = pvPage;
  document.getElementById('pvCount').textContent = pvCount;
  document.getElementById('pvInfo').textContent = '当前分类共 ' + j.data.total + ' 部资源';
  var rows = j.data.list || [];
  if (!rows.length) { box.innerHTML = '<p class="hint">该分类暂无内容</p>'; return; }
  var h = '<table class="tb"><tr><th style="width:60px">#</th><th>片名</th><th style="width:110px">资源分类</th><th style="width:110px">备注</th><th style="width:150px">更新时间</th></tr>';
  rows.forEach(function (r, i) {
    h += '<tr><td>' + ((pvPage - 1) * 20 + i + 1) + '</td><td>' + esc(r.name) + '</td><td>' + esc(r.type) + '</td><td>' + esc(r.remarks) + '</td><td style="font-size:12px;color:var(--sub)">' + esc(r.time) + '</td></tr>';
  });
  h += '</table>';
  box.innerHTML = h;
}

function pvGo(delta){ loadPreview(pvPage + delta); }
async function saveSpeed(v){
  var d=new FormData();d.append('speed',v);
  var j=await api('/admin.php?s=/content/speedsave',d);
  if(j.code===1)toast('采集速度已保存',true);
}

var taskAdd = 0, taskUpd = 0, stopFlag = false, eRetry = 0, eSkip = 0;
running = false;
document.getElementById('e_stop').onclick = function(){ stopFlag = true; this.textContent = '将在本页后停止…'; };

async function startCollect(){
  if (running) return; running = true; stopFlag = false;
  e_go.disabled = true; e_go.textContent = '采集中…';
  document.getElementById('progWrap').style.display = 'block';
  document.getElementById('statCards').style.display = 'flex';
  var d = new FormData();
  d.append('api_id', apiId);
  d.append('page', e_page.value);
  d.append('type_id', selClass);
  d.append('hours', e_hours.value);
  var page = +e_page.value;
  bcPage.textContent = page;
  var d0 = new FormData(); d0.append('id', apiId);
  var j0 = await api('/admin.php?s=/content/collectclasses', d0).catch(function(){return null});
  var j; try { j = await api('/admin.php?s=/content/collectrun', d); } catch (e) { j = { code: 0, msg: '网络异常或处理超时' }; }
  if (j.code === 1) {
    eRetry = 0; eSkip = 0;
    taskAdd += (+j.data.added || 0); taskUpd += (+j.data.updated || 0);
    var pc = Math.max(1, +j.data.pagecount || 1);
    bcPage.textContent = page; bcCount.textContent = pc;
    var pct = Math.min(100, Math.round(page / pc * 100));
    progBar.style.width = pct + '%';
    stAdd.textContent = taskAdd; stUpd.textContent = taskUpd;
    stProg.textContent = page + ' / ' + pc + ' 页 (' + pct + '%)';
    stPic.innerHTML = j.data.pic_local_mode
      ? '<span style="color:#1f9d55">📥 本地化存储</span>'
      : '<span style="color:#9aa0ad">🔗 外链模式</span>';
    imgBadge.style.display = 'inline-block';
    imgBadge.textContent = j.data.pic_local_mode ? '封面本地化:开' : '封面外链';
    imgBadge.style.background = j.data.pic_local_mode ? '#e5f7eb' : '#eef0f5';
    imgBadge.style.color = j.data.pic_local_mode ? '#1f9d55' : '#9aa0ad';
    var items = j.data.items || [];
    var feed = document.getElementById('feedList');
    var html = '';
    items.slice(0, 30).forEach(function (it, k) {
      var tag = it.status === 1 ? '<span style="background:#e5f7eb;color:#1f9d55;font-size:11px;padding:1px 8px;border-radius:5px">新增</span>'
        : it.status === 2 ? '<span style="background:#eef4ff;color:#2f6fed;font-size:11px;padding:1px 8px;border-radius:5px">更新</span>'
        : '<span style="background:#eef0f5;color:#9aa0ad;font-size:11px;padding:1px 8px;border-radius:5px">跳过</span>';
      var picTag = it.pic_local ? '<span title="封面已下载到本站" style="margin-left:6px">📥</span>' : '';
      html += '<div style="display:flex;align-items:center;gap:10px;background:#fff;border:1px solid var(--line);border-radius:10px;padding:6px 10px">'
        + '<img src="' + it.pic + '" onerror="this.style.visibility=\'hidden\'" style="width:30px;height:40px;object-fit:cover;border-radius:4px;background:#eef0f5">'
        + '<span style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:13px">' + esc(it.name) + '</span>'
        + '<span style="font-size:12px;color:var(--sub)">' + esc(it.remarks) + '</span>' + picTag + tag + '</div>';
    });
    feed.insertAdjacentHTML('afterbegin', html);
    while (feed.children.length > 60) feed.removeChild(feed.lastChild);
    e_page.value = Math.min((+j.data.page) + 1, pc);
    if (stopFlag) {
      e_log.textContent = '⏹ 已停止(本任务累计:新增 ' + taskAdd + ' 部,更新 ' + taskUpd + ' 部)';
    } else if ((+j.data.page) < pc) {
      running = false;
      var dly = {gentle:2600,slow:1600,normal:900,fast:350}[e_speed.value] || 900;
      setTimeout(startCollect, dly); return;
    } else {
      e_log.textContent = '★ 采集完成!累计新增 ' + taskAdd + ' 部,更新 ' + taskUpd + ' 部';
    }
  } else {
    eRetry++;
    if (eRetry <= 3) {
      running = false;
      e_log.textContent = '✗ 第' + page + '页失败,自动重试' + eRetry + '/3…(' + j.msg + ')';
      setTimeout(startCollect, 3000 * eRetry); return;
    }
    eSkip++;
    if (eSkip >= 10) {
      e_log.textContent = '✗ 连续10页失败,已停止采集,请检查资源站是否可访问';
    } else {
      eRetry = 0;
      e_log.textContent = '✗ 第' + page + '页连续失败,15秒后跳过继续';
      e_page.value = page + 1;
      running = false;
      setTimeout(startCollect, 15000); return;
    }
  }
  e_go.disabled = false; e_go.textContent = '▶ 开始采集入库';
  document.getElementById('e_stop').textContent = '⏹ 停止';
  running = false;
}
loadClasses();

</script>
<?php include __DIR__.'/_footer.php'; ?>
