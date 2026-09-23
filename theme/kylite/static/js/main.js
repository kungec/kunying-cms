/* 坤影CMS 默认主题 kylite 前台交互 */
(function(){
  'use strict';

  // 全局图片兜底:加载失败自动替换为占位图
  document.addEventListener('error', function(e){
    var t = e.target;
    if (t && t.tagName === 'IMG' && t.src.indexOf('nopic') === -1) {
      t.src = '/static/img/nopic.svg';
    }
  }, true);

  // AJAX表单工具
  window.kyPost=function(url,data){
    return fetch(url,{method:'POST',body:data,headers:{'X-Requested-With':'XMLHttpRequest'}}).then(function(r){return r.json()});
  };
  window.kyToast=function(msg,ok){
    var d=document.createElement('div');
    d.style.cssText='position:fixed;top:76px;left:50%;transform:translateX(-50%);z-index:9999;padding:11px 26px;border-radius:10px;font-size:14px;color:#fff;box-shadow:0 10px 30px rgba(0,0,0,.35);background:'+(ok===false?'#e5322d':'#1f9d55');
    d.textContent=msg;document.body.appendChild(d);
    setTimeout(function(){d.remove()},2400);
  };

  // 顶栏搜索联想(全站生效:所有页面header均含#hdrWd)
  var si=document.getElementById('hdrWd');
  var box=document.getElementById('hdrSg');
  if(si&&box){
    var t=null;
    function esc(s){ return String(s||'').replace(/[<>&"]/g,''); }
    function render(w,j){
      if(!j.code||!j.data||!j.data.length){box.style.display='none';return}
      box.innerHTML=j.data.map(function(v){
        var nm=esc(v.name);
        var hl=w?nm.split(w).join('<i>'+w+'</i>'):nm;
        return '<a href="/index.php?s=/vod/detail&id='+v.id+'">'
          +'<img src="'+v.pic+'" loading="lazy" onerror="this.style.visibility=\'hidden\'">'
          +'<span class="sg-i"><b>'+hl+'</b><em>'+esc(v.remarks||'')+'</em></span></a>';
      }).join('')
      +'<a class="ksg-all" href="/index.php?s=/vod/search&wd='+encodeURIComponent(w)+'">查看「'+esc(w)+'」的全部搜索结果 →</a>';
      box.style.display='block';
    }
    si.addEventListener('input',function(){
      clearTimeout(t);
      var w=si.value.trim();
      if(!w){box.style.display='none';return}
      t=setTimeout(function(){
        fetch('/index.php?s=/api/suggest&wd='+encodeURIComponent(w)).then(function(r){return r.json()}).then(function(j){render(w,j)}).catch(function(){});
      },300);
    });
    si.addEventListener('keydown',function(e){
      if(e.key==='Enter'){
        var first=box.querySelector('a');
        if(first&&box.style.display==='block'){e.preventDefault();location.href=first.href}
      }
      if(e.key==='Escape'){box.style.display='none'}
    });
    document.addEventListener('click',function(ev){if(!box.contains(ev.target)&&ev.target!==si)box.style.display='none'});
  }
})();

/* ===== 瀑布流无限加载(分类页/搜索页"加载更多") ===== */
var WF = {
  grid:null, next:2, hasMore:true, loading:false, base:'',
  init:function(opt){
    this.grid = document.querySelector(opt.grid);
    if(!this.grid) return;
    this.next = opt.next || 2;
    this.base = opt.base;
    this.mode = opt.mode || 'home';
    this.cardStyle = opt.cardStyle || 'land';
    this.typeId = opt.typeId || 0;
    this.order = opt.order || 'time';
    this.cls = opt.cls || ''; this.year = opt.year || ''; this.area = opt.area || '';
  },
  url:function(){
    var q = 's=/api/more&mode=' + this.mode + '&page=' + this.next + '&order=' + this.order;
    if (this.mode === 'type') { q += '&type_id=' + this.typeId; if(this.cls) q += '&class=' + encodeURIComponent(this.cls); if(this.year) q += '&year=' + encodeURIComponent(this.year); if(this.area) q += '&area=' + encodeURIComponent(this.area); }
    return '/index.php?' + q;
  },
  esc:function(s){ return String(s||'').replace(/[<>&"]/g,''); },
  card:function(v){
    if (this.cardStyle === 'iq') {
      var vip = v.vip ? '<span class="vip">vip专享</span>' : '';
      var tg = v.year ? '<span class="tag">' + this.esc(v.year) + '</span>' : '';
      return '<a class="iqc" href="/index.php?s=/vod/detail&id=' + v.id + '"><div class="pic"><img src="' + v.pic + '" loading="lazy" alt="' + this.esc(v.name) + '">' + tg + vip + '</div><div class="nm">' + this.esc(v.name) + '</div><div class="st">' + this.esc(v.remarks || v.ds || '') + '</div></a>';
    }
    var score = v.score > 0 ? '<span class="bd">' + (Math.round(v.score*10)/10) + '</span>' : '';
    var vip = v.vip ? '<span class="vip">vip</span>' : '';
    var rm = v.remarks ? '<span class="rm">' + this.esc(v.remarks) + '</span>' : '';
    return '<a class="mcard" href="/index.php?s=/vod/detail&id=' + v.id + '"><div class="pic"><img src="' + v.pic + '" loading="lazy" alt="' + this.esc(v.name) + '">' + score + vip + rm + '</div><div class="nm">' + this.esc(v.name) + '</div><div class="ds">' + this.esc(v.ds) + '</div></a>';
  },
  load:function(){
    var self = this; this.loading = true;
    fetch(this.url()).then(function(r){ return r.json(); }).then(function(j){
      if(!j.code){ self.hasMore = false; self.loading = false; return; }
      self.hasMore = !!j.data.has_more; self.next = j.data.next_page || (self.next + 1);
      var html = '';
      (j.data.list || []).forEach(function(v){ html += self.card(v); });
      if (html) self.grid.insertAdjacentHTML('beforeend', html);
      if (!j.data.has_more && self.grid) {
        var tip = document.createElement('p'); tip.style.cssText='text-align:center;color:var(--sub);padding:18px 0'; tip.textContent = '— 已经到底啦 —';
        self.grid.after(tip);
      }
      if (typeof kyAlignGrids === 'function') kyAlignGrids();
      self.loading = false;
    }).catch(function(){ self.loading = false; });
  },
  bindButton:function(){
    var b = document.getElementById('wfBtn');
    if (b) b.addEventListener('click', function(){ WF.load(); });
  }
};
// 页面声明 window.wfConfig 后自动初始化
if (window.wfConfig) {
  WF.init(window.wfConfig);
  WF.bindButton();
  window.addEventListener('scroll', function(){
    var b = document.getElementById('wfBtn');
    if (b && !WF.hasMore) b.style.display = 'none';
  });
}

/* ===== 海报网格自动对齐(末行不足整行时隐藏多余) ===== */
function kyAlignGrids(){
  document.querySelectorAll('.mgrid,.iqgrid').forEach(function(g){
    var cards = Array.prototype.slice.call(g.children);
    if(!cards.length) return;
    cards.forEach(function(c){ c.style.display=''; });
    var cols = getComputedStyle(g).gridTemplateColumns.split(' ').filter(Boolean).length;
    if(cols < 2) return;
    var keep = Math.max(cols, Math.floor(cards.length / cols) * cols);
    for(var i = keep; i < cards.length; i++) cards[i].style.display = 'none';
  });
}
window.addEventListener('DOMContentLoaded', kyAlignGrids);
window.addEventListener('load', kyAlignGrids);
var _kyAlignT;
window.addEventListener('resize', function(){ clearTimeout(_kyAlignT); _kyAlignT = setTimeout(kyAlignGrids, 200); });

/* ─── 暗色模式切换(右下角悬浮球) ─── */
(function(){
  document.addEventListener('DOMContentLoaded', function(){
    if (document.getElementById('ky-theme-fab')) return;
    var btn = document.createElement('div');
    btn.id = 'ky-theme-fab';
    btn.title = '切换暗色模式';
    btn.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>';
    btn.onclick = function(){
      var h = document.documentElement;
      var dark = h.getAttribute('data-theme') === 'dark';
      if (dark) { h.removeAttribute('data-theme'); localStorage.setItem('ky_theme','light'); }
      else { h.setAttribute('data-theme','dark'); localStorage.setItem('ky_theme','dark'); }
      updateFabIcon();
    };
    document.body.appendChild(btn);
    updateFabIcon();
  });
  function updateFabIcon(){
    var fab = document.getElementById('ky-theme-fab');
    if (!fab) return;
    var dark = document.documentElement.getAttribute('data-theme') === 'dark';
    fab.innerHTML = dark
      ? '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.2" y1="4.2" x2="5.6" y2="5.6"/><line x1="18.4" y1="18.4" x2="19.8" y2="19.8"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.2" y1="19.8" x2="5.6" y2="18.4"/><line x1="18.4" y1="5.6" x2="19.8" y2="4.2"/></svg>'
      : '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>';
  }
})();
