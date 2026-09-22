<?php
$p = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
if ($p === false || $p === "" || $p === "/") {
    if (is_file(__DIR__ . "/index.php")) { require __DIR__ . "/index.php"; return true; }
    return false;
}
$file = __DIR__ . $p;
if (is_file($file)) return false;
if (is_dir($file)) {
    $idx = rtrim($file, "/") . "/index.php";
    if (is_file($idx)) { chdir(dirname($idx)); require $idx; return true; }
    http_response_code(404); exit("not found");
}
$_GET["s"] = $p;
require __DIR__ . "/index.php";
return true;
