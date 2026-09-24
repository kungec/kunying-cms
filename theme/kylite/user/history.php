<?php $pageTitle = '观看历史'; include theme_path('layout/header.php'); ?>
<div class="wrap" style="margin-top:14px">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:16px">
    <b style="font-size:20px;color:var(--txt)">🕐 观看历史</b>
    <?php if ($records): ?><button class="btn-main" style="width:auto;padding:8px 18px;background:var(--red,#e5322d)" onclick="clearHist()">🗑 清空历史</button><?php endif; ?>
  </div>
  <?php if (empty($records)): ?>
  <p style="text-align:center;color:var(--sub);padding:60px 0">暂无观看记录,快去看几部影片吧</p>
  <?php else: ?>
  <div class="hist-grid">
    <?php foreach ($records as $r): ?>
    <div class="hist-card">
      <a class="hp" href="/index.php?s=/vod/detail&id=<?= (int)$r['vod_id'] ?>&ep=<?= (int)$r['episode'] ?>&play=1">
        <img src="<?= e(pic_url($r['pic'])) ?>" loading="lazy" alt="<?= e($r['name']) ?>">
        <span class="rm" style="position:absolute;top:6px;right:6px;background:rgba(229,50,45,.9);color:#fff;font-size:11px;padding:1px 7px;border-radius:4px"><?= e($r['remarks'] ?: '第' . (int)$r['episode'] . '集') ?></span>
        <div class="prog"><i style="width:100%"></i></div>
      </a>
      <a class="hnm" href="/index.php?s=/vod/detail&id=<?= (int)$r['vod_id'] ?>"><?= e($r['name']) ?></a>
      <div class="hmeta">看到第 <?= (int)$r['episode'] ?> 集 · <?= date('m-d H:i', (int)$r['updated']) ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<script>
async function clearHist(){
  if (!confirm('确定清空全部观看历史?')) return;
  var d = new FormData(); d.append('_csrf', '<?= e(Security::csrfToken()) ?>');
  var j = await kyPost('/index.php?s=/user/historyclear', d);
  j.code === 1 ? location.reload() : kyToast(j.msg || '操作失败', false);
}
</script>
<?php include theme_path('layout/footer.php'); ?>
