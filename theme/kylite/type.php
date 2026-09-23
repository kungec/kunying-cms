<?php
/** kylite 分类/筛选页(电影海报模式) */
$pageTitle = $type ? $type['name'] : '影片库';
$searchWd = '';
include theme_path('layout/header.php');
?>
<div class="wrap" style="margin-top:86px">
  <div class="filter">
    <div class="frow"><b>分类</b>
      <a class="<?= $type ? '' : 'on' ?>" href="/index.php?s=/vod/type&id=0&order=<?= e($order) ?>">全部</a>
      <?php foreach ($types as $t): ?>
      <a class="<?= $type && $type['id'] == $t['id'] ? 'on' : '' ?>" href="/index.php?s=/vod/type&id=<?= (int)$t['id'] ?>&order=<?= e($order) ?>"><?= e($t['name']) ?></a>
      <?php endforeach; ?>
    </div>
    <div class="frow"><b>排序</b>
      <?php foreach (['time' => '最近更新', 'hits' => '最热', 'score' => '评分', 'new' => '最新上架'] as $k => $n): ?>
      <a class="<?= $order === $k ? 'on' : '' ?>" href="/index.php?s=/vod/type&id=<?= (int)($type['id'] ?? 0) ?>&order=<?= $k ?>"><?= $n ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="mgrid" id="wfGrid">
    <?php foreach ($list as $v): ?>
    <a class="mcard" href="/index.php?s=/vod/detail&id=<?= (int)$v['id'] ?>">
      <div class="pic"><img src="<?= e(pic_url($v['pic'])) ?>" loading="lazy" alt="<?= e($v['name']) ?>">
        <?php if ((float)$v['score'] > 0): ?><span class="bd"><?= rtrim(rtrim(number_format((float)$v['score'], 1), '0'), '.') ?></span><?php endif; ?>
        <?php if ($v['remarks']): ?><span class="rm"><?= e($v['remarks']) ?></span><?php endif; ?>
        <?php if ($v['vip']): ?><span class="vip">vip</span><?php endif; ?>
      </div>
      <div class="nm"><?= e($v['name']) ?></div>
      <div class="ds"><?= e(trim((string)$v['area'] . (($v['area'] && $v['year']) ? ' · ' : '') . (string)$v['year'])) ?></div>
    </a>
    <?php endforeach; ?>
  </div>
  <?php if (empty($list)): ?><p style="text-align:center;color:var(--sub);padding:50px 0">暂无影片</p><?php endif; ?>
  <div id="wfMore" style="text-align:center;padding:14px 0"><button class="btn-main" style="width:auto;padding:10px 34px" id="wfBtn">加载更多</button></div>
  <script>
  window.wfConfig = {grid:'#wfGrid', next:<?= $page + 1 ?>, mode:'type', cardStyle:'poster', typeId:<?= (int)($type['id'] ?? 0) ?>, order:'<?= e($order) ?>', cls:<?= json_encode($class, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, year:<?= json_encode($year, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, area:<?= json_encode($area, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>};
  </script>
</div>
<?php include theme_path('layout/footer.php'); ?>
