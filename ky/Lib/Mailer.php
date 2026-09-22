<?php
/**
 * SMTP邮件发送(支持SSL/TLS)
 */
if (!defined('KY_PATH')) exit('Access denied');

class Mailer
{
    /**
     * 发送邮件
     * @param string $to 收件人
     * @param string $subject 主题
     * @param string $body HTML内容
     */
    public static function send(string $to, string $subject, string $body): bool
    {
        $host = config('smtp_host', '');
        $port = (int)config('smtp_port', 465);
        $user = config('smtp_user', '');
        $pass = config('smtp_pass', '');
        $secure = config('smtp_secure', 'ssl'); // ssl | tls | none
        $fromName = config('smtp_from_name', config('site_name', '坤影CMS'));
        if ($host === '' || $user === '' || $pass === '') return false;

        $timeout = 15;
        $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $errno = 0; $errstr = '';
        $fp = @stream_socket_client($remote, $errno, $errstr, $timeout);
        if (!$fp) return false;
        stream_set_timeout($fp, $timeout);

        $read = function () use ($fp) {
            $data = '';
            while ($line = fgets($fp, 515)) {
                $data .= $line;
                if (strlen($line) < 4 || $line[3] === ' ') break;
            }
            return $data;
        };
        $cmd = function (string $c) use ($fp, $read) {
            fwrite($fp, $c . "\r\n");
            return $read();
        };

        $read();
        $cmd('EHLO kunying');
        if ($secure === 'tls') {
            $cmd('STARTTLS');
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($fp);
                return false;
            }
            $cmd('EHLO kunying');
        }
        $cmd('AUTH LOGIN');
        $cmd(base64_encode($user));
        $r = $cmd(base64_encode($pass));
        if (strpos($r, '235') === false) { fclose($fp); return false; }
        $cmd('MAIL FROM:<' . $user . '>');
        $cmd('RCPT TO:<' . $to . '>');
        $cmd('DATA');
        $headers = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$user}>\r\n"
            . "To: <{$to}>\r\n"
            . "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n";
        $msg = $headers . chunk_split(base64_encode($body)) . "\r\n.";
        $r = $cmd($msg);
        $ok = strpos($r, '250') !== false;
        $cmd('QUIT');
        fclose($fp);
        return $ok;
    }

    /**
     * 发送注册/找回密码验证码邮件
     */
    public static function sendCode(string $to, string $code, string $type = 'register'): bool
    {
        $title = $type === 'reset' ? '找回密码' : '邮箱验证';
        $siteName = config('site_name', '坤影CMS');
        $subject = "[{$siteName}] {$title}验证码:{$code}";
        $body = '<div style="max-width:520px;margin:0 auto;padding:32px;font-family:sans-serif;background:#f7f8fa;border-radius:12px">'
            . '<h2 style="color:#e5322d;margin:0 0 8px">' . e($siteName) . '</h2>'
            . '<p>您好!您正在进行<strong>' . e($title) . '</strong>操作,验证码为:</p>'
            . '<p style="font-size:32px;font-weight:bold;letter-spacing:8px;color:#e5322d;margin:16px 0">' . e($code) . '</p>'
            . '<p style="color:#888;font-size:13px">验证码10分钟内有效,请勿泄露给他人。如非本人操作请忽略此邮件。</p></div>';
        return self::send($to, $subject, $body);
    }
}
