<?php
/**
 * 人机验证统一组件:图形验证码 / 极验v4 / Cloudflare Turnstile
 * 后台未配置极验或Turnstile时自动回落图形验证码
 */
if (!defined('KY_PATH')) exit('Access denied');

class Verify
{
    /**
     * 当前生效的验证方式:graph | geetest | turnstile
     */
    public static function provider(): string
    {
        $p = config('captcha_provider', 'graph');
        if ($p === 'geetest' && config('geetest_id', '') !== '' && config('geetest_key', '') !== '') return 'geetest';
        if ($p === 'turnstile' && config('turnstile_site_key', '') !== '' && config('turnstile_secret', '') !== '') return 'turnstile';
        return 'graph';
    }

    /* ===================== 前端输出 ===================== */

    public static function render(string $scene = 'default', string $theme = 'auto'): string
    {
        $p = self::provider();
        $id = 'ky_verify_' . $scene . '_' . rand_str(4);
        if ($p === 'turnstile') {
            $siteKey = e(config('turnstile_site_key'));
            return '<div class="ky-verify"><div id="' . $id . '" class="cf-turnstile" data-sitekey="' . $siteKey . '" data-theme="' . e($theme) . '"></div></div>'
                . '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
        }
        if ($p === 'geetest') {
            $cid = e(config('geetest_id'));
            $html = '<div class="ky-verify"><div id="' . $id . '"></div></div>'
                . '<input type="hidden" name="lot_number" id="' . $id . '_lot"><input type="hidden" name="captcha_output" id="' . $id . '_out"><input type="hidden" name="pass_token" id="' . $id . '_tok"><input type="hidden" name="gen_time" id="' . $id . '_gt">'
                . '<script src="https://static.geetest.com/v4/gt4.js"></script>'
                . '<script>initGeetest4({captchaId:' . json_encode(config('geetest_id')) . ',product:"float",language:"zho"},function(captcha){'
                . 'captcha.appendTo("#' . $id . '").onSuccess(function(){var r=captcha.getValidate();'
                . 'document.getElementById("' . $id . '_lot").value=r.lot_number;'
                . 'document.getElementById("' . $id . '_out").value=r.captcha_output;'
                . 'document.getElementById("' . $id . '_tok").value=r.pass_token;'
                . 'document.getElementById("' . $id . '_gt").value=r.gen_time;});});</script>';
            return $html;
        }
        // 图形验证码
        $url = '/index.php?s=/api/image&scene=' . e($scene);
        return '<div class="ky-verify"><div style="display:flex;gap:8px;align-items:center">'
            . '<input type="text" name="captcha" class="ky-input" placeholder="请输入验证码" autocomplete="off" maxlength="4" required>'
            . '<img src="' . $url . '&t=' . time() . '" class="ky-captcha-img" title="看不清?点击刷新" style="height:42px;cursor:pointer" onclick="this.src=\'' . $url . '&t=\'+Date.now()"></div></div>';
    }

    /* ===================== 服务端校验 ===================== */

    /**
     * @return array [ok(bool), msg]
     */
    public static function check(string $scene = 'default'): array
    {
        $p = self::provider();
        if ($p === 'turnstile') {
            $token = (string)($_POST['cf-turnstile-response'] ?? Request::jsonBody()['cf_turnstile_response'] ?? '');
            $res = Http::post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => config('turnstile_secret'),
                'response' => $token,
                'remoteip' => client_ip(),
            ]);
            $data = $res ? json_decode($res, true) : null;
            return [is_array($data) && !empty($data['success']), '人机验证未通过,请重试'];
        }
        if ($p === 'geetest') {
            $lot = trim((string)($_POST['lot_number'] ?? ''));
            $out = trim((string)($_POST['captcha_output'] ?? ''));
            $tok = trim((string)($_POST['pass_token'] ?? ''));
            $gt = trim((string)($_POST['gen_time'] ?? ''));
            if ($lot === '' || $out === '' || $tok === '') return [false, '请先完成人机验证'];
            $key = (string)config('geetest_key');
            $sign = hash_hmac('sha256', $lot, $key);
            // 官方现行端点gcaptcha4.geetest.com(原captcha-openapi域名已NXDOMAIN下线)
            $url = 'https://gcaptcha4.geetest.com/validate?' . http_build_query([
                'captcha_id' => config('geetest_id'),
                'lot_number' => $lot,
                'captcha_output' => $out,
                'pass_token' => $tok,
                'gen_time' => $gt,
                'sign_token' => $sign,
            ]);
            $data = Http::getJson($url, 10);
            if (is_array($data) && ($data['result'] ?? '') === 'success') return [true, 'ok'];
            return [false, '人机验证未通过,请重试'];
        }
        $input = (string)($_POST['captcha'] ?? Request::jsonBody()['captcha'] ?? '');
        return [Captcha::check($input, $scene), '验证码错误或已过期'];
    }
}
