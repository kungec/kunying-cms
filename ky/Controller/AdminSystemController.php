<?php
/**
 * 后台 - 系统设置:站点/支付/验证码/CDN/应用市场/自动更新/日志
 */
class AdminSystemController
{
    public function __construct()
    {
        Auth::requireAdmin();
        // 后台所有POST统一CSRF校验(GET视图不受影响)
        if (Request::isPost()) Security::csrfCheck();
    }

    /* ===================== 站点设置 ===================== */

    public function setting()
    {
        View::display('setting', []);
    }

    public function settingsave()
    {
        if (!Request::isPost()) json_error('非法请求');
        // 白名单键
        $keys = [
            'site_name', 'site_keywords', 'site_description', 'site_icp', 'site_template', 'site_mode',
            'member_enable', 'register_enable', 'comment_enable', 'comment_audit', 'show_api_notice', 'footer_notice_link',
            'home_slide_enable', 'browser_check_enable', 'rewrite_enable',
            'kp_icon_search', 'kp_icon_history', 'kp_icon_user', 'kp_slide_enable', 'kp_slide_count', 'kp_slide_source',
            'img_auto_clean_enable',
            'captcha_provider', 'geetest_id', 'geetest_key', 'turnstile_site_key', 'turnstile_secret',
            'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_secure', 'smtp_from_name',
            'points_sign', 'points_register', 'points_pay_rate',
            'cdn_mode', 'debug',
            'player_parse', 'player_autoplay',
            'codepay_gateway', 'codepay_pid', 'codepay_key', 'codepay_channel',
            'epay_gateway', 'epay_pid', 'epay_key', 'epay_channel',
            'usdt_address', 'usdt_rate', 'usdt_trongrid_key',
            'wxpay_appid', 'wxpay_mchid', 'wxpay_key',
            'alipay_appid', 'alipay_private_key', 'alipay_public_key',
            'admin_remark',
            'search_limit_enable', 'search_limit_times', 'search_limit_window',
            'baidu_push_site', 'baidu_push_token',
            'http_verify_ssl',
            'ad_home_enable', 'ad_home_code',
            'ad_playtop_enable', 'ad_playtop_code',
            'ad_playbottom_enable', 'ad_playbottom_code',
            'ad_footer_enable', 'ad_footer_code',
        ];
        $sens = ['smtp_pass', 'geetest_key', 'turnstile_secret', 'codepay_key', 'epay_key', 'usdt_trongrid_key', 'wxpay_key', 'alipay_private_key', 'alipay_public_key', 'usdt_address'];
        foreach ($keys as $k) {
            $val = (string)Request::post($k, null, 'raw');
            if ($val === null) continue;
            // 敏感项留空则不覆盖
            if (in_array($k, $sens, true) && $val === '') continue;
            if ($k === 'smtp_port' && !ctype_digit($val)) continue;
            if ($k === 'player_parse' && $val !== '' && !preg_match('#\{url\}#', $val)) continue;
            if ($k === 'site_template' && $val === '') continue;
            if ($k === 'api_site' && $val === '') continue;
            config_set($k, $val);
        }
        // 付费主题切换校验:需专业版授权
        $newTpl = strtolower(basename((string)Request::post('site_template', '')));
        if (in_array($newTpl, ['kunpro', 'iqiyi'], true) && (string)config('site_template', '') !== $newTpl) {
            if (!License::check($newTpl)) {
                json_error('「' . $newTpl . '」为付费主题,需开通专业版会员(99元)使用。请前往授权站购买专业版获取激活码,在 市场→激活码激活 中完成绑定');
            }
        }
        // 爱奇艺风格为付费模式(99元),需已在授权站购买并授权
        if ((string)config('site_mode', 'cms') !== Request::post('site_mode') && Request::post('site_mode') === 'iqiyi') {
            if (!License::check('iqiyi') && !License::check('kunpro') && !License::check('ds6')) {
                json_error('爱奇艺风格为付费主题(¥99永久),请先到 应用市场 → 模板市场 购买授权');
            }
        }
        Admin::log('修改系统设置');
        json_ok(null, '保存成功');
    }

