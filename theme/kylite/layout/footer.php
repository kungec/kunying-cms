<?php $links = cache_get('kylite_links');
if (!is_array($links)) {
    $links = Db::fetchAll("SELECT * FROM ky_link WHERE status=1 ORDER BY sort ASC, id ASC LIMIT 20");
    cache_set('kylite_links', $links, 600);
} ?>
<footer class="kfooter">
  <?php if ($links): ?>
  <div class="kflink">
    <span class="kflink-t">友情链接</span>
    <?php foreach ($links as $lk): $u = (string)$lk['url'];
      if (strpos($u, 'http://') !== 0 && strpos($u, 'https://') !== 0) continue; ?>
    <a href="<?= e($u) ?>" target="_blank" rel="nofollow noopener"><?= e($lk['name']) ?></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <div><?= e(config('site_icp', '')) ?></div>
  <div>© <?= date('Y') ?> <?= e(config('site_name', '坤影影视')) ?> · Powered by 坤影CMS</div>
</footer>
