<?php
// The site author as a representative h-card (https://microformats.org/wiki/representative-h-card-authoring):
// u-url and u-uid are this language's home page, and it is linked with rel="me".
// Expects $card (App\Services\Author::card), $cardVariant ('footer', 'home' or 'post') and $locale.
// The 'post' card sits inside the post's h-entry, so it is also the entry's p-author.
$footer = $cardVariant === 'footer';
$home = absolute_url('/' . $locale . '/');
$photo = match ($cardVariant) { 'footer' => 'size-14', 'post' => 'size-16 md:size-20', default => 'size-20 md:size-24' };
$nameSize = $cardVariant === 'home' ? 'text-2xl' : 'text-xl';
?>
<div class="<?= $cardVariant === 'post' ? 'p-author ' : '' ?>h-card flex items-start gap-4">
<?php if ($card['avatar'] !== ''): ?><img class="u-photo <?= $photo ?> shrink-0 rounded-full object-cover" src="<?= e(absolute_url($card['avatar'])) ?>" alt="" width="96" height="96" loading="lazy"><?php endif ?>
<div class="min-w-0">
<a class="p-name u-url u-uid inline-flex min-h-11 items-center font-bold <?= $footer ? 'text-xl text-white' : $nameSize . ' tracking-tight text-slate-900 dark:text-white' ?>" rel="me" href="<?= e($home) ?>"><?= e($card['name']) ?></a>
<?php if ($card['bio'] !== ''): ?><p class="p-note <?= $footer ? 'text-sm text-slate-400' : 'font-serif text-lg text-slate-700 dark:text-slate-300' ?> leading-relaxed"><?= e($card['bio']) ?></p><?php endif ?>
<?php if ($card['links']): ?>
<ul class="mt-2 flex flex-wrap gap-x-4 text-sm">
<?php foreach ($card['links'] as $link): ?><li><a class="<?= $link['email'] ? 'u-email' : 'u-url' ?> inline-flex min-h-11 items-center <?= $footer ? 'text-white underline-offset-4 hover:underline' : 'accent font-semibold' ?>" <?= $link['email'] ? '' : 'rel="me noopener"' ?> href="<?= e($link['url']) ?>"><?= e($link['label']) ?></a></li><?php endforeach ?>
</ul>
<?php endif ?>
</div>
</div>
