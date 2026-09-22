<?php $sc = (float)$v['score']; ?>
<a class="kcard" href="/index.php?s=/vod/detail&id=<?= (int)$v['id'] ?>">
  <div class="kc-pic"><img src="<?= e(pic_url($v['pic'])) ?>" loading="lazy" alt="<?= e($v['name']) ?>">
    <?php if ($v['remarks']): ?><span class="kc-rm"><?= e($v['remarks']) ?></span><?php endif; ?>
  </div>
  <?php if ($sc > 0): ?><div class="kc-score">评分: <?= rtrim(rtrim(number_format($sc, 1), '0'), '.') ?></div><?php endif; ?>
  <div class="kc-nm"><?= e($v['name']) ?></div>
</a>
