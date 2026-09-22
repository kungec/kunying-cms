<?php
include __DIR__.'/_header.php';
$pageTitle='系统设置';
$c = fn(string $k, string $d = '') => e((string)config($k, $d));
$cs = fn(string $k, string $v) => (string)config($k, '') === $v;
$themes = [];
foreach ((scandir(KY_PATH.'/theme') ?: []) as $d) {
    if ($d !== '.' && $d !== '..' && is_dir(KY_PATH.'/theme/'.$d) && is_file(KY_PATH.'/theme/'.$d.'/config.php')) $themes[] = $d;
}
?>
<div class="card">
  <div class="tabs" id="tabs">
    <a class="on" data-t="site">站点信息</a>
    <a data-t="member">会员/注册</a>
    <a data-t="verify">验证码</a>
    <a data-t="smtp">邮件服务</a>
    <a data-t="pay">支付设置</a>
    <a data-t="cdn">CDN加速</a>
    <a data-t="ad">广告设置</a>
    <a data-t="safe">安全</a>
  </div>
  <form id="f" onsubmit="return saveAll(event)">
  <div class="tp" id="t-site">
    <div class="form">
      <div class="row">
        <div class="fi"><label>网站名称</label><input type="text" name="site_name" value="<?= $c('site_name', '坤影影视') ?>"></div>
        <div class="fi"><label>备案号(页脚显示)</label><input type="text" name="site_icp" value="<?= $c('site_icp') ?>"></div>
      </div>
      <div class="fi"><label>SEO关键词</label><input type="text" name="site_keywords" value="<?= $c('site_keywords') ?>"></div>
      <div class="fi"><label>SEO描述</label><textarea name="site_description"><?= $c('site_description') ?></textarea></div>
      <div class="fi"><label>影视站模式</label><select name="site_mode">
        <option value="cms" <?= (string)config('site_mode', 'cms') === 'cms' ? 'selected' : '' ?>>CMS模式(板块+翻页)</option>
        <option value="waterfall" <?= (string)config('site_mode', 'cms') === 'waterfall' ? 'selected' : '' ?>>瀑布流模式(无限滚动)</option>
        <option value="movie" <?= (string)config('site_mode', 'cms') === 'movie' ? 'selected' : '' ?>>电影模式(海报墙)</option>
      </select><div class="hint">三种布局模式:CMS=分类板块+翻页;瀑布流=无限滚动;电影模式=大海报墙。爱奇艺风格已升级为独立主题模板,请在 拓展→市场→模板 中安装启用。</div></div>
      <div class="fi"><label>默认模板</label><select name="site_template">
        <?php $proOn = License::check('pro'); foreach ($themes as $th):
            $tCfg = is_file(KY_PATH . '/theme/' . $th . '/config.php') ? include KY_PATH . '/theme/' . $th . '/config.php' : [];
            $tNm = $tCfg['name'] ?? $th;
            $tPaid = in_array($th, ['kunpro', 'iqiyi'], true);
            $tOk = License::check($th) || ($tPaid && $proOn);
        ?><option value="<?= e($th) ?>" <?= (string)config('site_template', 'kylite') === $th ? 'selected' : '' ?>><?= e($tNm) ?> (<?= e($th) ?>)<?= $tPaid ? ($tOk ? ' ✓专业版' : ' - 需开通专业版') : '' ?></option><?php endforeach; ?>
      </select><div class="hint">KUNPRO 与 爱奇艺风格 为付费主题:开通专业版会员(99元)后全部免费使用。开通方式:授权站购买专业版获取激活码 → 拓展→市场→激活码激活。</div></div>
      <div class="fi"><label>播放全局解析(选填,需含{url},对所有来源生效优先级低于播放器级)</label><input type="text" name="player_parse" value="<?= $c('player_parse') ?>" placeholder="https://jx.xxx.com/?url={url}"></div>
      <div class="fi"><label>视频自动播放</label><select name="player_autoplay"><option value="1" <?= $cs('player_autoplay', '1') ? 'selected' : '' ?>>开启(被浏览器拦截时自动静音播放,可一键开声)</option><option value="0" <?= $cs('player_autoplay', '0') ? 'selected' : '' ?>>关闭</option></select></div>
    </div>
  </div>
  <div class="tp" id="t-member" style="display:none">
    <div class="form">
      <div class="fi"><label>会员系统</label><select name="member_enable"><option value="1" <?= $cs('member_enable', '1') ? 'selected' : '' ?>>开启</option><option value="0" <?= $cs('member_enable', '0') ? 'selected' : '' ?>>关闭(游客可看全部免费内容)</option></select></div>
      <div class="fi"><label>注册功能</label><select name="register_enable"><option value="1" <?= $cs('register_enable', '1') ? 'selected' : '' ?>>开启</option><option value="0" <?= $cs('register_enable', '0') ? 'selected' : '' ?>>关闭(禁止新用户注册)</option></select></div>
      <div class="hint" style="margin-bottom:14px">· 注册必须通过邮箱验证码验证 · 登录同一账号同IP失败5次封禁1小时(后台同理)</div>
      <div class="fi"><label>评论审核</label><select name="comment_audit"><option value="0" <?= $cs('comment_audit', '0') == '0' ? 'selected' : '' ?>>关闭(评论直接显示)</option><option value="1" <?= $cs('comment_audit', '1') == '1' ? 'selected' : '' ?>>开启(评论需后台审核通过后显示)</option></select></div>
      <div class="fi"><label>评论功能</label><select name="comment_enable"><option value="1" <?= $cs('comment_enable', '1') ? 'selected' : '' ?>>开启</option><option value="0" <?= $cs('comment_enable', '0') ? 'selected' : '' ?>>关闭</option></select></div>
      <div class="row">
        <div class="fi"><label>每日签到积分</label><input type="number" name="points_sign" value="<?= $c('points_sign', '5') ?>"></div>
        <div class="fi"><label>注册赠送积分</label><input type="number" name="points_register" value="<?= $c('points_register', '0') ?>"></div>
        <div class="fi"><label>充值比例(1元=N积分)</label><input type="number" name="points_pay_rate" value="<?= $c('points_pay_rate', '10') ?>"></div>
      </div>
      <div class="fi"><label>每日凌晨清理未引用图片</label><select name="img_auto_clean_enable"><option value="1" <?= $cs('img_auto_clean_enable', '0') == '1' ? 'selected' : '' ?>>开启(每天0-5点自动执行)</option><option value="0" <?= $cs('img_auto_clean_enable', '0') != '1' ? 'selected' : '' ?>>关闭</option></select></div>
      <div class="fi"><label>KUNPRO导航:搜索图标</label><select name="kp_icon_search"><option value="1" <?= $cs('kp_icon_search', '1') ? 'selected' : '' ?>>显示</option><option value="0" <?= $cs('kp_icon_search', '0') ? 'selected' : '' ?>>隐藏</option></select></div>
      <div class="fi"><label>KUNPRO导航:历史图标</label><select name="kp_icon_history"><option value="1" <?= $cs('kp_icon_history', '1') ? 'selected' : '' ?>>显示</option><option value="0" <?= $cs('kp_icon_history', '0') ? 'selected' : '' ?>>隐藏</option></select></div>
      <div class="fi"><label>KUNPRO导航:头像图标</label><select name="kp_icon_user"><option value="1" <?= $cs('kp_icon_user', '1') ? 'selected' : '' ?>>显示</option><option value="0" <?= $cs('kp_icon_user', '0') ? 'selected' : '' ?>>隐藏</option></select></div>
      <div class="fi"><label>KUNPRO大图幻灯</label><select name="kp_slide_enable"><option value="1" <?= $cs('kp_slide_enable', '1') ? 'selected' : '' ?>>开启</option><option value="0" <?= $cs('kp_slide_enable', '0') ? 'selected' : '' ?>>关闭</option></select></div>
      <div class="fi"><label>KUNPRO幻灯数量</label><input type="number" name="kp_slide_count" value="<?= $c('kp_slide_count', '6') ?>" min="2" max="10"></div>
      <div class="fi"><label>KUNPRO幻灯数据源</label><select name="kp_slide_source"><option value="new" <?= $cs('kp_slide_source', 'new') == 'new' ? 'selected' : '' ?>>最新影片</option><option value="hot" <?= $cs('kp_slide_source', 'hot') == 'hot' ? 'selected' : '' ?>>最热影片</option><option value="slide" <?= $cs('kp_slide_source', 'slide') == 'slide' ? 'selected' : '' ?>>幻灯管理(pos=电影模式大图)</option></select></div>
      <div class="fi"><label>伪静态URL</label><select name="rewrite_enable"><option value="0" <?= $cs('rewrite_enable', '0') ? 'selected' : '' ?>>关闭(/index.php?s=原生URL)</option><option value="1" <?= $cs('rewrite_enable', '1') ? 'selected' : '' ?>>开启(全站 /vod/detail?id=1 段式URL,利于SEO)</option></select></div>
      <div class="fi"><label>QQ/微信浏览器拦截</label><select name="browser_check_enable"><option value="0" <?= $cs('browser_check_enable', '0') ? 'selected' : '' ?>>关闭</option><option value="1" <?= $cs('browser_check_enable', '1') ? 'selected' : '' ?>>开启(微信/QQ内打开提示复制网址到浏览器)</option></select></div>
      <div class="fi"><label>首页幻灯片</label><select name="home_slide_enable"><option value="1" <?= $cs('home_slide_enable', '1') ? 'selected' : '' ?>>开启</option><option value="0" <?= $cs('home_slide_enable', '0') ? 'selected' : '' ?>>关闭(首页不显示轮播区)</option></select></div>
      <div class="fi"><label>首页显示授权站公告</label><select name="show_api_notice"><option value="1" <?= $cs('show_api_notice', '1') ? 'selected' : '' ?>>显示</option><option value="0" <?= $cs('show_api_notice', '0') ? 'selected' : '' ?>>不显示</option></select></div>
      <div class="fi"><label>页脚显示「网站公告」入口</label><select name="footer_notice_link"><option value="1" <?= $cs('footer_notice_link', '1') ? 'selected' : '' ?>>显示</option><option value="0" <?= $cs('footer_notice_link', '0') ? 'selected' : '' ?>>隐藏</option></select></div>
    </div>
  </div>
  <div class="tp" id="t-verify" style="display:none">
    <div class="form">
      <div class="fi"><label>验证方式</label><select name="captcha_provider">
        <option value="graph" <?= $cs('captcha_provider', 'graph') ? 'selected' : '' ?>>图形验证码(默认)</option>
        <option value="geetest" <?= $cs('captcha_provider', 'geetest') ? 'selected' : '' ?>>极验 v4</option>
        <option value="turnstile" <?= $cs('captcha_provider', 'turnstile') ? 'selected' : '' ?>>Cloudflare Turnstile</option>
      </select>
      <div class="hint">极验/Turnstile 参数未填写完整时自动回落为图形验证码。参考:https://www.geetest.com</div></div>
      <div class="row">
        <div class="fi"><label>极验 captcha_id</label><input type="text" name="geetest_id" value="<?= $c('geetest_id') ?>"></div>
        <div class="fi"><label>极验 captcha_key</label><input type="text" name="geetest_key" value="<?= $c('geetest_key') ?>"></div>
      </div>
      <div class="row">
        <div class="fi"><label>Turnstile Site Key</label><input type="text" name="turnstile_site_key" value="<?= $c('turnstile_site_key') ?>"></div>
        <div class="fi"><label>Turnstile Secret Key</label><input type="text" name="turnstile_secret" value="<?= $c('turnstile_secret') ?>"></div>
      </div>
    </div>
  </div>
  <div class="tp" id="t-smtp" style="display:none">
    <div class="form">
      <div class="hint" style="margin-bottom:12px">用于注册邮箱验证码发送。以QQ邮箱为例:host=smtp.qq.com port=465 secure=ssl user=完整QQ邮箱 pass=授权码</div>
      <div class="row">
        <div class="fi"><label>SMTP服务器</label><input type="text" name="smtp_host" value="<?= $c('smtp_host') ?>" placeholder="smtp.qq.com"></div>
        <div class="fi"><label>端口</label><input type="number" name="smtp_port" value="<?= $c('smtp_port', '465') ?>"></div>
        <div class="fi"><label>加密方式</label><select name="smtp_secure"><option value="ssl" <?= $cs('smtp_secure', 'ssl') ? 'selected' : '' ?>>SSL</option><option value="tls" <?= $cs('smtp_secure', 'tls') ? 'selected' : '' ?>>TLS</option><option value="none" <?= $cs('smtp_secure', 'none') ? 'selected' : '' ?>>无</option></select></div>
      </div>
      <div class="row">
        <div class="fi"><label>发件账号</label><input type="text" name="smtp_user" value="<?= $c('smtp_user') ?>"></div>
        <div class="fi"><label>密码/授权码(留空不修改)</label><input type="password" name="smtp_pass" value="" placeholder="<?= config('smtp_pass', '') !== '' ? '已设置授权码 ✓' : '' ?>" autocomplete="new-password"></div>
      </div>
      <div class="fi"><label>发件人显示名</label><input type="text" name="smtp_from_name" value="<?= $c('smtp_from_name', '坤影CMS') ?>"></div>
      <div class="row" style="margin-top:4px">
        <div class="fi" style="flex:1"><label>发送测试邮件到</label><input type="text" id="mt_mail" placeholder="填你的邮箱,验证SMTP配置是否可用"></div>
        <div class="fi" style="align-self:flex-end"><button class="btn plain" type="button" onclick="mailTest()">📨 发送测试邮件</button></div>
      </div>
      <script>
      async function mailTest(){
        var to = document.getElementById('mt_mail').value.trim();
        if (!to) { toast('请先填写接收测试的邮箱', false); return; }
        var d = new FormData(); d.append('email', to);
        toast('发送中…');
        var j = await api('/admin.php?s=/system/mailtest', d);
        toast(j.msg || '完成', j.code === 1);
      }
      </script>
    </div>
  </div>
  <div class="tp" id="t-pay" style="display:none">
    <b style="font-size:13px">码支付(SHMPAY)</b>
    <div class="form" style="margin-bottom:18px">
      <div class="row">
        <div class="fi"><label>网关地址</label><input type="text" name="codepay_gateway" value="<?= $c('codepay_gateway', 'https://xpay.shw1.com/xpay/epay/submit.php') ?>"></div>
        <div class="fi"><label>商户PID</label><input type="text" name="codepay_pid" value="<?= $c('codepay_pid') ?>"></div>
      </div>
      <div class="row">
        <div class="fi"><label>商户密钥(留空不修改)</label><input type="text" name="codepay_key" value="" placeholder="<?= config('codepay_key', '') !== '' ? '已设置密钥 ✓' : '' ?>"></div>
        <div class="fi"><label>默认通道</label><select name="codepay_channel"><option value="alipay" <?= $cs('codepay_channel', 'alipay') ? 'selected' : '' ?>>支付宝</option><option value="wxpay" <?= $cs('codepay_channel', 'wxpay') ? 'selected' : '' ?>>微信</option></select></div>
      </div>
    </div>
    <b style="font-size:13px">易支付</b>
    <div class="form" style="margin-bottom:18px">
      <div class="row">
        <div class="fi"><label>网关地址(submit.php完整地址)</label><input type="text" name="epay_gateway" value="<?= $c('epay_gateway') ?>"></div>
        <div class="fi"><label>商户PID</label><input type="text" name="epay_pid" value="<?= $c('epay_pid') ?>"></div>
      </div>
      <div class="row">
        <div class="fi"><label>商户密钥(留空不修改)</label><input type="text" name="epay_key" value="" placeholder="<?= config('epay_key', '') !== '' ? '已设置密钥 ✓' : '' ?>"></div>
        <div class="fi"><label>默认通道</label><select name="epay_channel"><option value="alipay" <?= $cs('epay_channel', 'alipay') ? 'selected' : '' ?>>支付宝</option><option value="wxpay" <?= $cs('epay_channel', 'wxpay') ? 'selected' : '' ?>>微信</option><option value="qqpay" <?= $cs('epay_channel', 'qqpay') ? 'selected' : '' ?>>QQ钱包</option></select></div>
      </div>
    </div>
    <b style="font-size:13px">USDT 免挂(TRC20)</b>
    <div class="form" style="margin-bottom:18px">
      <div class="row">
        <div class="fi"><label>TRC20收款地址</label><input type="text" name="usdt_address" value="<?= $c('usdt_address') ?>" placeholder="T开头的波场地址"></div>
        <div class="fi"><label>汇率(1元人民币=X USDT)</label><input type="text" name="usdt_rate" value="<?= $c('usdt_rate') ?>" placeholder="如 0.14"></div>
      </div>
      <div class="fi"><label>TronGrid API Key(选填,提升接口额度)</label><input type="text" name="usdt_trongrid_key" placeholder="<?php echo config('usdt_trongrid_key', '') !== '' ? '已设置 ✓' : 'TronGrid API Key'; ?>" value="<?= $c('usdt_trongrid_key') ?>"></div>
      <div class="hint">系统通过TronGrid链上接口自动到账确认,无需挂机软件。金额按订单附加唯一分位尾数,自动匹配入账。</div>
    </div>
    <b style="font-size:13px">微信官方支付(Native扫码)</b>
    <div class="form" style="margin-bottom:18px">
      <div class="row">
        <div class="fi"><label>公众号/小程序APPID</label><input type="text" name="wxpay_appid" value="<?= $c('wxpay_appid') ?>"></div>
        <div class="fi"><label>商户号</label><input type="text" name="wxpay_mchid" value="<?= $c('wxpay_mchid') ?>"></div>
      </div>
      <div class="fi"><label>商户API密钥V2(32位,留空不修改)</label><input type="text" name="wxpay_key" placeholder="<?php echo config('wxpay_key', '') !== '' ? '已设置 ✓' : '商户API密钥'; ?>"></div>
    </div>
    <b style="font-size:13px">支付宝官方 / 当面付(RSA2)</b>
    <div class="form">
      <div class="fi"><label>开放平台 APPID</label><input type="text" name="alipay_appid" value="<?= $c('alipay_appid') ?>"></div>
      <div class="fi"><label>应用私钥(留空不修改)</label><textarea name="alipay_private_key" placeholder="RSA2(SHA256)私钥"><?= $c('alipay_private_key') ?></textarea></div>
      <div class="fi"><label>支付宝公钥</label><textarea name="alipay_public_key"><?= $c('alipay_public_key') ?></textarea></div>
      <div class="hint">当面付需在支付宝开放平台签约;电脑/手机网站支付需签约对应产品。回调地址请确保域名可公网访问。</div>
    </div>
  </div>
  <div class="tp" id="t-cdn" style="display:none">
    <div class="form">
      <div class="fi"><label>Cloudflare CDN 模式</label><select name="cdn_mode">
        <option value="1" <?= $cs('cdn_mode', '1') ? 'selected' : '' ?>>已开启CDN( orange cloud 开启状态)</option>
        <option value="0" <?= $cs('cdn_mode', '0') ? 'selected' : '' ?>>未开启(直连)</option>
      </select>
      <div class="hint">开启后系统自动读取 CF-Connecting-IP 获取访客真实IP(登录限制/日志/投票均按真实IP);同时输出适配缓存的静态资源版本号。源站已配置强制HTTPS与CF兼容的真实IP,无需额外操作。</div></div>
    </div>
  </div>
  <div class="tp" id="t-ad" style="display:none">
    <p class="hint" style="margin-bottom:12px">共4个广告位,每个可独立开关;代码支持HTML/图片链接/广告联盟JS(站长对代码内容自行负责)。建议尺寸:通栏1200×120内。</p>
    <?php
    $adSlots = [
        ['home', '首页轮播下方通栏'],
        ['playtop', '播放页 · 播放器上方'],
        ['playbottom', '播放页 · 播放器下方'],
        ['footer', '全站底部横幅(所有页面)'],
    ];
    foreach ($adSlots as $sl): $ak = 'ad_' . $sl[0]; ?>
    <div class="card" style="box-shadow:none;border:1px solid var(--line);max-width:760px;margin-bottom:12px">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <b style="font-size:13px"><?= e($sl[1]) ?></b>
        <select name="<?= $ak ?>_enable" style="width:auto"><option value="1" <?= (string)config($ak . '_enable', '0') === '1' ? 'selected' : '' ?>>开启</option><option value="0" <?= (string)config($ak . '_enable', '0') !== '1' ? 'selected' : '' ?>>关闭</option></select>
      </div>
      <textarea name="<?= $ak ?>_code" style="margin-top:10px;min-height:70px;font-family:monospace;font-size:12px" placeholder="粘贴广告代码,例如: <a href=&quot;https://链接&quot; target=&quot;_blank&quot;><img src=&quot;/static/img/ad-demo.svg&quot; style=&quot;max-width:100%&quot;></a>"><?= e((string)config($ak . '_code', '')) ?></textarea>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="tp" id="t-safe" style="display:none">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;align-items:start">
      <div class="card" style="margin:0">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px"><span style="font-size:16px">🛡️</span><b style="font-size:14px">运行安全</b></div>
        <div class="fi"><label>调试模式(生产环境务必关闭)</label><select name="debug"><option value="0" <?= $cs('debug', '0') ? 'selected' : '' ?>>关闭</option><option value="1" <?= $cs('debug', '1') ? 'selected' : '' ?>>开启</option></select><div class="hint">开启后页面会显示详细错误信息,便于排查问题;生产环境请保持关闭。</div></div>
        <div class="fi" style="margin-top:12px"><label>后台备注</label><input type="text" name="admin_remark" value="<?= $c('admin_remark') ?>" placeholder="内部备忘,不会对外显示"></div>
      </div>

      <div class="card" style="margin:0">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px"><span style="font-size:16px">🔍</span><b style="font-size:14px">搜索防护</b></div>
        <div class="fi"><label>搜索频率限制</label><select name="search_limit_enable"><option value="1" <?= $cs('search_limit_enable', '1') ? 'selected' : '' ?>>开启</option><option value="0" <?= $cs('search_limit_enable', '0') ? 'selected' : '' ?>>关闭</option></select></div>
        <div class="fi"><label>百度推送-站点</label><input type="text" name="baidu_push_site" value="<?= $c('baidu_push_site') ?>" placeholder="如 ys.yujia.xyz(与百度站长平台绑定一致)"></div>
        <div class="fi"><label>百度推送-token</label><input type="text" name="baidu_push_token" value="<?= $c('baidu_push_token') ?>" placeholder="ziyuan.baidu.com 链接提交处获取,选填"></div>
        <div class="row" style="margin-top:10px">
          <div class="fi"><label>窗口内最大次数(每IP)</label><input type="number" min="1" name="search_limit_times" value="<?= $c('search_limit_times', '30') ?>"></div>
          <div class="fi"><label>时间窗口(秒)</label><input type="number" min="5" name="search_limit_window" value="<?= $c('search_limit_window', '60') ?>"></div>
        </div>
        <div class="hint">对影片搜索接口生效,同一IP在时间窗口内超出次数的请求将被拒绝,防止恶意刷接口。</div>
      </div>

      <div class="card" style="margin:0;grid-column:1 / -1">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px"><span style="font-size:16px">🔑</span><b style="font-size:14px">修改管理员密码</b><span class="hint" style="margin-left:6px">建议定期更换,至少8位</span></div>
        <div class="row" style="max-width:820px">
          <div class="fi"><label>旧密码</label><input type="password" name="oldpassword" id="p_old" autocomplete="current-password"></div>
          <div class="fi"><label>新密码(≥8位)</label><input type="password" name="password" id="p_new" autocomplete="new-password"></div>
          <div class="fi"><label>确认新密码</label><input type="password" name="repassword" id="p_re" autocomplete="new-password"></div>
          <div class="fi" style="align-self:flex-end"><button class="btn blue" type="button" onclick="changePwd()">修改密码</button></div>
        </div>
      </div>
    </div>
  </div>
  <button class="btn" type="submit" id="go">保存全部设置</button>
  </form>
