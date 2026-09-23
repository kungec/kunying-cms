/* 坤影CMS 前台交互 */
(function(){
  'use strict';
  // 顶栏滚动
  var header=document.querySelector('.header');
  var toTop=document.querySelector('.totop');
  window.addEventListener('scroll',function(){
    var y=window.scrollY;
    if(header)header.classList.toggle('solid',y>60);
    if(toTop)toTop.classList.toggle('show',y>400);
  });
  if(toTop)toTop.addEventListener('click',function(){window.scrollTo({top:0,behavior:'smooth'})});

  // 抽屉
  var burger=document.querySelector('.burger');
  if(burger)burger.addEventListener('click',function(){document.body.classList.add('drawer-open')});
  var mask=document.querySelector('.drawer-mask');
  if(mask)mask.addEventListener('click',function(){document.body.classList.remove('drawer-open')});

  // Banner轮播
  var hero=document.querySelector('.hero');
  if(hero){
    var slides=hero.querySelectorAll('.slide'),dots=hero.querySelectorAll('.dots i'),cur=0,timer=null;
    function go(i){
      slides.forEach(function(s,idx){s.classList.toggle('on',idx===i)});
      dots.forEach(function(d,idx){d.classList.toggle('on',idx===i)});
      cur=i;
    }
    if(slides.length>1){
      timer=setInterval(function(){go((cur+1)%slides.length)},5200);
      dots.forEach(function(d,i){d.addEventListener('click',function(){clearInterval(timer);go(i);timer=setInterval(function(){go((cur+1)%slides.length)},5200)})});
    }else if(slides.length===1){go(0)}
  }

  // 搜索联想
  var si=document.querySelector('.hsearch input[type=text],.xsg-bar input[type=text]');
  var box=document.querySelector('.hsearch .sg,.xsg-drawer .sg');
  if(si&&box){
    var t=null;
    si.addEventListener('input',function(){
      clearTimeout(t);
      var w=si.value.trim();
      if(!w){box.style.display='none';return}
      t=setTimeout(function(){
        fetch('/index.php?s=/api/suggest&wd='+encodeURIComponent(w)).then(function(r){return r.json()}).then(function(j){
          if(!j.code||!j.data||!j.data.length){box.style.display='none';return}
          sgIdx=-1;sgItems=j.data;
          box.innerHTML=j.data.map(function(v,i){
            var nm=String(v.name||'').replace(/[<>&"]/g,'');
            var k=w.replace(/[<>&"]/g,'');
            var hlName=k?nm.split(k).join('<i class="hl">'+k+'</i>'):nm;
            return '<a href="/index.php?s=/vod/detail&id='+v.id+'" data-i="'+i+'">'
              +'<img src="'+esc(v.pic)+'" loading="lazy" onerror="this.style.visibility=\'hidden\'">'
              +'<span class="sg-i"><b>'+hlName+'</b><em>'
              +(v.remarks?'<i>'+String(v.remarks).replace(/[<>&"\']/g,'')+'</i>':'')
              +(v.year?'<u>'+String(v.year).replace(/[<>&"\']/g,'')+'</u>':'')
              +(v.area?'<u>'+String(v.area).replace(/[<>&"\']/g,'')+'</u>':'')
              +'</em></span></a>';
          }).join('')
          +'<a class="sg-all" href="/index.php?s=/vod/search&wd='+encodeURIComponent(w)+'">查看「'+w.replace(/[<>&"]/g,'')+'」的全部搜索结果 →</a>';
          box.style.display='block';
        }).catch(function(){});
      },300);
    });
    document.addEventListener('click',function(ev){if(!box.contains(ev.target)&&ev.target!==si)box.style.display='none'});
    var sgIdx=-1,sgItems=[];
    si.addEventListener('keydown',function(e){
      var links=box.querySelectorAll('a:not(.sg-all)');
      if(e.key==='Enter'){
        e.preventDefault();
        if(sgIdx>=0&&links[sgIdx]){location.href=links[sgIdx].href;}
        else{si.closest('form').submit();}
        return;
      }
      if(!links.length)return;
      if(e.key==='ArrowDown'||e.key==='ArrowUp'){
        e.preventDefault();
        sgIdx=e.key==='ArrowDown'?Math.min(sgIdx+1,links.length-1):Math.max(sgIdx-1,0);
        links.forEach(function(a,i){a.classList.toggle('on',i===sgIdx)});
        if(links[sgIdx])si.value=sgItems[sgIdx]?sgItems[sgIdx].name:si.value;
      }
    });
  }

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
})();

/* ===== 瀑布流无限加载 ===== */
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
    var self = this;
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
      return '<a class="iqc" href="/index.php?s=/vod/detail&id=' + v.id + '"><div class="pic"><img src="' + this.esc(v.pic) + '" loading="lazy" alt="' + this.esc(v.name) + '">' + tg + vip + '</div><div class="nm">' + this.esc(v.name) + '</div><div class="st">' + this.esc(v.remarks || v.ds || '') + '</div></a>';
    }
    if (this.cardStyle === 'poster') {
      var score = v.score > 0 ? '<span class="bd">' + (Math.round(v.score*10)/10) + '</span>' : '';
      var vip = v.vip ? '<span class="vip">vip</span>' : '';
      var rm = v.remarks ? '<span class="rm">' + this.esc(v.remarks) + '</span>' : '';
      return '<a class="mcard" href="/index.php?s=/vod/detail&id=' + v.id + '"><div class="pic"><img src="' + this.esc(v.pic) + '" loading="lazy" alt="' + this.esc(v.name) + '">' + score + vip + rm + '</div><div class="nm">' + this.esc(v.name) + '</div><div class="ds">' + this.esc(v.ds) + '</div></a>';
    }
    var score = v.score > 0 ? '<span class="bd">' + (Math.round(v.score*10)/10) + '分</span>' : '';
    var vip = v.vip ? '<span class="vip">vip专享</span>' : '';
    var rm = v.remarks ? '<span class="rm">' + this.esc(v.remarks) + '</span>' : '';
    return '<a class="vcard land" href="/index.php?s=/vod/detail&id=' + v.id + '"><div class="pic"><img src="' + this.esc(v.pic) + '" loading="lazy">' + score + vip + rm + '</div><div class="nm">' + this.esc(v.name) + '</div><div class="ds">' + this.esc(v.ds) + '</div></a>';
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

/* ===== 海报网格自动对齐(末行不足整行时隐藏多余,PC/手机通用) ===== */
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
