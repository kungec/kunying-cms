/**
 * 坤影播放器 KunPlayer (内置)
 * 支持直连 m3u8(HLS)/mp4,基于 hls.js;记忆播放位置;自动下一集;投屏
 */
function KunPlayer(opts){
  this.opts=Object.assign({container:null,src:'',type:'',poster:'',start:0,nextUrl:'',vodId:0,episode:1,onProgress:null},opts);
  this.video=null;
  this.hls=null;
  this._init();
}
KunPlayer.prototype._init=function(){
  var o=this.opts,box=typeof o.container==='string'?document.querySelector(o.container):o.container;
  if(!box)return;
  var v=document.createElement('video');
  v.controls=true;v.playsInline=true;v.setAttribute('playsinline','');v.preload='metadata';
  if(o.poster)v.poster=o.poster;
  box.appendChild(v);
  this.video=v;
  var self=this;
  v.addEventListener('loadedmetadata',function(){
    if(o.start>5&&o.start<v.duration-30){try{v.currentTime=o.start}catch(e){}}
  });
  v.addEventListener('ended',function(){ if(o.onNext)o.onNext(); else if(o.nextUrl)location.href=o.nextUrl; });
  // 流错误上报:直连mp4/原生HLS失败
  v.addEventListener('error',function(){ if(o.onStreamError)o.onStreamError(); });
  // 进度上报
  var lastSend=0;
  v.addEventListener('timeupdate',function(){
    var now=Date.now();
    if(now-lastSend>15000&&v.currentTime>5){
      lastSend=now;
      if(o.onProgress)o.onProgress(Math.floor(v.currentTime));
    }
  });
  this._load(o.src,o.type);
  if(o.autoplay){
    var p=v.play();
    if(p&&p.catch){ p.catch(function(){
      // 浏览器拦截带声自动播放:静音起播+提示开声
      v.muted=true;
      var p2=v.play();
      if(p2&&p2.catch)p2.catch(function(){});
      self._showUnmute(v,box);
    }); }
  }
  // 键盘快捷键
  document.addEventListener('keydown',function(e){
    if(['INPUT','TEXTAREA'].indexOf(document.activeElement.tagName)>=0)return;
    if(e.code==='Space'){e.preventDefault();v.paused?v.play():v.pause()}
    if(e.code==='ArrowRight')v.currentTime+=10;
    if(e.code==='ArrowLeft')v.currentTime-=10;
  });
  this._enhance(v,box);
};
KunPlayer.prototype._load=function(src,type){
  var o=this.opts,v=this.video;
  if(!src)return;
  // 无扩展名的播放页地址(如 jisyuzv 系):按资源站规律补 /index.m3u8
  if (!/\.[a-z0-9]{2,5}(\?|#|$)/i.test(src) && !/m3u8/i.test(src)) {
    src = src.replace(/\/?$/, '/index.m3u8');
  }
  var isM3u8 = type==='m3u8' || type==='hls' || /m3u8/i.test(src);
  var ua = navigator.userAgent;
  var isIOS = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  var isSafari = /Safari/i.test(ua) && !/Chrome|CriOS|FxiOS|EdgiOS|Edg\//i.test(ua);
  // iOS/iPadOS Safari 优先原生HLS:性能更好且支持AirPlay投屏
  if(isM3u8 && window.Hls && Hls.isSupported() && !(isIOS && isSafari)){
    if(this.hls)this.hls.destroy();
    this.hls=new Hls({maxBufferLength:60,maxMaxBufferLength:180,maxBufferSize:120*1000*1000,backBufferLength:45,fragLoadingTimeOut:40000,fragLoadingMaxRetry:8,manifestLoadingTimeOut:25000,startLevel:-1,abrEwmaDefaultEstimate:2000000});
    var slf=this;this._errFired=false;
    this.hls.on(Hls.Events.ERROR,function(e,data){ if(data&&data.fatal&&!slf._errFired){ slf._errFired=true; if(o.onStreamError)o.onStreamError(); } });
    this.hls.loadSource(src);
    this.hls.attachMedia(v);
  }else{
    v.src=src;
  }
};
KunPlayer.prototype.destroy=function(){
  if(this.hls){try{this.hls.destroy()}catch(e){} this.hls=null;}
  if(this.video){try{this.video.pause()}catch(e){} this.video.removeAttribute('src'); this.video.load();}
};
KunPlayer.prototype._showUnmute=function(v,box){
  if(!box||box.querySelector('.ky-unmute'))return;
  var b=document.createElement('button');
  b.className='ky-unmute';
  b.innerHTML='\uD83D\uDD07 \u70B9\u51FB\u5F00\u542F\u58F0\u97F3';
  b.addEventListener('click',function(e){ e.stopPropagation(); v.muted=false; v.volume=1; if(b.parentNode)b.parentNode.removeChild(b); });
  box.appendChild(b);
};
/* ===== 播放器增强:投屏(移动端自动搜索WiFi设备) ===== */
KunPlayer.prototype._enhance=function(v,box){
  if(!box||box.querySelector('.ky-plx'))return;
  var o=this.opts;
  if(getComputedStyle(box).position==='static')box.style.position='relative';
  if(!document.getElementById('ky-plx-style')){
    var st=document.createElement('style');st.id='ky-plx-style';
    st.textContent='.ky-plx{position:absolute;top:10px;right:10px;z-index:30;display:flex;gap:8px}'
      +'.ky-plx-btn{background:rgba(0,0,0,.55);color:#fff;border:1px solid rgba(255,255,255,.28);border-radius:16px;padding:6px 16px;font-size:13px;cursor:pointer;backdrop-filter:blur(4px);font-family:inherit;transition:opacity .5s}'
      +'.ky-plx-btn:hover{background:rgba(229,50,45,.85);border-color:#e5322d}'
      +'.ky-plx-btn.hide{opacity:0;pointer-events:none}';
    document.head.appendChild(st);
  }
  var wrap=document.createElement('div');wrap.className='ky-plx';
  var cast=document.createElement('button');cast.className='ky-plx-btn';cast.textContent='投屏';
  wrap.appendChild(cast);
  box.appendChild(wrap);

  // 10秒无操作自动隐藏;触摸/点击/移动鼠标重新显示
  var hideT=null;
  function poke(){
    cast.classList.remove('hide');
    if(hideT)clearTimeout(hideT);
    hideT=setTimeout(function(){ cast.classList.add('hide'); },10000);
  }
  poke();
  v.addEventListener('touchstart',poke,{passive:true});
  v.addEventListener('click',poke);
  box.addEventListener('mousemove',poke);
  box.addEventListener('touchstart',poke,{passive:true});

  cast.addEventListener('click',function(e){
    e.stopPropagation();
    var src=v.currentSrc||o.src||'';
    var isMobile=/Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
    // 1) Chrome安卓/iPadOS: Remote Playback 自动搜索同一WiFi下的投屏设备
    try{
      if(v.remote&&typeof v.remote.prompt==='function'){
        v.remote.prompt().then(function(){},function(){
          // 2) Safari/iOS: AirPlay 隔空播放设备列表
          try{ if(typeof v.webkitShowPlaybackTargetPicker==='function'){v.webkitShowPlaybackTargetPicker();return;} }catch(err2){}
          fallback(src,isMobile);
        });
        return;
      }
    }catch(err){}
    // 2) Safari/iOS: AirPlay
    try{
      if(typeof v.webkitShowPlaybackTargetPicker==='function'){v.webkitShowPlaybackTargetPicker();return;}
    }catch(err){}
    fallback(src,isMobile);
  });
  function fallback(src,isMobile){
    var tip=isMobile
      ?'未找到可投屏设备。\n\n请确认:手机与电视连接同一WiFi,且电视支持投屏(Chromecast/AirPlay/DLNA)。\n\n也可以复制播放地址,在电视自带浏览器中打开播放:'
      :'当前浏览器不支持一键投屏(请使用手机Chrome/Safari)。\n\n可复制播放地址,在智能电视浏览器中打开:';
    prompt(tip,src);
  }
};
