<?php include __DIR__.'/_header.php'; $pageTitle='采集管理'; ?>
<?php
$lastAuto = (int)config('collect_last_auto', 0);
$interval = max(5, (int)config('collect_auto_interval', 60));
$nextAt = $lastAuto > 0 ? $lastAuto + $interval * 60 : 0;
$autoOn = config('collect_auto_enable', '0') == '1';
$lastResult = json_decode((string)config('collect_last_result', ''), true) ?: [];
$sumAdd = 0; $sumUpd = 0; $hasErr = false;
foreach ($lastResult as $r) {
    if (isset($r['error'])) { $hasErr = true; continue; }
    $sumAdd += (int)($r['added'] ?? 0); $sumUpd += (int)($r['updated'] ?? 0);
}
?>
<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <b>定时采集 / 去重设置</b>
    <button class="btn" onclick="runAuto()">⚡ 立即执行一次定时采集</button>
  </div>
  <div style="display:flex;gap:12px;margin-top:14px;flex-wrap:wrap">
    <div style="flex:1;min-width:150px;background:<?= $autoOn ? '#e5f7eb' : '#f0f1f5' ?>;border-radius:12px;padding:12px 16px">
      <div style="font-size:12px;color:var(--sub)">定时采集状态</div>
      <div style="font-size:16px;font-weight:800;margin-top:4px;color:<?= $autoOn ? '#1f9d55' : '#9aa0ad' ?>"><?= $autoOn ? '🟢 运行中' : '⚪ 已关闭' ?></div>
      <div style="font-size:12px;color:var(--sub);margin-top:2px">每 <?= $interval ?> 分钟一轮</div>
    </div>
    <div style="flex:1;min-width:150px;background:#f7f8fb;border-radius:12px;padding:12px 16px">
      <div style="font-size:12px;color:var(--sub)">上次自动执行</div>
      <div style="font-size:14px;font-weight:700;margin-top:4px"><?= $lastAuto ? date('m-d H:i', $lastAuto) : '从未执行' ?></div>
      <div style="font-size:12px;color:var(--sub);margin-top:2px">采集范围:近12小时更新</div>
    </div>
    <div style="flex:1;min-width:150px;background:#f7f8fb;border-radius:12px;padding:12px 16px">
      <div style="font-size:12px;color:var(--sub)">下次预计执行</div>
      <div style="font-size:14px;font-weight:700;margin-top:4px"><?= $autoOn ? ($nextAt ? date('m-d H:i', $nextAt) : '访问触发') : '待开启' ?></div>
      <div style="font-size:12px;color:var(--sub);margin-top:2px">由访客访问自动触发</div>
    </div>
    <div style="flex:1;min-width:150px;background:#f7f8fb;border-radius:12px;padding:12px 16px">
      <div style="font-size:12px;color:var(--sub)">上次执行成果</div>
      <div style="font-size:14px;font-weight:700;margin-top:4px">
        <span style="color:#1f9d55">新增 <?= $sumAdd ?></span> ·
        <span style="color:#2f6fed">更新 <?= $sumUpd ?></span>
        <?= $hasErr ? '<span style="color:#e5322d">有失败</span>' : '' ?>
      </div>
      <div style="font-size:12px;color:var(--sub);margin-top:2px"><?= count($lastResult) ? count($lastResult) . ' 个接口参与' : '暂无数据' ?></div>
    </div>
  </div>
  <?php if ($lastResult): ?>
  <div style="margin-top:12px;background:#f7f8fb;border-radius:10px;padding:10px 14px">
    <div style="font-size:12px;color:var(--sub);margin-bottom:6px">上次各接口采集明细</div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <?php foreach ($lastResult as $r): ?>
      <?php if (isset($r['error'])): ?>
      <span style="background:#fff;border:1px solid #f5c6c4;color:#e5322d;font-size:12px;border-radius:8px;padding:5px 12px"><?= e($r['api']) ?> · 失败:<?= e(mb_substr($r['error'], 0, 40)) ?></span>
      <?php else: ?>
      <span style="background:#fff;border:1px solid var(--line);font-size:12px;border-radius:8px;padding:5px 12px"><?= e($r['api']) ?> · <b style="color:#1f9d55">+<?= (int)$r['added'] ?></b> · <span style="color:#2f6fed">更新 <?= (int)$r['updated'] ?></span></span>
      <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
  <div class="row" style="margin-top:16px;max-width:860px">
    <div class="fi"><label>定时自动采集</label><select id="g_auto"><option value="1" <?= config('collect_auto_enable', '0') == '1' ? 'selected' : '' ?>>开启</option><option value="0" <?= config('collect_auto_enable', '0') != '1' ? 'selected' : '' ?>>关闭</option></select></div>
    <div class="fi"><label>间隔(分钟,最小5)</label><input type="number" id="g_interval" value="<?= $interval ?>" min="5"></div>
    <div class="fi"><label>同名影片去重</label><select id="g_dedup"><option value="1" <?= config('collect_dedup_title', '1') == '1' ? 'selected' : '' ?>>合并播放地址(推荐)</option><option value="0" <?= config('collect_dedup_title', '1') != '1' ? 'selected' : '' ?>>关闭</option></select></div>
    <div class="fi"><label>采集图片到本地</label><select id="g_imglocal"><option value="1" <?= config('collect_img_local', '0') == '1' ? 'selected' : '' ?>>下载到本站(推荐)</option><option value="0" <?= config('collect_img_local', '0') != '1' ? 'selected' : '' ?>>用外链</option></select></div>
    <div class="fi"><label>采集速度</label><select id="g_speed" onchange="saveSpeed(this.value)"><option value="gentle" <?= config('collect_speed', 'normal') == 'gentle' ? 'selected' : '' ?>>温和(防封)</option><option value="slow" <?= config('collect_speed', 'normal') == 'slow' ? 'selected' : '' ?>>慢速</option><option value="normal" <?= config('collect_speed', 'normal') == 'normal' ? 'selected' : '' ?>>标准(推荐)</option><option value="fast" <?= config('collect_speed', 'normal') == 'fast' ? 'selected' : '' ?>>极速</option></select></div>
    <div class="fi" style="align-self:flex-end"><button class="btn" onclick="saveGlobal()">保存设置</button></div>
  </div>
