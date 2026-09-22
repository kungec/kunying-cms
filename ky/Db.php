<?php
/**
 * 坤影CMS - 数据库操作类(全预处理防注入)
 */
if (!defined('KY_PATH')) exit('Access denied');

class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $file = KY_PATH . '/data/config.php';
            if (!is_file($file)) {
                http_response_code(500);
                exit('坤影CMS尚未安装,<a href="/install/">请点击进入安装向导</a>');
            }
            $conf = include $file;
            if (!is_array($conf) || empty($conf['db'])) {
                http_response_code(500);
                exit('数据库配置无效,请重新安装');
            }
            $db = $conf['db'];
            $dsn = "mysql:host={$db['host']};port=" . ($db['port'] ?? 3306) . ";dbname={$db['name']};charset=utf8mb4";
            try {
                self::$pdo = new PDO($dsn, $db['user'], $db['pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                ]);
            } catch (PDOException $ex) {
                http_response_code(500);
                exit('数据库连接失败:' . htmlspecialchars($ex->getMessage(), ENT_QUOTES));
            }
        }
        return self::$pdo;
    }

    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function fetch(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function fetchOne(string $sql, array $params = [])
    {
        $row = self::query($sql, $params)->fetch(PDO::FETCH_NUM);
        return $row === false ? null : $row[0];
    }

    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $marks = array_map(fn($c) => ':' . $c, $cols);
        $sql = "INSERT INTO `{$table}` (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', $marks) . ")";
        $bind = [];
        foreach ($data as $k => $v) { $bind[':' . $k] = $v; }
        self::query($sql, $bind);
        return (int)self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $sets = [];
        $bind = [];
        foreach ($data as $k => $v) {
            $sets[] = "`{$k}`=:s_{$k}";
            $bind[":s_{$k}"] = $v;
        }
        // 将where中的位置占位符统一转换为命名占位符,避免混合参数
        $idx = 0;
        $whereNamed = preg_replace_callback('/\?/', function ($m) use (&$idx, &$bind, $whereParams) {
            $name = ":w_{$idx}";
            $bind[$name] = $whereParams[$idx] ?? null;
            $idx++;
            return $name;
        }, $where);
        $sql = "UPDATE `{$table}` SET " . implode(',', $sets) . " WHERE {$whereNamed}";
        return self::query($sql, $bind)->rowCount();
    }

    public static function delete(string $table, string $where, array $params = []): int
    {
        return self::query("DELETE FROM `{$table}` WHERE {$where}", $params)->rowCount();
    }

    public static function begin(): void { self::pdo()->beginTransaction(); }
    public static function commit(): void { if (self::pdo()->inTransaction()) self::pdo()->commit(); }
    public static function rollback(): void { if (self::pdo()->inTransaction()) self::pdo()->rollback(); }
}
