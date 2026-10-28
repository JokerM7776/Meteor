<?php
// Home page: sections in the order chosen in Settings → Home page.
$home = $page->home;
if (!in_array('intro', $home->sections, true)): ?><h1 class="sr-only"><?= e($home->heading) ?></h1><?php endif;
echo slot('home_before');
foreach ($home->sections as $section):
if ($section === 'intro'): ?>
<section class="relative overflow-hidden border-b border-slate-200 dark:border-slate-800">
<?php if ($home->decoration): ?><svg class="absolute -end-10 -top-5 max-w-[60%] text-orange-500 opacity-60 md:opacity-100" width="520" height="360" viewBox="0 0 520 360" aria-hidden="true"><path d="M500 20 L180 300" stroke="currentColor" stroke-width="3"/><path class="text-orange-300" d="M470 10 L260 194" stroke="currentColor" stroke-width="2"/><path class="text-orange-200" d="M515 90 L330 252" stroke="currentColor" stroke-width="2"/><circle cx="176" cy="304" r="9" fill="currentColor"/><g class="text-slate-300 dark:text-slate-600" fill="currentColor"><circle cx="420" cy="230" r="2"/><circle cx="300" cy="80" r="2"/><circle cx="120" cy="140" r="1.5"/></g></svg><?php endif ?>
<div class="relative mx-auto flex max-w-[1120px] flex-col gap-4 px-4 pt-10 pb-8 md:gap-6 md:px-6 md:pt-24 md:pb-[88px]">
<?php if ($home->eyebrow !== ''): ?><p class="eyebrow"><?= e($home->eyebrow) ?></p><?php endif ?>
<h1 class="max-w-[16ch] text-[44px] leading-none font-bold tracking-[-.035em] text-balance md:text-[80px]"><?= e($home->heading) ?></h1>
<?php if ($home->intro !== ''): ?><p class="max-w-[56ch] text-[17px] leading-relaxed text-slate-700 md:text-[19px] dark:text-slate-300"><?= nl2br(e($home->intro), false) ?></p><?php endif ?>
<?php if ($home->buttons): ?><div class="flex flex-wrap gap-3 pt-2"><?php foreach ($home->buttons as $i => $button): ?><a class="<?= $i === 0 ? 'button-primary' : 'button-secondary' ?>" href="<?= e($button->url) ?>"<?= $button->external ? ' rel="noopener"' : '' ?>><?= e($button->label) ?><?= $i === 0 ? ' →' : '' ?></a><?php endforeach ?></div><?php endif ?>
</div></section>
<?php elseif ($section === 'pinned' && $page->pinned): ?>
<section aria-labelledby="pinned" class="mx-auto max-w-[1120px] px-4 pt-10 pb-2 md:px-6 md:pt-[72px]">
<h2 id="pinned" class="eyebrow mb-5 md:mb-6"><?= e(t('pinned')) ?></h2>
<div class="grid gap-5 md:grid-cols-2 md:gap-6">
<?php foreach ($page->pinned as $post): ?><article class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 md:p-7 dark:border-slate-800 dark:bg-slate-900">
<?php if ($post->cover !== null): ?><div class="h-[180px] overflow-hidden rounded-[10px]"><?= partial('cover', ['post' => $post]) ?></div><?php endif ?>
<div class="flex flex-wrap items-center gap-2 font-mono text-xs text-slate-600 dark:text-slate-400"><?php if ($post->category): ?><a class="accent inline-flex min-h-11 min-w-11 items-center uppercase" href="<?= e($post->category->url) ?>"><?= e($post->category->name) ?></a><span aria-hidden="true">·</span><?php endif ?><time datetime="<?= e($post->published->iso) ?>"><?= e($post->published->long) ?></time><span aria-hidden="true">·</span><span><?= e(t('reading_minutes', ['minutes' => $post->readingMinutes])) ?></span></div>
<h3 class="text-2xl leading-[1.15] font-bold tracking-tight md:text-[28px]"><a class="-my-2 inline-block py-2" href="<?= e($post->url) ?>"><?= e($post->title) ?></a></h3>
<?php if ($post->summary !== ''): ?><p class="font-serif text-lg leading-relaxed text-slate-700 dark:text-slate-300"><?= e($post->summary) ?></p><?php endif ?>
<div class="mt-auto"><?= partial('read-link', ['post' => $post, 'button' => true]) ?></div></article><?php endforeach ?>
</div></section>
<?php elseif ($section === 'latest'): ?>
<section aria-labelledby="latest" class="mx-auto max-w-[1120px] px-4 pt-10 pb-6 md:px-6 md:pt-14">
<div class="mb-2 flex items-baseline justify-between gap-4 md:mb-4"><h2 id="latest" class="eyebrow"><?= e(t('latest')) ?></h2><?php if ($home->hasPosts): ?><a class="accent inline-flex min-h-11 items-center font-semibold" href="<?= e($site->postsUrl) ?>"><?= e(t('view_all_posts')) ?> →</a><?php endif ?></div>
<?php foreach ($page->posts as $post): ?><article class="flex flex-wrap gap-x-8 gap-y-1.5 border-t border-slate-200 py-5 md:py-6 dark:border-slate-800">
<time class="w-full pt-1.5 font-mono text-xs text-slate-600 md:w-30 dark:text-slate-400" datetime="<?= e($post->published->iso) ?>"><?= e($post->published->long) ?></time>
<div class="min-w-0 flex-[999_1_420px]"><h3 class="text-xl font-semibold tracking-tight md:mb-1.5 md:text-[22px]"><a class="-my-2 inline-block py-2" href="<?= e($post->url) ?>"><?= e($post->title) ?></a></h3><?php if ($post->summary !== ''): ?><p class="font-serif text-[17px] text-slate-600 dark:text-slate-400"><?= e($post->summary) ?></p><?php endif ?>
<div class="mt-1"><?= partial('read-link', ['post' => $post, 'button' => false]) ?></div></div></article><?php endforeach ?>
<?php if (!$page->posts): ?><p class="border-t border-slate-200 py-6 text-slate-600 dark:border-slate-800 dark:text-slate-400"><?= e(t('empty_posts')) ?></p><?php endif ?>
</section>
<?php elseif ($section === 'categories' && $page->categories): ?>
<section aria-labelledby="categories" class="mx-auto max-w-[1120px] px-4 pt-10 pb-6 md:px-6 md:pt-14">
<h2 id="categories" class="eyebrow mb-4"><?= e(t('browse_categories')) ?></h2>
<ul class="flex flex-wrap gap-2">
<?php foreach ($page->categories as $term): ?><li><a class="button-secondary gap-2 rounded-full" href="<?= e($term->url) ?>"><?= e($term->name) ?><span class="font-mono text-xs text-slate-500 dark:text-slate-400"><?= e($term->count) ?></span></a></li><?php endforeach ?>
</ul></section>
<?php elseif ($section === 'author' && $site->author !== null): ?>
<section aria-labelledby="author-heading" class="mx-auto max-w-[1120px] px-4 pt-10 pb-6 md:px-6 md:pt-14">
<h2 id="author-heading" class="eyebrow mb-4"><?= e(t('about_author')) ?></h2>
<div class="rounded-2xl border border-slate-200 bg-white p-5 md:p-7 dark:border-slate-800 dark:bg-slate-900"><?= partial('author-card', ['author' => $site->author, 'variant' => 'home']) ?></div>
</section>
<?php endif;
endforeach;
echo slot('home_after') ?>
<div class="pb-10 md:pb-20" aria-hidden="true"></div>
