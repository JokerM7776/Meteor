<div class="mx-auto flex max-w-[1120px] flex-col gap-10 px-4 py-10 md:gap-12 md:px-6 md:py-16">
<?= slot('archive_before') ?><header><h1 class="mb-4 text-[40px] leading-tight font-bold tracking-tighter md:text-6xl"><?= e($page->title) ?></h1><p class="font-serif text-xl text-slate-700 dark:text-slate-300"><?= e($page->description) ?></p></header>
<div class="rounded-[14px] border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
<?php
// Each row of chips folds to three rows on phones and two from md up, with "View all" when more are hidden.
$chipRows = function (string $id, string $label, string $gap, array $chips, string $extra = ''): void { ?>
<div class="<?= e($extra) ?>"><input type="checkbox" id="<?= e($id) ?>" class="chip-toggle sr-only"><div class="chip-rows flex flex-wrap items-center <?= e($gap) ?>"><span class="eyebrow inline-flex min-h-11 w-24 items-center"><?= e($label) ?></span><?php foreach ($chips as $chip) { echo $chip; } ?>
<label for="<?= e($id) ?>" class="chip-more bg-white dark:bg-slate-900"><span class="button-secondary rounded-full"><?= e(t('view_all')) ?> · <?= e(count($chips)) ?></span></label></div>
<label for="<?= e($id) ?>" class="chip-less button-secondary mt-2 rounded-full"><?= e(t('show_less')) ?></label></div>
<?php };
$chip = fn (string $class, string $href, bool $current, string $text): string => '<a class="' . $class . '" href="' . e($href) . '"' . ($current ? ' aria-current="page"' : '') . '>' . e($text) . '</a>';
$categoryChips = [$chip('button-secondary rounded-full', $site->postsUrl, $page->term === null, t('all'))];
foreach ($page->categories as $term) { $categoryChips[] = $chip('button-secondary rounded-full', $term->url, $term->url === $page->term?->url, $term->name); }
$tagChips = [];
foreach ($page->tags as $term) { $tagChips[] = $chip('tag', $term->url, $term->url === $page->term?->url, '#' . $term->name); }
$chipRows('more-categories', t('category'), 'gap-2 [--gap:0.5rem]', $categoryChips);
if ($tagChips) { $chipRows('more-tags', t('tags'), 'gap-1.5 [--gap:0.375rem]', $tagChips, 'mt-4 border-t border-slate-100 pt-4 dark:border-slate-800'); }
?>
</div>
<?php foreach ($page->archive as $year): ?><section class="flex flex-wrap gap-x-12 gap-y-6"><h2 class="w-40 text-5xl leading-none font-bold tracking-tight text-slate-500 dark:text-slate-500"><?= e($year->year) ?></h2><div class="min-w-0 flex-[999_1_560px] space-y-8">
<?php foreach ($year->months as $month): ?><div><h3 class="eyebrow mb-2 text-orange-700 dark:text-orange-400"><?= e($month->name) ?></h3>
<?php foreach ($month->posts as $post): ?><a href="<?= e($post->url) ?>" class="flex flex-wrap items-baseline gap-x-5 gap-y-1 border-t border-slate-200 py-4 dark:border-slate-800"><time class="w-12 font-mono text-sm text-slate-600 dark:text-slate-400" datetime="<?= e($post->published->iso) ?>"><?= e($post->published->short) ?></time><span class="flex-[999_1_320px] text-xl font-semibold tracking-tight"><?= e($post->title) ?></span><span class="text-sm text-slate-600 dark:text-slate-400"><?= e($post->category?->name ?? '') ?> · <?= e(t('reading_minutes', ['minutes' => $post->readingMinutes])) ?></span></a><?php endforeach ?></div><?php endforeach ?>
</div></section><?php endforeach ?>
<?php if (!$page->archive): ?><p><?= e(t('empty_posts')) ?></p><?php endif ?>
<?php $p = $page->pagination; ?>
<nav aria-label="<?= e(t('pagination')) ?>" class="flex flex-wrap items-center justify-between gap-4">
<?php if ($p->previous !== null): ?><a class="button-secondary" rel="prev" href="<?= e($p->previous) ?>">← <?= e(t('newer')) ?></a><?php else: ?><span class="button-secondary opacity-50" aria-disabled="true">← <?= e(t('newer')) ?></span><?php endif ?>
<div class="flex flex-wrap gap-1"><?php foreach ($p->pages as $number): ?><a class="inline-flex size-11 items-center justify-center rounded-lg font-mono <?= $number->current ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900' : '' ?>" <?= $number->current ? 'aria-current="page"' : '' ?> href="<?= e($number->url) ?>"><?= e($number->label) ?></a><?php endforeach ?></div>
<?php if ($p->next !== null): ?><a class="button-primary" rel="next" href="<?= e($p->next) ?>"><?= e(t('older')) ?> →</a><?php else: ?><span class="button-secondary opacity-50" aria-disabled="true"><?= e(t('older')) ?> →</span><?php endif ?>
</nav><?= slot('archive_after') ?></div>
