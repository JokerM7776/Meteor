<section class="relative overflow-hidden border-b border-slate-200 dark:border-slate-800">
<svg class="absolute -end-10 -top-5 max-w-[60%] opacity-60 md:opacity-100" width="520" height="360" viewBox="0 0 520 360" aria-hidden="true"><path d="M500 20 L180 300" stroke="#f97316" stroke-width="3"/><path d="M470 10 L260 194" stroke="#fdba74" stroke-width="2"/><path d="M515 90 L330 252" stroke="#fed7aa" stroke-width="2"/><circle cx="176" cy="304" r="9" fill="#f97316"/><g fill="#cbd5e1"><circle cx="420" cy="230" r="2"/><circle cx="300" cy="80" r="2"/><circle cx="120" cy="140" r="1.5"/></g></svg>
<div class="relative mx-auto flex max-w-[1120px] flex-col gap-4 px-4 pt-10 pb-8 md:gap-6 md:px-6 md:pb-[88px] md:pt-24">
<p class="eyebrow"><?php $names = []; foreach ($live as $code) { $names[] = $registry[$code]['native_name']; } ?><?= e(implode(' · ', $names)) ?></p>
<h1 class="max-w-[14ch] text-[44px] leading-none font-bold tracking-[-.035em] md:text-[80px]"><?= e($settings->label('home_heading')) ?></h1>
<p class="max-w-[56ch] text-[17px] leading-relaxed md:text-[19px] text-slate-700 dark:text-slate-300"><?= e($settings->label('home_intro')) ?></p>
<div class="flex flex-wrap gap-3 pt-2"><a class="button-primary" href="<?= e('/' . $locale . '/posts') ?>"><?= e(__('read_archive')) ?> →</a><?php if (!$preview): ?><a class="button-secondary hidden md:inline-flex" href="<?= e('/' . $locale . '/rss.xml') ?>"><?= e(__('language_rss', ['language' => $registry[$locale]['name']])) ?></a><?php endif ?></div>
</div></section>
<?php if ($pinned): ?>
<section aria-labelledby="pinned" class="mx-auto hidden max-w-[1120px] px-4 md:block pt-10 pb-6 md:px-6 md:pt-[72px]">
<h2 id="pinned" class="eyebrow mb-6"><?= e(__('pinned')) ?></h2>
<div class="grid gap-6 md:grid-cols-2">
<?php foreach ($pinned as $post): ?><article class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 md:p-7 dark:border-slate-800 dark:bg-slate-900">
<div class="h-[180px] overflow-hidden rounded-[10px]"><?php require __DIR__ . '/../partials/cover.php' ?></div>
<div class="flex flex-wrap items-center gap-2 font-mono text-xs text-slate-600 dark:text-slate-400"><?php if ($post['category_name']): ?><a class="accent uppercase" href="<?= e('/' . $locale . '/categories/' . rawurlencode($post['category_slug'])) ?>"><?= e($post['category_name']) ?></a><span>·</span><?php endif ?><time datetime="<?= e($post['published_at']) ?>"><?= e(localized_date($post['published_at'])) ?></time><span>·</span><span><?= e(__('reading_minutes', ['minutes' => $post['reading_minutes']])) ?></span></div>
<h3 class="text-[28px] leading-[1.15] font-bold tracking-tight"><a href="<?= e('/' . $locale . '/posts/' . rawurlencode($post['slug'])) ?>"><?= e($post['title']) ?></a></h3>
<p class="font-serif text-lg leading-relaxed text-slate-700 dark:text-slate-300"><?= e($post['summary']) ?></p>
<div class="mt-auto"><?php require __DIR__ . '/../partials/badges.php' ?></div></article><?php endforeach ?>
</div></section><?php endif ?>
<section aria-labelledby="latest" class="mx-auto hidden max-w-[1120px] px-4 md:block pt-10 pb-16 md:px-6 md:pt-14 md:pb-24">
<div class="mb-4 flex items-baseline justify-between gap-4"><h2 id="latest" class="eyebrow"><?= e(__('latest')) ?></h2><a class="accent inline-flex min-h-11 items-center font-semibold" href="<?= e('/' . $locale . '/posts') ?>"><?= e(__('view_all_posts')) ?> →</a></div>
<?php foreach ($latest as $post): ?><article class="flex flex-wrap gap-x-8 gap-y-2 border-t border-slate-200 py-6 dark:border-slate-800">
<time class="w-30 pt-1.5 font-mono text-xs text-slate-600 dark:text-slate-400" datetime="<?= e($post['published_at']) ?>"><?= e(localized_date($post['published_at'])) ?></time>
<div class="min-w-0 flex-[999_1_420px]"><h3 class="mb-1.5 text-[22px] font-semibold tracking-tight"><a href="<?= e('/' . $locale . '/posts/' . rawurlencode($post['slug'])) ?>"><?= e($post['title']) ?></a></h3><p class="font-serif text-[17px] text-slate-600 dark:text-slate-400"><?= e($post['summary']) ?></p></div>
<?php require __DIR__ . '/../partials/badges.php' ?></article><?php endforeach ?>
<?php if (!$latest && !$pinned): ?><p><?= e(__('empty_posts')) ?></p><?php endif ?>
</section>

<section aria-labelledby="mobile-latest" class="px-4 py-6 md:hidden">
<h2 id="mobile-latest" class="eyebrow mb-2"><?= e(__('latest')) ?></h2>
<?php foreach ($mobileLatest as $post): ?><article class="border-b border-slate-200 py-4 dark:border-slate-800"><p class="mb-1.5 font-mono text-xs text-slate-600 dark:text-slate-400"><?= e(localized_date($post['published_at'])) ?> · <?= e(__('reading_minutes', ['minutes' => $post['reading_minutes']])) ?></p><h3 class="text-xl leading-tight font-semibold tracking-tight"><a class="inline-flex min-h-11 items-center" href="<?= e('/' . $locale . '/posts/' . rawurlencode($post['slug'])) ?>"><?= e($post['title']) ?></a></h3></article><?php endforeach ?>
<?php if (!$mobileLatest): ?><p><?= e(__('empty_posts')) ?></p><?php endif ?>
</section>
