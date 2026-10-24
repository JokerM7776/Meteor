<?php
// Font links for <head>. Expects $fontSource: the stored setting (local, google or system).
[$fontsFrom, $fontPreload] = \App\Services\Fonts::resolve($fontSource ?? 'local');
?>
<?php if ($fontsFrom === 'local'): ?>
<?php foreach ($fontPreload as $fontFile): ?><link rel="preload" href="/<?= e(\App\Services\Fonts::DIRECTORY . '/' . $fontFile) ?>" as="font" type="font/woff2" crossorigin>
<?php endforeach ?>
<link rel="stylesheet" href="<?= e(asset(\App\Services\Fonts::DIRECTORY . '/fonts.css')) ?>">
<?php elseif ($fontsFrom === 'google'): ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="<?= e(\App\Services\Fonts::GOOGLE) ?>">
<?php endif ?>
