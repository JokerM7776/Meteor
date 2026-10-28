<?php $iconType = \App\Services\SiteIcon::type($siteIcon ?: '/assets/favicon.svg'); ?>
<link rel="icon" href="<?= e($siteIcon ?: '/assets/favicon.svg') ?>"<?= $iconType !== '' ? ' type="' . e($iconType) . '"' : '' ?>>
<?php if ($siteIcon !== '' && preg_match('/\.(png|jpg|webp)$/D', $siteIcon)): ?>
<link rel="apple-touch-icon" href="<?= e($siteIcon) ?>">
<?php endif ?>
