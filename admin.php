<?php
/**
 * 坤影CMS 后台入口
 */
define('KY_SUB_DIR', '');
require __DIR__ . '/ky/bootstrap.php';

if (!is_file(__DIR__ . '/data/install.lock')) {
    header('Location: /install/');
    exit;
}

Security::session();

$app = new App('Admin', 'Main');
$adminView = new View(KY_PATH . '/ky/View/admin');
View::setFront($adminView);
$app->setView($adminView);
$app->run();