</div>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <b>采集接口</b>
    <button class="btn sm" onclick="showApi()">+ 添加接口</button>
  </div>
  <div class="tb-wrap"><table class="tb" style="margin-top:12px">
    <tr><th>ID</th><th>名称</th><th>接口地址</th><th>备注</th><th>状态</th><th>操作</th></tr>
    <?php foreach ($apis as $a): ?>
    <tr>
      <td><?= (int)$a['id'] ?></td>
      <td><b><?= e($a['name']) ?></b></td>
      <td style="max-width:340px;word-break:break-all;font-size:12px"><a href="/admin.php?s=/content/collectenter&id=<?= (int)$a['id'] ?>" style="color:var(--blue)" title="点击进入接口,指定资源采集"><?= e($a['api_url']) ?></a></td>
      <td style="font-size:12px"><?= e($a['remark']) ?></td>
      <td><span class="tag <?= $a['status'] ? 'g' : 'gr' ?>"><?= $a['status'] ? '启用' : '停用' ?></span></td>
      <td>
        <a class="btn sm" href="/admin.php?s=/content/collectenter&id=<?= (int)$a['id'] ?>" style="text-decoration:none">进入采集</a>
        <button class="btn plain sm" onclick="startRun(<?= (int)$a['id'] ?>,'<?= e($a['name']) ?>')">快速采集</button>
        <button class="btn plain sm" onclick='showApi(<?= json_encode($a, JSON_UNESCAPED_UNICODE) ?>)'>编辑</button>
        <button class="btn plain sm" onclick="delApi(<?= (int)$a['id'] ?>)">删除</button>
      </td>
    </tr>
    <?php endforeach; ?>
  </table></div>
  <p class="hint">兼容苹果CMS v10 标准JSON接口;已内置「极速资源」。同名影片自动合并播放地址;采集时按归一化片名识别重复。</p>
