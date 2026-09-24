<?php
/**
 * 坤影CMS - 核心路由/请求/视图
 */
if (!defined('KY_PATH')) exit('Access denied');

class Request
{
    public static function get(string $key, $default = '', string $type = 's')
    {
        $val = $_GET[$key] ?? $default;
        return self::filter($val, $type);
    }

    public static function post(string $key, $default = '', string $type = 's')
    {
        $val = $_POST[$key] ?? $default;
        return self::filter($val, $type);
    }

    public static function jsonBody(): array
    {
        static $body = null;
        if ($body === null) {
            $raw = file_get_contents('php://input');
            $body = (array)(json_decode($raw, true) ?: []);
        }
        return $body;
    }

    public static function jsonPost(string $key, $default = '', string $type = 's')
    {
        $body = self::jsonBody();
        if (array_key_exists($key, $body)) return self::filter($body[$key], $type);
        return self::filter($_POST[$key] ?? $default, $type);
    }

    private static function filter($val, string $type)
    {
        switch ($type) {
            case 'i': return (int)$val;
            case 'f': return (float)$val;
            case 'raw': return $val; // 仅用于富文本等,调用方必须自行校验
            case 'a': // 数组
                return is_array($val) ? $val : [];
            default: // 字符串,去除不可见字符
                return is_array($val) ? '' : trim(str_replace(["\0", "\r"], '', (string)$val));
        }
    }

    public static function isPost(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    public static function isAjax(): bool
    {
        return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    }
}

class View
{
    private string $templateDir;
    private static ?View $front = null;

    public function __construct(string $templateDir)
    {
        $this->templateDir = rtrim($templateDir, '/');
    }

    public static function setFront(View $view): void
    {
        self::$front = $view;
    }

    /**
     * 主题模板直接输出
     */
    public static function display(string $tpl, array $data = []): void
    {
        if (self::$front === null) {
            self::$front = new View(theme_path());
        }
        echo self::$front->render($tpl, $data);
    }

    /**
     * 主题模板返回内容
     */
    public static function load(string $tpl, array $data = []): string
    {
        if (self::$front === null) {
            self::$front = new View(theme_path());
        }
        return self::$front->render($tpl, $data);
    }

    /**
     * 渲染模板(数据键直接注入模板作用域)
     */
    public function render(string $tpl, array $data = []): string
    {
        $file = $this->templateDir . '/' . str_replace(['..', "\\0"], '', $tpl) . '.php';
        if (!is_file($file)) {
            throw new RuntimeException('模板不存在:' . htmlspecialchars($tpl, ENT_QUOTES));
        }
        unset($data['file']);
        extract($data);
        ob_start();
        try {
            include $file;
        } catch (\Throwable $ex) {
            ob_end_clean();
            throw $ex;
        }
        return (string)ob_get_clean();
    }
}

class App
{
    private string $controllerNs;
    private string $defaultController;
    private View $view;

    public function __construct(string $controllerNs, string $defaultController = 'Index')
    {
        $this->controllerNs = $controllerNs;
        $this->defaultController = $defaultController;
    }

    public function setView(View $view): void
    {
        $this->view = $view;
    }

    public function run(): void
    {
        // 路由: /index/index 或 ?s=/user/login 或 PATH_INFO
        $route = '';
        if (!empty($_GET['s'])) {
            $route = (string)$_GET['s'];
        } elseif (!empty($_SERVER['PATH_INFO'])) {
            $route = $_SERVER['PATH_INFO'];
        } else {
            $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
            $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
            if ($script !== '/' && strpos($uri, $script) === 0) {
                $uri = substr($uri, strlen($script));
            }
            $route = $uri;
        }
        $route = trim(str_replace(['..', "\0"], '', $route), '/');
        // 裸入口(如 /admin.php 不带?s=)视为默认路由,避免把脚本名当控制器名
        if ($route === ltrim($_SERVER['SCRIPT_NAME'] ?? ' ', '/') || $route === basename($_SERVER['SCRIPT_NAME'] ?? '')) $route = '';
        if (defined('KY_SUB_DIR') && KY_SUB_DIR !== '' && strpos($route, KY_SUB_DIR) === 0) {
            $route = trim(substr($route, strlen(KY_SUB_DIR)), '/');
        }
        // 入口脚本自身(如 /admin.php、/index.php)不作为路由
        $scriptName = strtolower(basename($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($route === $scriptName) {
            $route = '';
        }
        $parts = $route === '' ? [] : explode('/', $route);
        // 支持 pathinfo 参数: /controller/action/p1/v1/p2/v2
        $controller = $parts[0] ?? $this->defaultController;
        $action = $parts[1] ?? 'index';
        $extra = array_slice($parts, 2);
        if (count($extra) >= 2) {
            for ($i = 0; $i + 1 < count($extra); $i += 2) {
                if (!isset($_GET[$extra[$i]])) $_GET[$extra[$i]] = $extra[$i + 1];
            }
        }

        $controller = preg_replace('/[^A-Za-z0-9_]/', '', $controller);
        $action = preg_replace('/[^A-Za-z0-9_]/', '', $action);
        if ($controller === '' || $action === '') {
            $controller = $this->defaultController;
            $action = 'index';
        }
        $GLOBALS['ky_controller'] = strtolower($controller);
        $GLOBALS['ky_action'] = strtolower($action);

        // 兼容 RESTful 风格: 非GET禁止调用 get 前缀方法等安全约束在各控制器内部处理
        $class = $this->controllerNs . ucfirst($controller) . 'Controller';
        if (!class_exists($class)) {
            $this->halt(404, '页面不存在');
        }
        $obj = new $class();
        if (!method_exists($obj, $action) || !is_callable([$obj, $action])) {
            $this->halt(404, '页面不存在');
        }
        try {
            $obj->$action();
        } catch (\Throwable $ex) {
            if (config('debug') == '1') {
                $msg = $ex->getMessage() . ' @' . htmlspecialchars($ex->getFile(), ENT_QUOTES) . ':' . $ex->getLine();
            } else {
                $msg = '系统繁忙,请稍后再试';
            }
            error_log('[KunYing] ' . $ex->getMessage() . ' ' . $ex->getTraceAsString());
            if (Request::isAjax()) {
                json_error($msg, 500);
            }
            $this->halt(500, $msg);
        }
    }

    private function halt(int $code, string $msg): void
    {
        http_response_code($code);
        if (file_exists(theme_path('404.php'))) {
            echo $this->view->render('404', ['msg' => $msg]);
        } else {
            echo '<!doctype html><meta charset="utf-8"><title>' . $code . '</title><body style="font-family:sans-serif;background:#101014;color:#eee;display:flex;align-items:center;justify-content:center;height:100vh">' . e($msg) . '</body>';
        }
        exit;
    }
}