    /**
     * 清理系统缓存(文件缓存/图片缓存/更新临时包)
     */
    public function clearcache()
    {
        if (!Request::isPost()) json_error('非法请求');
        $count = 0;
        foreach (glob(KY_PATH . '/data/cache/*') ?: [] as $f) {
            if (is_dir($f)) { Addon::rrmdir($f); $count++; }
            else { @unlink($f); $count++; }
        }
        Admin::log('清理系统缓存');
        json_ok(null, "已清理 {$count} 项缓存");
    }

    /**
     * 修改管理员密码
     */
    public function password()
    {
        if (!Request::isPost()) json_error('非法请求');
        $old = (string)Request::post('oldpassword');
        $new = (string)Request::post('password');
        $re = (string)Request::post('repassword');
        $admin = Auth::admin();
        if (!$admin || !password_verify($old, $admin['pwd'])) json_error('旧密码错误');
        if (strlen($new) < 8) json_error('新密码至少8位');
        if ($new !== $re) json_error('两次新密码不一致');
        Db::update('ky_admin', ['pwd' => password_hash($new, PASSWORD_DEFAULT)], 'id=?', [$admin['id']]);
        // 刷新当前会话令牌,本会话保持登录,其他旧会话失效
        $_SESSION['admin_token'] = Auth::adminSessionToken(['pwd' => password_hash($new, PASSWORD_DEFAULT)]);
        Admin::log('修改管理员密码');
        json_ok(null, '密码已修改');
    }

    /* ===================== 应用市场(插件/模板) ===================== */

    public function images()
    {
        $dir = KY_PATH . '/upload/vod';
        $perPage = (int)Request::get('per_page', 100, 'i');
        if (!in_array($perPage, [50, 100, 200, 500, 1000], true)) $perPage = 100;
        $page = max(1, (int)Request::get('page', 1, 'i'));
        $files = [];
        $totalSize = 0;
        if (is_dir($dir)) {
            foreach (glob($dir . '/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE) ?: [] as $f) {
                $sz = @filesize($f) ?: 0;
                $totalSize += $sz;
                $files[] = ['name' => basename($f), 'size' => $sz, 'time' => @filemtime($f) ?: 0];
            }
            usort($files, function ($a, $b) { return $b['time'] <=> $a['time']; });
        }
        $total = count($files);
        $pages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $pages);
        $used = [];
        foreach (Db::fetchAll("SELECT pic FROM ky_vod WHERE pic LIKE '%/upload/vod/%' LIMIT 5000") as $r) {
            if (preg_match_all('#/upload/vod/([A-Za-z0-9_\-\.]+)#', (string)$r['pic'], $m)) {
                foreach ($m[1] as $n) $used[strtolower($n)] = 1;
            }
        }
        foreach (Db::fetchAll("SELECT pic FROM ky_slide WHERE pic LIKE '%/upload/vod/%'") as $r) {
            $used[strtolower(basename((string)$r['pic']))] = 1;
        }
        $orphan = 0;
        foreach ($files as &$f) {
            $f['used'] = isset($used[strtolower($f['name'])]);
            if (!$f['used']) $orphan++;
        }
        unset($f);
        $slice = array_slice($files, ($page - 1) * $perPage, $perPage);
        View::display('images', [
            'files' => $slice, 'total' => $total, 'totalSize' => $totalSize, 'orphan' => $orphan,
            'page' => $page, 'pages' => $pages, 'perPage' => $perPage,
        ]);
    }

    public function imgdels()
    {
        $names = array_filter(array_map('trim', explode(',', (string)Request::post('names'))));
        $dir = KY_PATH . '/upload/vod';
        $n = 0;
        foreach ($names as $nm) {
            $nm = basename((string)$nm);
            if ($nm === '' || !preg_match('#^[A-Za-z0-9_\-]+\.(jpg|jpeg|png|gif|webp)$#i', $nm)) continue;
            if (is_file($dir . '/' . $nm)) { @unlink($dir . '/' . $nm); $n++; }
        }
        Admin::log('批量删除图片 ' . $n . ' 张');
        json_ok(null, '已删除 ' . $n . ' 张图片');
    }

