<?php $pageTitle = '登录'; ?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>登录 - 坤影CMS管理后台</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;font-family:-apple-system,"PingFang SC","Microsoft YaHei",sans-serif;background:radial-gradient(900px 500px at 70% -10%,rgba(229,50,45,.18),transparent),#191b22;color:#242a37}
.card{width:100%;max-width:390px;background:#fff;border-radius:14px;padding:34px;box-shadow:0 24px 70px rgba(0,0,0,.45)}
.brand{display:flex;align-items:center;gap:10px;margin-bottom:22px}
.brand .i{width:30px;height:30px;background:#e5322d;border-radius:50%;display:flex;align-items:center;justify-content:center;flex:none}
.brand .i::after{content:"";border-left:9px solid #fff;border-top:6px solid transparent;border-bottom:6px solid transparent;margin-left:3px}
.brand b{font-size:17px}
.fi{margin-bottom:14px}
.fi label{display:block;font-size:13px;color:#7b8394;margin-bottom:6px}
input[type=text],input[type=password]{width:100%;padding:11px 13px;border:1px solid #e6e9f0;border-radius:8px;font-size:14px;outline:none;font-family:inherit}
input:focus{border-color:#2f6bff}
.captcha-row{display:flex;gap:8px;align-items:stretch}
.captcha-row input{flex:1}
.captcha-row img{height:44px;border-radius:8px;cursor:pointer;border:1px solid #e6e9f0}
.btn{width:100%;padding:12px;background:#e5322d;color:#fff;border:0;border-radius:9px;font-size:15px;font-weight:700;cursor:pointer;margin-top:6px}
.btn[disabled]{opacity:.55}
.hint{text-align:center;font-size:12px;color:#7b8394;margin-top:14px}
.err{display:none;background:#fdecec;color:#c53030;border-radius:8px;padding:9px 12px;font-size:13px;margin-bottom:12px}

/* 人机验证自适应(极验v4/Turnstile/图形) */
.ky-verify{width:100%;border-radius:8px;overflow:hidden}
.ky-verify .geetest_box,.ky-verify .geetest_holder,.ky-verify .geetest_panel,.ky-verify .geetest_widget,.ky-verify .geetest_btn,.ky-verify .geetest_window{max-width:100%!important;border-radius:8px!important;overflow:hidden}
.ky-verify .geetest_btn,.ky-verify .geetest_holder{border:1px solid #e6e9f0!important;box-shadow:none!important}
.ky-verify .geetest_btn,.ky-verify .geetest_holder{width:100%!important;height:44px!important}
.ky-verify iframe,.ky-verify .cf-turnstile,.ky-verify .cf-turnstile iframe{max-width:100%!important}
</style>
</head>
<body>
<form class="card" onsubmit="return doLogin(event)">
  <div class="brand"><div class="i"></div><b>坤影CMS 管理登录</b></div>
  <div class="err" id="err"></div>
  <div class="fi"><label>管理员账号</label><input type="text" name="username" required autocomplete="username"></div>
  <div class="fi"><label>密码</label><input type="password" name="password" required autocomplete="current-password"></div>
  <div class="fi"><?= Verify::render('admin_login', 'light') ?></div>
  <input type="hidden" name="_csrf" value="<?= e(Security::csrfToken()) ?>">
  <button class="btn" type="submit" id="go">登 录</button>
  <p class="hint">同一账号同IP连续失败5次将锁定1小时</p>
</form>
<script>
async function api(url,data){
  var r=await fetch(url,{method:'POST',body:data,headers:{'X-Requested-With':'XMLHttpRequest'}});
  var t=await r.text();
  try{return JSON.parse(t)}catch(e){return {code:0,msg:'响应异常'}}
}
function toast(msg){
  var e=document.getElementById('err');e.style.display='block';e.textContent=msg;
}
async function doLogin(ev){
  ev.preventDefault();
  var go=document.getElementById('go');go.disabled=true;go.textContent='登录中…';
  var j=await api('/admin.php?s=/main/login', new FormData(ev.target));
  if(j.code===1){ location.href=j.data.redirect; }
  else{ toast(j.msg||'登录失败'); go.disabled=false; go.textContent='登 录';
    var img=document.querySelector('.ky-captcha-img'); if(img){img.click()} }
  return false;
}
</script>
</body>
</html>
