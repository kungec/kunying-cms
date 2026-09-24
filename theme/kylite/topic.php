<?php
/** kylite 专题页 */
$pageTitle = $topic ? $topic['name'] . ' - 专题' : '专题';
$searchWd = '';
include theme_path('layout/header.php');
?>
<div class="wrap" style="margin-top:14px">
  <div class="sec-h"><h3>专题列表</h3></div>
  <div class="tgrid" style="margin-bottom:26px">
    <?php foreach ($topics as $t): ?>
    <a class="tcard" href="/index.php?s=/vod/topic&id=<?= (int)$t['id'] ?>">
      <img src="<?= e(pic_url($t['pic'])) ?>" alt="<?= e($t['name']) ?>" loading="lazy">
      <b><?= e($t['name']) ?></b>
    </a>
    <?php endforeach; ?>
  </div>
  <?php if ($topic): ?>
  <div class="sec-h"><h3><?= e($topic['name']) ?></h3></div>
  <?php if ($topic['description']): ?><p style="color:var(--sub);font-size:13px;margin-bottom:14px"><?= e($topic['description']) ?></p><?php endif; ?>
  <div class="grid g6">
    <?php foreach ($list as $v): include theme_path('_vcard.php'); endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php include theme_path('layout/footer.php'); ?>