    public function imgorphan()
    {
        $n = img_clean_orphans();
        Admin::log('清理未引用图片 ' . $n . ' 张');
        json_ok(null, '已清理 ' . $n . ' 张未引用图片');
    }

    /**
     * 发送测试邮件(验证SMTP配置)
     */
    public function mailtest()
    {
        $to = trim((string)Request::post('email'));
        if (!preg_match('#^[^@\s]+@[^@\s]+\.[a-z]{2,}$#i', $to)) json_error('邮箱格式不正确');
        $ok = Mailer::send($to, '坤影CMS 邮件配置测试', '<div style="font-family:sans-serif;padding:20px"><h2 style="color:#2f7df6">配置成功!</h2><p>这是一封测试邮件,收到即说明SMTP邮件服务配置正确。</p><p>时间:' . date('Y-m-d H:i:s') . '</p></div>');
        if ($ok) json_ok(null, '测试邮件已发送到 ' . $to . ',请查收(注意垃圾箱)');
        json_error('发送失败,请检查SMTP服务器/端口/加密方式/账号/授权码');
    }

    public function market()
    {
        $tab = Request::get('tab', 'plugin');
        $local = Db::fetchAll("SELECT * FROM ky_plugin ORDER BY id DESC");
        $items = [];
        $apiOk = true;
        $apiMsg = '';
        $list = License::market();
        if (empty($list)) {
            $apiOk = false;
            $apiMsg = '无法连接授权控制端(' . License::apiBase() . '),请检查站点设置中的授权服务器与站点Token';
        } else {
            foreach ($list as $item) {
                if (($item['type'] ?? '') !== $tab) continue;
                $installed = null;
                foreach ($local as $l) {
                    if ($l['code'] === ($item['code'] ?? '')) { $installed = $l; break; }
                }
                $items[] = $item + ['installed' => $installed];
            }
        }
        View::display('market', compact('tab', 'items', 'local', 'apiOk', 'apiMsg'));
    }

