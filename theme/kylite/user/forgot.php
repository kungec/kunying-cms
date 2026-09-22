<?php
/** 忘记密码 */
$pageTitle = '找回密码';
include theme_path('layout/header.php');
?>
<div class="auth-wrap">
  <form class="auth" onsubmit="return doForgot(event)">
    <div class="brand">
      <svg viewBox="0 0 24 24" width="44" height="44"><path d="M4 3l16 9-16 9z" fill="#ff5a5f"/><path d="M4 3l9 9-9 9z" fill="#2f7df6"/><path d="M13 12l7-9v18z" fill="#ffb400"/></svg>
      <b>找回密码</b>
      <span>通过注册邮箱验证重置</span>
    </div>
    <div class="afield"><label>注册邮箱</label><input type="email" name="email" required autocomplete="email" placeholder="example@qq.com"></div>
    <div class="afield" style="display:flex;gap:8px">
      <input class="ky-input" type="text" name="code" required maxlength="6" placeholder="邮箱重置码" style="flex:1;letter-spacing:3px">
      <button class="abtn ghost" type="button" id="snd" style="white-space:nowrap;padding:0 14px">发送重置码</button>
    </div>
    <div class="afield"><label>新密码</label><input type="password" name="password" required minlength="6" autocomplete="new-password" placeholder="至少6位"></div>
    <div class="afield"><label>确认新密码</label><input type="password" name="repassword" required minlength="6" autocomplete="new-password" placeholder="再输入一次"></div>
    <input type="hidden" name="_csrf" value="<?= e(Security::csrfToken()) ?>">
    <button class="abtn" type="submit" id="go">重置密码</button>
    <a class="abtn ghost" href="/user/login">想起来了?去登录</a>
  </form>
</div>
<script>
var cd=0,cdT=null;
function cdTick(){var b=document.getElementById('snd');if(cd<=0){b.disabled=false;b.textContent='发送重置码';return}b.disabled=true;b.textContent=cd+'秒';cd--;cdT=setTimeout(cdTick,1000)}
document.getElementById('snd').addEventListener('click',async function(){
  var f=this.closest('form'),em=f.email.value.trim();
  if(!em)return kyToast('请先填写注册邮箱',false);
  var b=this;b.disabled=true;b.textContent='发送中…';
  var d=new FormData();d.append('step','send');d.append('email',em);d.append('_csrf','<?= e(Security::csrfToken()) ?>');
  try{
    var j=await kyPost('/index.php?s=/user/forgot',d);
    kyToast(j.msg,j.code===1);
    if(j.code===1){cd=60;cdTick()}else{b.disabled=false;b.textContent='发送重置码'}
  }catch(e){kyToast('网络异常',false);b.disabled=false;b.textContent='发送重置码'}
});
async function doForgot(ev){
  ev.preventDefault();
  var go=document.getElementById('go');go.disabled=true;go.textContent='重置中…';
  try{
    var j=await kyPost('/index.php?s=/user/forgot',new FormData(ev.target));
    if(j.code===1){kyToast(j.msg,true);setTimeout(function(){location.href=j.data.redirect},800)}
    else{kyToast(j.msg,false);go.disabled=false;go.textContent='重置密码'}
  }catch(e){kyToast('网络异常',false);go.disabled=false}
  return false;
}
</script>
<?php include theme_path('layout/footer.php'); ?>