</div>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <b>重复影片合并</b>
    <button class="btn sm" onclick="dupScan()" id="dupBtn">🔍 扫描疑似重复影片</button>
  </div>
  <p class="hint" style="margin-top:8px">按归一化片名(忽略空格/标点/罗马数字写法差异,如「Ⅲ」与「第三季」)与年份智能匹配。合并后播放地址并入保留条目,评论/收藏/播放记录一并转移。</p>
  <div id="dupBox" style="margin-top:12px"></div>
</div>

<div class="card" id="runBox" style="display:none">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <b>快速采集</b>
    <button class="btn plain sm" onclick="document.getElementById('runBox').style.display='none'">✕ 关闭</button>
  </div>
  <div id="rumb" style="margin-top:10px;background:#f7f8fb;border-radius:10px;padding:10px 16px;font-size:13px;color:var(--sub);display:flex;align-items:center;gap:8px;flex-wrap:wrap">
    <span>🗂</span><b id="runApiName" style="color:var(--text)">-</b><span>▸</span>
    <span>全部资源</span><span>▸</span>
    <span>第 <b id="bcPage" style="color:var(--text)">-</b> / <span id="bcCount">-</span> 页</span>
    <span id="imgBadge" style="margin-left:auto;display:none" class="tag">封面模式</span>
  </div>
  <div class="row" style="margin-top:14px;max-width:760px">
    <div class="fi"><label>采集范围</label><select id="r_hours"><option value="24">仅最近24小时更新(推荐)</option><option value="12">仅最近12小时</option><option value="6">仅最近6小时</option><option value="0">全量(按页采集)</option></select></div>
    <div class="fi"><label>采集速度</label><select id="r_speed" onchange="saveSpeed(this.value)"><option value="gentle" <?= config('collect_speed', 'normal') == 'gentle' ? 'selected' : '' ?>>温和(防封)</option><option value="slow" <?= config('collect_speed', 'normal') == 'slow' ? 'selected' : '' ?>>慢速</option><option value="normal" <?= config('collect_speed', 'normal') == 'normal' ? 'selected' : '' ?>>标准(推荐)</option><option value="fast" <?= config('collect_speed', 'normal') == 'fast' ? 'selected' : '' ?>>极速</option></select></div>
    <div class="fi"><label>起始页</label><input type="number" id="r_page" value="1" min="1"></div>
    <div class="fi" style="align-self:flex-end"><button class="btn" id="r_go" onclick="runPage()">▶ 开始采集</button><button class="btn plain" id="r_stop" style="display:none" onclick="rStop=true;this.textContent='将在本页后停止…'">⏹ 停止</button></div>
  </div>
  <div id="rProgWrap" style="display:none;margin-top:12px">
    <div style="height:10px;background:#eef0f5;border-radius:6px;overflow:hidden"><div id="progBar" style="height:100%;width:0;background:linear-gradient(90deg,#e5322d,#ff7a45);border-radius:6px;transition:width .4s"></div></div>
  </div>
  <div id="rStat" style="display:none;gap:12px;margin-top:12px;flex-wrap:wrap">
    <div style="flex:1;min-width:130px;background:#f0fbf4;border-radius:10px;padding:10px 14px"><div style="font-size:12px;color:var(--sub)">本次新增</div><div id="stAdd" style="font-size:22px;font-weight:800;color:#1f9d55">0</div></div>
    <div style="flex:1;min-width:130px;background:#eef4ff;border-radius:10px;padding:10px 14px"><div style="font-size:12px;color:var(--sub)">本次更新</div><div id="stUpd" style="font-size:22px;font-weight:800;color:#2f6fed">0</div></div>
    <div style="flex:1;min-width:130px;background:#fff8ec;border-radius:10px;padding:10px 14px"><div style="font-size:12px;color:var(--sub)">封面图片</div><div id="stPic" style="font-size:14px;font-weight:700;margin-top:4px">—</div></div>
    <div style="flex:1;min-width:130px;background:#f7f8fb;border-radius:10px;padding:10px 14px"><div style="font-size:12px;color:var(--sub)">采集进度</div><div id="stProg" style="font-size:14px;font-weight:700;margin-top:4px">—</div></div>
  </div>
  <div id="rFeed" style="margin-top:10px;display:flex;flex-direction:column;gap:6px"></div>
