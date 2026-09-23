/**
 * 坤影播放器 KunPlayer (内置)
 * 支持直连 m3u8(HLS)/mp4,基于 hls.js;记忆播放位置;自动下一集
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
    var self=this;
    var p=v.play();
    if(p&&p.catch){ p.catch(function(){
      // 浏览器拦截带声自动播放:静音起播+提示开声
      v.muted=true;
      var p2=v.play();
      if(p2&&p2.catch)p2.catch(function(){});
      self._showUnmute(v,box);
    }); }
  }
  if(o.autoplay){ var pp=v.play(); if(pp&&pp.catch)pp.catch(function(){}); }
  // 键盘快捷键
  document.addEventListener('keydown',function(e){
    if(['INPUT','TEXTAREA'].indexOf(document.activeElement.tagName)>=0)return;
    if(e.code==='Space'){e.preventDefault();v.paused?v.play():v.pause()}
    if(e.code==='ArrowRight')v.currentTime+=10;
    if(e.code==='ArrowLeft')v.currentTime-=10;
  });
};
KunPlayer.prototype._load=function(src,type){
  var o=this.opts,v=this.video;
  if(!src)return;
  var isM3u8 = type==='m3u8' || type==='hls' || /m3u8/i.test(src);
  if(isM3u8&&window.Hls&&Hls.isSupported()){
    if(this.hls)this.hls.destroy();
    this.hls=new Hls({maxBufferLength:30,maxMaxBufferLength:60});
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
