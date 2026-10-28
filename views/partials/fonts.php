<?php
// Font links for <head>, from $site->fonts.
$fonts = $site->fonts;
?>
<?php if ($fonts->source === 'local'): ?>
<?php foreach ($fonts->preloads as $fontFile): ?><link rel="preload" href="<?= e($fontFile) ?>" as="font" type="font/woff2" crossorigin>
<?php endforeach ?>
<link rel="stylesheet" href="<?= e($fonts->cssUrl) ?>">
<?php elseif ($fonts->source === 'google'): ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="<?= e($fonts->cssUrl) ?>">
<?php endif ?>
