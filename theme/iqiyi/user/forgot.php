<?php
/** 忘记密码 */
$pageTitle = '找回密码';
include theme_path('layout/header.php');
?>
<div class="auth-wrap">
  <form class="auth" onsubmit="return doForgot(event)">
    <h2><span class="i"></span>找回密码<?= e(config('site_name', '坤影影视')) ?></h2>
    <div class="fi" style="display:flex;gap:8px">
      <input class="ky-input" type="email" name="email" required autocomplete="email" placeholder="example@qq.com" style="flex:1">
      <button class="btn-main" type="button" style="width:auto;padding:0 16px;margin:0;font-size:13px" id="snd">发送重置码</button>
    </div>
    <div class="fi"><input class="ky-input" type="text" name="code" required maxlength="6" placeholder="6位邮箱重置码" style="letter-spacing:3px"></div>
    <div class="fi"><input class="ky-input" type="password" name="password" required minlength="6" autocomplete="new-password" placeholder="设置新密码(至少6位)"></div>
    <div class="fi"><input class="ky-input" type="password" name="repassword" required minlength="6" autocomplete="new-password" placeholder="再次输入新密码"></div>
    <input type="hidden" name="_csrf" value="<?= e(Security::csrfToken()) ?>">
    <button class="btn-main" type="submit" id="go" style="margin-top:6px">重置密码</button>
    <a class="btn-line" href="/user/login" style="margin-top:6px">想起来了?去登录</a>
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
    if(j.code===1){cd=60;cdTick()}
    else{b.disabled=false;b.textContent='发送重置码'}
  }catch(e){kyToast('网络异常',false);b.disabled=false}
});
document.getElementById('snd').addEventListener('click',function(){},true);
async function doForgot(ev){
  ev.preventDefault();
  var go=document.getElementById('go');go.disabled=true;go.textContent='提交中…';
  try{
    var j=await kyPost('/index.php?s=/user/forgot',new FormData(ev.target));
    if(j.code===1){kyToast('密码已重置,请登录');setTimeout(function(){location.href='/user/login'},900)}
    else{kyToast(j.msg,false);go.disabled=false}
  }catch(e){kyToast('网络异常',false);go.disabled=false}
  return false;
}
</script>
<?php include theme_path('layout/footer.php'); ?>
