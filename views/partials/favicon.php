<link rel="icon" href="<?= e($siteIcon ?: '/assets/favicon.svg') ?>"<?= $siteIcon === '' ? ' type="image/svg+xml"' : '' ?>>
<?php if ($siteIcon !== '' && preg_match('/\.(png|jpg|webp)$/D', $siteIcon)): ?>
<link rel="apple-touch-icon" href="<?= e($siteIcon) ?>">
<?php endif ?>
