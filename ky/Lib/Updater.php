<?php
/**
 * 自动更新:从授权控制端检查并应用更新
 */
if (!defined('KY_PATH')) exit('Access denied');

class Updater
{
    public static function check(): ?array
    {
        $res = License::api('/api/update/check', [], 12);
        if (!$res || ($res['code'] ?? 0) != 1) return null;
        $data = is_array($res['data'] ?? null) ? $res['data'] : [];
        return [
            'has_update' => version_compare((string)($data['version'] ?? ''), KY_VERSION, '>'),
            'version' => (string)($data['version'] ?? KY_VERSION),
            'changelog' => (string)($data['changelog'] ?? ''),
            'time' => (int)($data['time'] ?? 0),
        ];
    }

    /**
     * 下载并应用更新
     */
    public static function apply(): array
    {
        if (!class_exists('ZipArchive')) return [false, 'PHP缺少zip扩展'];
        $res = License::api('/api/update/package', [], 60);
        if (!$res || ($res['code'] ?? 0) != 1) return [false, '获取更新包失败'];
        $url = (string)($res['data']['url'] ?? '');
        $sha = (string)($res['data']['sha256'] ?? '');
        $version = (string)($res['data']['version'] ?? '');
        if ($url === '') return [false, '更新包地址无效'];

        $tmpZip = KY_PATH . '/data/cache/update_' . preg_replace('/[^0-9a-z\.]/', '', $version) . '.zip';
        if (!Http::download($url, $tmpZip)) return [false, '更新包下载失败'];
        if ($sha !== '' && hash_file('sha256', $tmpZip) !== $sha) {
            @unlink($tmpZip);
            return [false, '更新包校验失败,已中止'];
        }

        $zip = new ZipArchive();
        if ($zip->open($tmpZip) !== true) return [false, '更新包无法解压'];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string)$zip->getNameIndex($i);
            if (str_contains($name, '..') || str_starts_with($name, '/')) { $zip->close(); return [false, '更新包含非法路径']; }
            // 不允许覆盖数据与安装目录
            if (preg_match('#^(data|install|config)/#', ltrim($name, './'))) { $zip->close(); return [false, '更新包含受保护目录']; }
        }
        $tmpDir = KY_PATH . '/data/cache/update_' . rand_str(6);
        if (!mkdir($tmpDir, 0755, true)) { $zip->close(); return [false, '创建临时目录失败']; }
        $zip->extractTo($tmpDir);
        $zip->close();

        // 根目录可能是包名一层
        $src = $tmpDir;
        $entries = array_diff(scandir($tmpDir) ?: [], ['.', '..']);
        if (count($entries) === 1 && is_dir($tmpDir . '/' . reset($entries))) {
            $src = $tmpDir . '/' . reset($entries);
        }

        // 备份被覆盖的入口与核心文件
        $backup = KY_PATH . '/data/cache/backup_' . date('YmdHis');
        mkdir($backup, 0755, true);

        $copied = 0;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY);
        foreach ($it as $file) {
            $rel = ltrim(substr((string)$file, strlen($src)), '/\\');
            if ($rel === '' || preg_match('#^(data|install)/#', $rel)) continue;
            $target = KY_PATH . '/' . str_replace('\\', '/', $rel);
            $dir = dirname($target);
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            if (is_file($target)) {
                $bd = $backup . '/' . dirname($rel);
                if (!is_dir($bd)) mkdir($bd, 0755, true);
                @copy($target, $bd . '/' . basename($rel));
            }
            if (@copy((string)$file, $target)) $copied++;
        }

        // 运行升级脚本(可选)
        $upgradeFile = $src . '/upgrade.php';
        $upgradeLog = '';
        if (is_file($upgradeFile)) {
            ob_start();
            try { include $upgradeFile; } catch (\Throwable $t) { $upgradeLog = '升级脚本异常:' . $t->getMessage(); }
            $upgradeLog .= ob_get_clean();
        }

        config_set('sys_version', $version);
        Addon::rrmdir($tmpDir);
        @unlink($tmpZip);
        return [true, "更新完成,共更新 {$copied} 个文件 " . ($upgradeLog !== '' ? '| ' . $upgradeLog : '')];
    }
}