</div>
<script>
document.querySelectorAll('#tabs a').forEach(a=>a.onclick=function(){
  document.querySelectorAll('#tabs a').forEach(x=>x.classList.remove('on'));
  this.classList.add('on');
  document.querySelectorAll('.tp').forEach(t=>t.style.display='none');
  document.getElementById('t-'+this.dataset.t).style.display='block';
});
async function saveAll(ev){
  ev.preventDefault();
  var go=document.getElementById('go');go.disabled=true;
  var j=await api('/admin.php?s=/system/settingsave',new FormData(ev.target));
  toast(j.msg||'已保存',j.code===1);go.disabled=false;
  if(j.code===1) location.reload();
  return false;
}
async function clearCacheNow(){
  var d=new FormData();d.append('_csrf','<?= e(Security::csrfToken()) ?>');
  var j=await api('/admin.php?s=/system/clearcache',d);
  toast(j.msg||'完成',j.code===1);
}
async function changePwd(){
  var d=new FormData();d.append('oldpassword',p_old.value);d.append('password',p_new.value);d.append('repassword',p_re.value);
  d.append('_csrf','<?= e(Security::csrfToken()) ?>');
  var j=await api('/admin.php?s=/system/password',d);
  toast(j.msg||'完成',j.code===1);
}
</script>
<?php include __DIR__.'/_footer.php'; ?>
