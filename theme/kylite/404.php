<?php
/** kylite 404页 */
$pageTitle = '页面不存在';
$searchWd = '';
if (!is_file(theme_path('layout/header.php'))) { echo '404'; exit; }
include theme_path('layout/header.php');
?>
<div style="min-height:70vh;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:16px;text-align:center;padding:80px 20px 0">
  <div style="font-size:64px">🎬</div>
  <h2 style="font-size:20px"><?= e($msg ?? '页面不存在或已被删除') ?></h2>
  <a class="btn-main" style="width:auto;padding:10px 34px" href="/">返回首页</a>
</div>
<?php include theme_path('layout/footer.php'); ?>
