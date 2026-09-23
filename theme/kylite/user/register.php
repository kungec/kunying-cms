<?php
/** 注册页(邮箱验证) */
$pageTitle = '注册';
include theme_path('layout/header.php');
?>
<div class="auth-wrap">
  <form class="auth" onsubmit="return doRegister(event)">
    <div class="brand">
      <svg viewBox="0 0 24 24" width="44" height="44"><path d="M4 3l16 9-16 9z" fill="#ff5a5f"/><path d="M4 3l9 9-9 9z" fill="#2f7df6"/><path d="M13 12l7-9v18z" fill="#ffb400"/></svg>
      <b><?= e(config('site_name', '坤影影视')) ?></b>
      <span>注册账号 · 开启观影之旅</span>
    </div>
    <div class="afield"><label>邮箱</label><input type="email" name="email" id="rEmail" required placeholder="example@qq.com"></div>
    <div class="afield row"><label>邮箱验证码</label>
      <div class="arow">
        <input type="text" name="code" id="rCode" required placeholder="6位数字验证码" autocomplete="one-time-code">
        <button type="button" class="abtn sub" id="sendBtn" onclick="sendCode()">获取验证码</button>
      </div>
    </div>
    <div class="afield"><label>昵称(选填)</label><input type="text" name="name" placeholder="给自己起个名字"></div>
    <div class="afield"><label>密码</label><input type="password" name="password" required placeholder="设置密码(至少6位)"></div>
    <div class="afield"><label>确认密码</label><input type="password" name="repassword" required placeholder="再次输入密码"></div>
    <?= Verify::render('user_register', 'light') ?>
    <input type="hidden" name="_csrf" value="<?= e(Security::csrfToken()) ?>">
    <button class="abtn" type="submit" id="go">注 册</button>
    <a class="abtn ghost" href="/user/login">已有账号?去登录</a>
  </form>
</div>
<script>
var cd=0;
function sendCode(){
  var em=document.getElementById('rEmail').value.trim();
  if(!em)return kyToast('请先填写邮箱',false);
  if(cd>0)return;
  var d=new FormData();d.append('email',em);d.append('_csrf','<?= e(Security::csrfToken()) ?>');
  kyPost('/index.php?s=/user/sendcode',d).then(function(j){
    kyToast(j.msg,j.code===1);
    if(j.code===1){cd=60;var b=document.getElementById('sendBtn');var t=setInterval(function(){cd--;b.textContent=cd+'s';if(cd<=0){clearInterval(t);b.textContent='获取验证码'}},1000)}
  });
}
async function doRegister(ev){
  ev.preventDefault();
  var go=document.getElementById('go');go.disabled=true;
  var j=await kyPost('/index.php?s=/user/register',new FormData(ev.target));
  if(j.code===1){kyToast('注册成功');location.href=j.data.redirect}
  else{kyToast(j.msg,false);go.disabled=false}
  return false;
}
</script>
<?php include theme_path('layout/footer.php'); ?>
