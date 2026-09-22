<?php
/**
 * 插件/模板管理:安装、卸载、列表
 * 安装包为zip,根目录须含 manifest.json(name/code/type/version)
 */
if (!defined('KY_PATH')) exit('Access denied');

class Addon
{
    public static function typeDir(string $type): string
    {
        return $type === 'template' ? 'theme' : 'addon';
    }

    public static function all(): array
    {
        return Db::fetchAll("SELECT * FROM ky_plugin ORDER BY type ASC, id DESC");
    }

    /**
     * 从zip安装
     */
    public static function installFromZip(string $zipPath, string $expectedType = ''): array
    {
        if (!class_exists('ZipArchive')) throw new RuntimeException('PHP缺少zip扩展');
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) throw new RuntimeException('安装包解压失败');
        // 安全检查:禁止压缩包内含路径穿越与php非法位置由目录规则限制
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string)$zip->getNameIndex($i);
            if ($name === '' || str_contains($name, '..') || str_starts_with($name, '/') || preg_match('#^[a-zA-Z]:#', $name)) {
                $zip->close();
                throw new RuntimeException('安装包含非法路径:' . $name);
            }
        }
        $manifestRaw = $zip->getFromName('manifest.json');
        $base = ''; // 包内基础路径(支持单层目录包裹的包)
        if ($manifestRaw === false && $zip->numFiles > 0) {
            // 检测是否所有文件都在同一个一级目录下
            $tops = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $n = (string)$zip->getNameIndex($i);
                if ($n === '' || $n === '/') continue;
                $slash = strpos($n, '/');
                if ($slash === false) { $tops = []; break; }
                $tops[substr($n, 0, $slash + 1)] = true;
            }
            if (count($tops) === 1) {
                $base = (string)array_key_first($tops);
                $manifestRaw = $zip->getFromName($base . 'manifest.json');
            }
        }
        if ($manifestRaw === false) { $zip->close(); throw new RuntimeException('安装包缺少manifest.json'); }
        $manifest = json_decode($manifestRaw, true);
        if (!is_array($manifest) || empty($manifest['code']) || empty($manifest['type'])) {
            $zip->close();
            throw new RuntimeException('manifest.json格式错误');
        }
        $code = preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)$manifest['code']));
        $type = $manifest['type'] === 'template' ? 'template' : 'plugin';
        if ($code === '') { $zip->close(); throw new RuntimeException('产品代码非法'); }
        if ($expectedType !== '' && $type !== $expectedType) {
            $zip->close();
            throw new RuntimeException('产品类型不符');
        }

        $dest = KY_PATH . '/' . self::typeDir($type) . '/' . $code;
        if (!is_dir($dest) && !mkdir($dest, 0755, true)) { $zip->close(); throw new RuntimeException('创建目录失败'); }
        $tmpEx = KY_PATH . '/data/cache/ext_' . rand_str(6);
        if (!mkdir($tmpEx, 0755, true)) { $zip->close(); throw new RuntimeException('临时目录创建失败'); }
        if (!$zip->extractTo($tmpEx)) {
            $zip->close();
            self::rrmdir($tmpEx);
            throw new RuntimeException('解压失败');
        }
        $zip->close();
        // 单层目录包裹时从子目录搬运
        $srcDir = $base === '' ? $tmpEx : rtrim($tmpEx . '/' . $base, '/');
        foreach (scandir($srcDir) ?: [] as $f) {
            if ($f === '.' || $f === '..') continue;
            if (!@rename($srcDir . '/' . $f, $dest . '/' . $f)) {
                self::rrmdir($tmpEx);
                throw new RuntimeException('安装文件写入失败');
            }
        }
        self::rrmdir($tmpEx);

        Db::query(
            "INSERT INTO ky_plugin (code,name,type,version,author,status,expire) VALUES (?,?,?,?,?,0,0)
             ON DUPLICATE KEY UPDATE name=VALUES(name),version=VALUES(version),author=VALUES(author)",
            [$code, mb_substr((string)($manifest['name'] ?? $code), 0, 60), $type,
             mb_substr((string)($manifest['version'] ?? '1.0'), 0, 20), mb_substr((string)($manifest['author'] ?? ''), 0, 60)]
        );
        return ['code' => $code, 'type' => $type, 'name' => (string)($manifest['name'] ?? $code)];
    }

    public static function uninstall(string $code): bool
    {
        $item = Db::fetch("SELECT * FROM ky_plugin WHERE code=?", [$code]);
        if (!$item) return false;
        // 模板使用中禁止卸载
        if ($item['type'] === 'template' && config('site_template', 'dsv1') === $code) {
            throw new RuntimeException('该模板使用中,请先切换其他模板');
        }
        $dir = KY_PATH . '/' . self::typeDir($item['type']) . '/' . basename($code);
        self::rrmdir($dir);
        Db::delete('ky_plugin', 'code=?', [$code]);
        cache_del('license_' . $code);
        return true;
    }

    public static function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $dir = rtrim($dir, '/');
        if (realpath($dir) === realpath(KY_PATH . '/theme') || realpath($dir) === realpath(KY_PATH . '/addon')) return;
        foreach (scandir($dir) ?: [] as $f) {
            if ($f === '.' || $f === '..') continue;
            $p = $dir . '/' . $f;
            is_dir($p) ? self::rrmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }
}
