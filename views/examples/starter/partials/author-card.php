<?php
// Starter author card: a simpler card with the accent rule. $author (Author) and $variant ('footer', 'home' or 'post').
$variant ??= 'home';
?>
<div class="<?= $variant === 'post' ? 'p-author ' : '' ?>h-card starter-rule pt-4">
<a class="p-name u-url inline-flex min-h-11 items-center text-xl font-bold<?= $variant === 'footer' ? ' text-white' : '' ?>" rel="me" href="<?= e(absolute_url($site->homeUrl)) ?>"><?= e($author->name) ?></a>
<?php if ($author->bio !== ''): ?><p class="p-note leading-relaxed"><?= e($author->bio) ?></p><?php endif ?>
<?php if ($author->links): ?><p class="mt-2 flex flex-wrap gap-x-4 text-sm"><?php foreach ($author->links as $link): ?><a class="<?= $link->email ? 'u-email' : 'u-url' ?> inline-flex min-h-11 items-center underline" <?= $link->email ? '' : 'rel="me noopener"' ?> href="<?= e($link->url) ?>"><?= e($link->label) ?></a><?php endforeach ?></p><?php endif ?>
</div>