</div>

<div class="modal" id="md">
  <div class="mbox">
    <h3 id="mtitle">添加接口</h3>
    <form onsubmit="return saveApi(event)">
      <input type="hidden" name="id" id="f_id" value="0">
      <div class="fi"><label>名称 *</label><input type="text" name="name" id="f_name" required></div>
      <div class="fi"><label>接口地址(JSON) *</label><input type="text" name="api_url" id="f_api" required placeholder="https://xxx/api.php/provide/vod/at/json"></div>
      <div class="fi"><label>备注</label><input type="text" name="remark" id="f_remark"></div>
      <div class="row">
        <div class="fi"><label>状态</label><select name="status" id="f_status"><option value="1">启用</option><option value="0">停用</option></select></div>
        <div class="fi"><label>自动采集</label><select name="collect_auto" id="f_cauto"><option value="1">开启</option><option value="0">关闭</option></select></div>
        <div class="fi"><label>增量小时(h)</label><input type="number" name="collect_hours" id="f_chours" value="12" min="1"></div>
      </div>
      <div style="display:flex;gap:10px"><button class="btn" type="submit">保存</button><button class="btn plain" type="button" onclick="md.classList.remove('open')">取消</button></div>
    </form>
  </div>
</div>
<script>
var running=false;
function showApi(a){
  if(a){f_id.value=a.id;f_name.value=a.name;f_api.value=a.api_url;f_remark.value=a.remark;f_status.value=a.status;f_cauto.value=a.collect_auto||0;f_chours.value=a.collect_hours||24;mtitle.textContent='编辑接口'}
  else{f_id.value=0;f_name.value='';f_api.value='';f_remark.value='';f_status.value=1;mtitle.textContent='添加接口'}
  md.classList.add('open');
}
async function saveApi(ev){
  ev.preventDefault();
  var j=await api('/admin.php?s=/content/collectsave',new FormData(ev.target));
  if(j.code===1){location.reload()}else{toast(j.msg,false)}
  return false;
}
async function delApi(id){
  if(!confirmDel())return;
  var d=new FormData();d.append('id',id);
  var j=await api('/admin.php?s=/content/collectdel',d);
  j.code===1?location.reload():toast(j.msg,false);
}
var rAdd=0,rUpd=0,rStop=false,running=false;
function startRun(id,name){
  runApiName.textContent=name||('接口 #'+id);
  runBox.dataset.api=id;
  runBox.style.display='block';
  rAdd=0;rUpd=0;rStop=false;
  stAdd.textContent='0';stUpd.textContent='0';
  document.getElementById('rProgWrap').style.display='none';
  document.getElementById('rStat').style.display='flex';
  document.getElementById('rFeed').innerHTML='';
  document.getElementById('r_stop').style.display='inline-block';
  document.getElementById('r_stop').textContent='⏹ 停止';
  runBox.scrollIntoView({behavior:'smooth'});
}
function esc(s){ return String(s||'').replace(/[<>&"]/g,''); }
async function saveSpeed(v){
  var d=new FormData();d.append('speed',v);
  var j=await api('/admin.php?s=/content/speedsave',d);
  if(j.code===1)toast('采集速度已保存:'+(v==='gentle'?'温和':v==='slow'?'慢速':v==='fast'?'极速':'标准'),true);
}
async function runPage(){
  if(running)return; running=true;
  r_go.disabled=true;r_go.textContent='采集中…';
  document.getElementById('rProgWrap').style.display='block';
  document.getElementById('rStat').style.display='flex';
  var page=+r_page.value;
  bcPage.textContent=page;
  var d=new FormData();
  d.append('api_id',runBox.dataset.api);
  d.append('page',r_page.value);
  d.append('type_id',0);
  d.append('hours',r_hours.value);
  var j=await api('/admin.php?s=/content/collectrun',d);
  if(j.code===1){
    rAdd+=(+j.data.added||0);rUpd+=(+j.data.updated||0);
    var pc=Math.max(1,+j.data.pagecount||1);
    bcPage.textContent=page;bcCount.textContent=pc;
    var pct=Math.min(100,Math.round(page/pc*100));
    progBar.style.width=pct+'%';
    stAdd.textContent=rAdd;stUpd.textContent=rUpd;
    stProg.textContent=page+' / '+pc+' 页 ('+pct+'%)';
    stPic.innerHTML=j.data.pic_local_mode?'<span style="color:#1f9d55">📥 本地化存储</span>':'<span style="color:#9aa0ad">🔗 外链模式</span>';
    imgBadge.style.display='inline-block';
    imgBadge.textContent=j.data.pic_local_mode?'封面本地化:开':'封面外链';
    imgBadge.style.background=j.data.pic_local_mode?'#e5f7eb':'#eef0f5';
    imgBadge.style.color=j.data.pic_local_mode?'#1f9d55':'#9aa0ad';
    var feed=document.getElementById('rFeed');
    var html='';
    (j.data.items||[]).slice(0,30).forEach(function(it){
      var tag=it.status===1?'<span style="background:#e5f7eb;color:#1f9d55;font-size:11px;padding:1px 8px;border-radius:5px">新增</span>'
        :it.status===2?'<span style="background:#eef4ff;color:#2f6fed;font-size:11px;padding:1px 8px;border-radius:5px">更新</span>'
        :'<span style="background:#eef0f5;color:#9aa0ad;font-size:11px;padding:1px 8px;border-radius:5px">跳过</span>';
      var picTag=it.pic_local?'<span title="封面已下载到本站" style="margin-left:6px">📥</span>':'';
      html+='<div style="display:flex;align-items:center;gap:10px;background:#fff;border:1px solid var(--line);border-radius:10px;padding:6px 10px">'
        +'<img src="'+it.pic+'" onerror="this.style.visibility=\'hidden\'" style="width:30px;height:40px;object-fit:cover;border-radius:4px;background:#eef0f5">'
        +'<span style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:13px">'+esc(it.name)+'</span>'
        +'<span style="font-size:12px;color:var(--sub)">'+esc(it.remarks)+'</span>'+picTag+tag+'</div>';
    });
    feed.insertAdjacentHTML('afterbegin',html);
    while(feed.children.length>60)feed.removeChild(feed.lastChild);
    r_page.value=Math.min((+j.data.page)+1,pc);
    if(rStop){
      r_go.disabled=false;r_go.textContent='▶ 开始采集';running=false;
      r_stop.textContent='⏹ 停止';
      return;
    }
    if((+j.data.page)<pc){
      running=false;
      var dly={gentle:2600,slow:1600,normal:900,fast:350}[r_speed.value]||900;
      setTimeout(runPage,dly);return;
    }
  }else{
    toast('✗ '+j.msg,false);
  }
  r_go.disabled=false;r_go.textContent='▶ 开始采集';
  r_stop.textContent='⏹ 停止';
  running=false;
}
</script>
<script>
async function saveGlobal(){
  var d=new FormData();
  d.append('collect_auto_enable', g_auto.value);
  d.append('collect_auto_interval', g_interval.value);
  d.append('collect_dedup_title', g_dedup.value);
  d.append('collect_img_local', g_imglocal.value);
  d.append('collect_speed', g_speed.value);
  d.append('_csrf','<?= e(Security::csrfToken()) ?>');
  var j=await api('/admin.php?s=/content/collectglobal',d);
  toast(j.msg||'完成',j.code===1);
}
async function runAuto(){
  var d=new FormData();d.append('_csrf','<?= e(Security::csrfToken()) ?>');
  toast('定时采集中,请稍候…');
  var j=await api('/admin.php?s=/content/collectauto',d);
  toast(j.msg||'完成',j.code===1);
  if(j.code===1)setTimeout(()=>location.reload(),1200);
}
</script>
<script>
async function dupScan(){
  var btn=document.getElementById('dupBtn');
  btn.disabled=true;btn.textContent='扫描中…';
  var box=document.getElementById('dupBox');
  box.innerHTML='<p class="hint">正在全库比对片名,请稍候…</p>';
  var j=await api('/admin.php?s=/content/dupscan',new FormData());
  btn.disabled=false;btn.textContent='🔍 重新扫描';
  if(j.code!==1){box.innerHTML='<p style="color:#e5322d;font-size:13px">'+(j.msg||'扫描失败')+'</p>';return}
  var groups=j.data.groups||[];
  if(!groups.length){box.innerHTML='<p class="hint">✓ 未发现疑似重复影片</p>';return}
  var h='<p class="hint">发现 <b style="color:#e5322d">'+groups.length+'</b> 组疑似重复,请为每组选择保留条目后合并:</p>';
  groups.forEach(function(g,gi){
    h+='<div style="border:1px solid var(--line);border-radius:12px;padding:12px;margin-top:10px">';
    h+='<div style="font-size:12px;color:var(--sub);margin-bottom:8px">第 '+(gi+1)+' 组 · '+g.length+' 条</div>';
    g.forEach(function(m,mi){
      var src='播放源 '+m.sources+' 个';
      h+='<label style="display:flex;align-items:center;gap:10px;padding:6px 4px;border-radius:8px;cursor:pointer" onmouseover="this.style.background=\'#f7f8fb\'" onmouseout="this.style.background=\'\'">'
        +'<input type="radio" name="dup'+gi+'" value="'+m.id+'" '+(mi===0?'checked':'')+(m.status?'':' disabled')+'>'
        +'<img src="'+m.pic+'" style="width:26px;height:36px;object-fit:cover;border-radius:4px" onerror="this.style.visibility=\'hidden\'">'
        +'<span style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:13px">'+esc(m.name)+'</span>'
        +'<span style="font-size:12px;color:var(--sub)">'+esc(m.year||'')+'</span>'
        +'<span style="font-size:12px;color:var(--sub)">'+esc(m.remarks)+'</span>'
        +'<span style="font-size:11px;color:#2f6fed">'+src+'</span>'
        +(m.status?'':'<span class="tag r">已隐藏</span>')
        +'<code style="font-size:11px;color:var(--sub)">#'+m.id+'</code></label>';
    });
    var best=g.reduce(function(a,b){return (b.sources>a.sources?b:a)},g[0]);
    h+='<div style="margin-top:8px"><button class="btn sm" onclick="dupMerge('+gi+')">合并其余到 #'+best.id+'</button></div>';
    h+='</div>';
  });
  box.innerHTML=h;
  window.__dupGroups=groups;
}
async function dupMerge(gi){
  var g=window.__dupGroups[gi]||[];
  var keep=document.querySelector('input[name="dup'+gi+'"]:checked');
  if(!keep){toast('请选择保留条目',false);return}
  var ids=[];g.forEach(function(m){if(m.id!==+keep.value)ids.push(m.id)});
  if(!ids.length){toast('该组只有一条',false);return}
  if(!confirmDel('确定将 '+ids.length+' 条合并到 #'+keep.value+'?播放地址将并入,其余删除且不可恢复'))return;
  var d=new FormData();d.append('keep',keep.value);d.append('ids',ids.join(','));
  var j=await api('/admin.php?s=/content/dupmerge',d);
  if(j.code===1){toast(j.msg||'已合并',true);dupScan()}else toast(j.msg,false);
}
</script>
<?php include __DIR__.'/_footer.php'; ?>
