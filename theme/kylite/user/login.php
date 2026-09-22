<?php
/** 登录页 */
$pageTitle = '登录';
include theme_path('layout/header.php');
?>
<div class="auth-wrap">
  <form class="auth" onsubmit="return doLogin(event)">
    <div class="brand">
      <svg viewBox="0 0 24 24" width="44" height="44"><path d="M4 3l16 9-16 9z" fill="#ff5a5f"/><path d="M4 3l9 9-9 9z" fill="#2f7df6"/><path d="M13 12l7-9v18z" fill="#ffb400"/></svg>
      <b><?= e(config('site_name', '坤影影视')) ?></b>
      <span>海量影视 · 极速播放</span>
    </div>
    <div class="afield"><label>邮箱</label><input type="email" name="email" required autocomplete="email" placeholder="example@qq.com"></div>
    <div class="afield"><label>密码</label><input type="password" name="password" required autocomplete="current-password" placeholder="输入密码"></div>
    <?= Verify::render('user_login', 'light') ?>
    <input type="hidden" name="_csrf" value="<?= e(Security::csrfToken()) ?>">
    <button class="abtn" type="submit" id="go">登 录</button>
    <?php if (config('register_enable', '1') == '1'): ?><a class="abtn ghost" href="/user/register">没有账号?立即注册</a><?php endif; ?>
    <p class="aalt">同一账号同IP连续失败5次将锁定1小时</p>
  </form>
</div>
<script>
async function doLogin(ev){
  ev.preventDefault();
  var go=document.getElementById('go');go.disabled=true;go.textContent='登录中…';
  try{
    var j=await kyPost('/index.php?s=/user/login',new FormData(ev.target));
    if(j.code===1){location.href=j.data.redirect}else{kyToast(j.msg,false);go.disabled=false;go.textContent='登 录';var img=document.querySelector('.ky-captcha-img');if(img)img.click()}
  }catch(e){kyToast('网络异常',false);go.disabled=false}
  return false;
}
</script>
<?php include theme_path('layout/footer.php'); ?>
