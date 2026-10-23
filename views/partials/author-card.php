<?php
// The site author as a representative h-card (https://microformats.org/wiki/representative-h-card-authoring):
// u-url and u-uid are this language's home page, and it is linked with rel="me".
// Expects $card (App\Services\Author::card), $cardVariant ('footer' or 'home') and $locale.
$footer = $cardVariant === 'footer';
$home = absolute_url('/' . $locale . '/');
?>
<div class="h-card flex items-start gap-4">
<?php if ($card['avatar'] !== ''): ?><img class="u-photo <?= $footer ? 'size-14' : 'size-20 md:size-24' ?> shrink-0 rounded-full object-cover" src="<?= e(absolute_url($card['avatar'])) ?>" alt="" width="96" height="96" loading="lazy"><?php endif ?>
<div class="min-w-0">
<a class="p-name u-url u-uid inline-flex min-h-11 items-center font-bold <?= $footer ? 'text-xl text-white' : 'text-2xl tracking-tight text-slate-900 dark:text-white' ?>" rel="me" href="<?= e($home) ?>"><?= e($card['name']) ?></a>
<?php if ($card['bio'] !== ''): ?><p class="p-note <?= $footer ? 'text-sm text-slate-400' : 'font-serif text-lg text-slate-700 dark:text-slate-300' ?> leading-relaxed"><?= e($card['bio']) ?></p><?php endif ?>
<?php if ($card['links']): ?>
<ul class="mt-2 flex flex-wrap gap-x-4 text-sm">
<?php foreach ($card['links'] as $link): ?><li><a class="<?= $link['email'] ? 'u-email' : 'u-url' ?> inline-flex min-h-11 items-center <?= $footer ? 'text-white underline-offset-4 hover:underline' : 'accent font-semibold' ?>" <?= $link['email'] ? '' : 'rel="me noopener"' ?> href="<?= e($link['url']) ?>"><?= e($link['label']) ?></a></li><?php endforeach ?>
</ul>
<?php endif ?>
</div>
</div>
