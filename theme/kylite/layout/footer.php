<?php $links = Db::fetchAll("SELECT * FROM ky_link WHERE status=1 ORDER BY sort ASC, id ASC LIMIT 20"); ?>
<footer class="kfooter">
  <div><?= e(config('site_icp', '')) ?></div>
  <div>© <?= date('Y') ?> <?= e(config('site_name', '坤影影视')) ?> · Powered by 坤影CMS</div>
</footer>
