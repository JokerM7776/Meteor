<?php $favicon = $site->favicon; ?>
<link rel="icon" href="<?= e($favicon->url) ?>"<?= $favicon->type !== '' ? ' type="' . e($favicon->type) . '"' : '' ?>>
<?php if ($site->icon !== null && preg_match('/\.(png|jpg|webp)$/D', $site->icon->url)): ?>
<link rel="apple-touch-icon" href="<?= e($site->icon->url) ?>">
<?php endif ?>
