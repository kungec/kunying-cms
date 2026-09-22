<?php
/** dsv1 文章页(公告/资讯) */
$pageTitle = $article ? $article['title'] : '网站公告';
$searchWd = '';
include theme_path('layout/header.php');
?>
<div class="wrap" style="margin-top:86px;max-width:900px">
  <?php if ($article): ?>
    <div class="vdesc" style="padding:26px">
      <h1 style="font-size:20px;color:#fff;margin-bottom:10px"><?= e($article['title']) ?></h1>
      <p style="color:var(--sub);font-size:12px;margin-bottom:16px"><?= date('Y-m-d H:i', (int)$article['addtime']) ?></p>
      <div style="line-height:2"><?= nl2text((string)$article['content']) ?></div>
    </div>
  <?php else: ?>
    <div class="sec-h"><h3>网站公告</h3></div>
    <?php foreach ($articles as $a): ?>
    <a class="vdesc" style="display:block;margin-bottom:10px" href="/index.php?s=/index/article&id=<?= (int)$a['id'] ?>">
      <b style="color:#fff"><?= e($a['title']) ?></b>
      <span style="float:right;color:var(--sub);font-size:12px"><?= date('m-d', (int)$a['addtime']) ?></span>
    </a>
    <?php endforeach; ?>
    <?php if (config('show_api_notice', '0') == '1') foreach (License::notices() as $n): ?>
    <div class="notice" style="background:#241d10;border:1px solid rgba(216,169,71,.35);color:#d8a947;border-radius:10px;padding:12px 16px;margin-bottom:10px;font-size:13px">
      📢 <b><?= e($n['title']) ?></b><div style="margin-top:6px;white-space:pre-wrap"><?= e($n['content']) ?></div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<?php include theme_path('layout/footer.php'); ?>