    /**
     * 在线安装(下载zip→解压)
     */
    public function install()
    {
        @set_time_limit(300);
        $code = preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)Request::post('code')));
        $typeRaw = (string)Request::post('type');
        $type = $typeRaw === 'template' ? 'template' : ($typeRaw === 'mode' ? 'mode' : 'plugin');
        // 模板/插件/模式需已授权
        if (!License::check($code)) {
            json_error('该产品未授权或授权已过期,请先在授权控制端获取授权');
        }
        if ($type === 'mode') {
            // 模式类产品为程序内置功能,授权核实通过即完成激活,无需安装包
            $name = $code;
            foreach (License::market() as $m) {
                if (($m['code'] ?? '') === $code) { $name = (string)($m['name'] ?? $code); break; }
            }
            Db::query("INSERT INTO ky_plugin (code,name,type,status) VALUES (?,?, 'mode',1) ON DUPLICATE KEY UPDATE status=1", [$code, mb_substr($name, 0, 60)]);
            Admin::log('激活模式:' . $code);
            json_ok(['code' => $code, 'type' => 'mode', 'name' => $name], '激活成功,可在系统设置→站点模式中切换启用');
        }
        $url = License::downloadUrl($code);
        if (!$url) json_error('获取下载地址失败,请确认已在控制端授权该产品');
        $tmp = KY_PATH . '/data/cache/pkg_' . $code . '_' . rand_str(4) . '.zip';
        if (!Http::download($url, $tmp)) json_error('安装包下载失败');
        try {
            $ret = Addon::installFromZip($tmp, $type);
            Admin::log('在线安装' . ($type === 'template' ? '模板' : '插件') . ':' . $code);
            json_ok($ret, '安装成功:' . $ret['name']);
        } catch (Throwable $t) {
            json_error($t->getMessage());
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * 本地上传安装
     */
    public function upload()
    {
        if (empty($_FILES['package']) || $_FILES['package']['error'] !== UPLOAD_ERR_OK) json_error('请选择安装包');
        $f = $_FILES['package'];
        if ($f['size'] > 50 * 1024 * 1024) json_error('安装包不能超过50MB');
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if ($ext !== 'zip') json_error('仅支持zip安装包');
        $tmp = KY_PATH . '/data/cache/upl_' . rand_str(6) . '.zip';
        move_uploaded_file($f['tmp_name'], $tmp);
        try {
            $ret = Addon::installFromZip($tmp);
            Admin::log('本地上传安装:' . $ret['code']);
            json_ok($ret, '安装成功:' . $ret['name']);
        } catch (Throwable $t) {
            json_error($t->getMessage());
        } finally {
            @unlink($tmp);
        }
    }

    public function uninstall()
    {
        $code = preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)Request::post('code')));
        try {
            Addon::uninstall($code);
            Admin::log('卸载产品:' . $code);
            json_ok(null, '卸载成功');
        } catch (Throwable $t) {
            json_error($t->getMessage());
        }
    }

    public function settemplate()
    {
        $code = preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)Request::post('code')));
        $item = Db::fetch("SELECT * FROM ky_plugin WHERE code=? AND type='template'", [$code]);
        if (!$item) json_error('模板不存在或未安装');
        if (!License::check($code)) json_error('模板未授权,无法启用');
        if (!is_file(theme_path_abs($code) . '/config.php')) json_error('模板文件不完整');
        config_set('site_template', $code);
        Admin::log('启用模板:' . $code);
        json_ok(null, '模板已启用');
    }

    public function plugintoggle()
    {
        $code = preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)Request::post('code')));
        $item = Db::fetch("SELECT * FROM ky_plugin WHERE code=?", [$code]);
        if (!$item) json_error('产品不存在');
        if ($item['status'] == 1 && !License::check($code)) json_error('授权失效,无法启用');
        $new = $item['status'] == 1 ? 0 : 1;
        Db::update('ky_plugin', ['status' => $new], 'code=?', [$code]);
        json_ok(['status' => $new], $new ? '已启用' : '已停用');
    }

    /* ===================== 授权码绑定 ===================== */

    public function bind()
    {
        $code = preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)Request::post('code')));
        $auth = trim((string)Request::post('auth'));
        if ($code === '' || $auth === '') json_error('参数不完整');
        [$ok, $msg] = License::bind($code, $auth);
        if ($ok) { Admin::log('绑定授权:' . $code); json_ok(null, $msg); }
        json_error($msg);
    }

    /* ===================== 自动更新 ===================== */

    public function update()
    {
        $info = Updater::check();
        View::display('update', ['info' => $info]);
    }

    public function updateapply()
    {
        @set_time_limit(600);
        [$ok, $msg] = Updater::apply();
        Admin::log('执行在线更新:' . ($ok ? '成功' : '失败') . ' ' . $msg);
        if ($ok) json_ok(null, $msg);
        json_error($msg);
    }

    /* ===================== 安全日志 ===================== */

    /**
     * 清空安全日志(保留本次清理操作的记录)
     */
    public function clearlog()
    {
        if (!Request::isPost()) json_error('非法请求');
        Db::query("TRUNCATE TABLE ky_admin_log");
        Admin::log('清空全部安全日志');
        json_ok(null, '日志已清空');
    }

    public function log()
    {
        $page = max(1, Request::get('page', 1, 'i'));
        $pageSize = 30;
        $total = (int)Db::fetchOne("SELECT COUNT(*) FROM ky_admin_log");
        $list = Db::fetchAll(
            "SELECT l.*,a.username FROM ky_admin_log l LEFT JOIN ky_admin a ON a.id=l.admin_id ORDER BY l.id DESC LIMIT {$pageSize} OFFSET " . (($page - 1) * $pageSize)
        );
        View::display('log', ['list' => $list, 'pageHtml' => page_html($total, $pageSize, $page, '/admin.php?s=/system/log&page={page}')]);
    }
}

function theme_path_abs(string $code): string
{
    return KY_PATH . '/theme/' . basename($code);
}
