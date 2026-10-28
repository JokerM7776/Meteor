<?php /** Page frame. Uses only the Theme API: $site, $page, $theme, $content and the helpers (see docs/THEMES.md). */ ?>
<!doctype html>
<html lang="<?= e($site->locale->code) ?>" dir="<?= e($site->locale->dir) ?>" data-theme="<?= e($site->appearance->scheme) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($page->title === $site->title ? $page->title : $page->title . ' — ' . $site->title) ?></title>
<meta name="description" content="<?= e($page->description) ?>">
<?php if ($page->noindex): ?><meta name="robots" content="noindex, nofollow"><?php endif ?>
<?php if ($page->canonical !== null): ?><link rel="canonical" href="<?= e($page->canonical) ?>">
<meta property="og:url" content="<?= e($page->canonical) ?>"><?php endif ?>
<meta property="og:title" content="<?= e($page->title) ?>">
<meta property="og:description" content="<?= e($page->description) ?>">
<meta property="og:type" content="<?= $page->type === 'post' ? 'article' : 'website' ?>">
<meta property="og:site_name" content="<?= e($site->title) ?>">
<?php if ($page->shareImage !== null): $share = $page->shareImage; ?><meta property="og:image" content="<?= e($share->url) ?>">
<?php if ($share->type !== ''): ?><meta property="og:image:type" content="<?= e($share->type) ?>">
<?php endif ?><?php if ($share->width > 0 && $share->height > 0): ?><meta property="og:image:width" content="<?= e($share->width) ?>">
<meta property="og:image:height" content="<?= e($share->height) ?>">
<?php endif ?><?php if ($share->alt !== ''): ?><meta property="og:image:alt" content="<?= e($share->alt) ?>">
<?php endif ?><meta name="twitter:card" content="summary_large_image">
<?php else: ?><meta name="twitter:card" content="summary">
<?php endif ?>
<?php foreach ($page->alternates as $code => $href): ?><link rel="alternate" hreflang="<?= e($code) ?>" href="<?= e($href) ?>"><?php endforeach ?>
<?php if ($site->feedUrl !== ''): ?><link rel="alternate" type="application/rss+xml" title="<?= e(t('rss')) ?>" href="<?= e($site->feedUrl) ?>"><?php endif ?>
<?php if ($page->webmention !== ''): ?><link rel="webmention" href="<?= e($page->webmention) ?>">
<?php endif ?><?= partial('favicon') ?>
<?= partial('fonts') ?>
<link rel="stylesheet" href="<?= e(asset('assets/public.css')) ?>">
<?php if ($site->appearance->accentCssUrl !== ''): ?><link rel="stylesheet" href="<?= e($site->appearance->accentCssUrl) ?>"><?php endif ?>
<?php if ($theme->hasAsset('theme.css')): ?><link rel="stylesheet" href="<?= e($theme->asset('theme.css')) ?>">
<?php endif ?><?php if ($theme->settingsCssUrl() !== ''): ?><link rel="stylesheet" href="<?= e($theme->settingsCssUrl()) ?>">
<?php endif ?><?php if ($site->appearance->customCss): ?><link rel="stylesheet" href="/custom.css"><?php endif ?>
<?= slot('head') ?></head>
<body id="top" class="relative flex min-h-screen flex-col <?= $page->type === 'not_found' ? 'bg-slate-900 text-slate-100' : 'bg-slate-50' ?> font-display text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
<?= slot('body_start') ?><a class="sr-only focus:not-sr-only focus:inline-flex focus:self-start focus:min-h-11 focus:items-center" href="#main"><?= e(t('skip_content')) ?></a>
<?php if ($site->announcements) { echo partial('announcements'); } ?>
<header class="<?= $page->type === 'not_found' ? '[&]:bg-slate-900 [&]:text-slate-100 [&]:border-slate-800' : '' ?> border-b border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
<div class="relative mx-auto flex max-w-[1120px] items-center justify-between gap-2 px-4 py-2 md:px-6 md:py-4">
<a href="<?= e($site->homeUrl) ?>" class="flex min-h-11 items-center gap-2 text-xl font-bold tracking-tight md:text-2xl"><?php if ($site->header->showIcon) { echo partial('mark'); } ?><span<?= !$site->header->showTitle ? ' class="sr-only"' : '' ?>><?= e($site->title) ?></span></a>
<div class="flex items-center gap-2 md:gap-6">
<div class="hidden md:block"><?= partial('nav') ?></div>
<?php if ($site->header->showSwitcher): ?>
<?php $pills = count($site->languages) <= 4; if ($pills): ?>
<nav aria-label="<?= e(t('language')) ?>" class="hidden gap-0.5 rounded-full border border-slate-200 bg-slate-50 p-0.5 md:flex dark:border-slate-700 dark:bg-slate-950">
<?php foreach ($site->languages as $language): ?>
<a href="<?= e($language->url) ?>" lang="<?= e($language->code) ?>" <?= $language->current ? 'aria-current="page"' : '' ?> class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-full px-3.5 text-xs font-semibold whitespace-nowrap <?= $language->current ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900' : 'text-slate-600 dark:text-slate-300' ?>"><?= e($language->shortLabel) ?></a>
<?php endforeach ?>
</nav>
<?php endif // A menu on phones (and everywhere with many languages), so the header never wraps. ?>
<details class="<?= $pills ? 'md:hidden' : 'md:relative' ?>">
<summary class="flex size-11 cursor-pointer list-none items-center justify-center gap-2 rounded-lg text-slate-600 hover:bg-slate-100 md:w-auto md:rounded-full md:border md:border-slate-200 md:bg-slate-50 md:px-3 md:text-xs md:font-semibold md:text-slate-700 dark:text-slate-300 dark:hover:bg-slate-800 md:dark:border-slate-700 md:dark:bg-slate-950 md:dark:text-slate-200 [&::-webkit-details-marker]:hidden" title="<?= e(t('language')) ?>"><svg width="20" height="20" viewBox="0 0 16 16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.25"><circle cx="8" cy="8" r="6.25"/><path d="M1.75 8h12.5M8 1.75c1.8 1.9 2.6 4 2.6 6.25S9.8 12.35 8 14.25C6.2 12.35 5.4 10.25 5.4 8S6.2 3.65 8 1.75"/></svg><span class="sr-only"><?= e(t('language')) ?>: </span><span lang="<?= e($site->locale->code) ?>" class="sr-only md:not-sr-only md:whitespace-nowrap"><?= e($site->locale->shortLabel) ?></span><svg class="hidden md:block" width="10" height="10" viewBox="0 0 10 10" aria-hidden="true"><path d="M2 3.5 5 6.5 8 3.5" fill="none" stroke="currentColor" stroke-width="1.5"/></svg></summary>
<nav aria-label="<?= e(t('language')) ?>" class="absolute end-4 top-full z-20 mt-1 max-h-[70vh] w-60 md:end-0 md:top-auto md:mt-2 overflow-y-auto rounded-xl border border-slate-200 bg-white p-2 shadow-lg dark:border-slate-700 dark:bg-slate-900">
<p class="eyebrow px-3 pt-1 pb-2"><?= e(t('language')) ?></p>
<ul>
<?php foreach ($site->languages as $language): ?>
<li><a href="<?= e($language->url) ?>" <?= $language->current ? 'aria-current="page"' : '' ?> class="flex min-h-11 items-center justify-between gap-3 rounded-lg px-3 text-sm hover:bg-slate-100 aria-[current=page]:font-semibold dark:hover:bg-slate-800"><span lang="<?= e($language->code) ?>" dir="<?= e($language->dir) ?>"><?= e($language->nativeName) ?></span><span class="font-mono text-xs text-slate-600 dark:text-slate-400"><?= $language->current ? '<span aria-hidden="true">✓</span>' : e($language->code) ?></span></a></li>
<?php endforeach ?>
</ul>
</nav>
</details>
<?php endif ?>
<?php if ($site->header->showSearch): ?>
<a href="<?= e($site->searchUrl) ?>" aria-label="<?= e(t('search')) ?>" title="<?= e(t('search')) ?>" <?= $page->active === 'search' ? 'aria-current="page"' : '' ?> class="flex size-11 items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100 aria-[current=page]:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800 dark:aria-[current=page]:bg-slate-800"><svg width="20" height="20" viewBox="0 0 20 20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"><circle cx="8.75" cy="8.75" r="5.5"/><path d="m13 13 4 4"/></svg></a>
<?php endif ?>
<?php if ($site->appearance->switchEnabled): ?>
<details class="relative"><summary aria-label="<?= e(t('color_scheme')) ?>" title="<?= e(t('color_scheme')) ?>" class="flex size-11 cursor-pointer list-none items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800 [&::-webkit-details-marker]:hidden"><svg width="20" height="20" viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="7.25" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M10 2.75a7.25 7.25 0 0 1 0 14.5z" fill="currentColor"/></svg></summary>
<div class="absolute end-0 z-20 mt-2 w-44 rounded-xl border border-slate-200 bg-white p-2 shadow-lg dark:border-slate-700 dark:bg-slate-900"><p class="eyebrow px-3 pt-1 pb-2"><?= e(t('color_scheme')) ?></p>
<?php foreach ($site->appearance->links as $option): ?><a rel="nofollow" href="<?= e($option->url) ?>" <?= $option->current ? 'aria-current="true"' : '' ?> class="flex min-h-11 items-center justify-between rounded-lg px-3 text-sm hover:bg-slate-100 aria-[current=true]:font-semibold dark:hover:bg-slate-800"><?= e($option->label) ?><?php if ($option->current): ?><span aria-hidden="true">✓</span><?php endif ?></a><?php endforeach ?>
</div></details>
<?php endif ?>
<details class="relative md:hidden"><summary aria-label="<?= e(t('menu')) ?>" class="flex size-11 cursor-pointer list-none items-center [&::-webkit-details-marker]:hidden justify-center rounded-lg border border-slate-300 dark:border-slate-700"><svg width="20" height="20" viewBox="0 0 20 20" aria-hidden="true"><path d="M3 6h14M3 10h14M3 14h14" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/></svg></summary><div class="absolute end-0 z-20 mt-2 w-56 rounded-xl border border-slate-200 bg-white p-2 shadow-lg dark:border-slate-700 dark:bg-slate-900"><?= partial('nav', ['stacked' => true]) ?></div></details>
</div></div></header>
<?= slot('header_after') ?><main id="main" class="flex-1"><?= slot('main_before') ?><?= $content ?><?= slot('main_after') ?></main>
<?php
$footer = $site->footer; $live = $site->liveLanguages;
$columns = ($footer->showLanguages && $live) || ($footer->showFeeds && $live) || ($footer->showSite && $footer->menu);
?>
<?= slot('footer_before') ?><?php if ($footer->brand !== 'none' || $columns || $footer->copyright !== ''): ?>
<footer class="bg-slate-900 text-slate-300">
<?php if ($footer->brand !== 'none' || $columns): ?>
<div class="mx-auto flex max-w-[1120px] flex-wrap justify-between gap-10 px-4 py-12 md:px-6">
<?php if ($footer->brand === 'author'): ?><div class="max-w-sm"><?= partial('author-card', ['author' => $site->author, 'variant' => 'footer']) ?></div>
<?php elseif ($footer->brand === 'site'): ?>
<div class="max-w-xs"><div class="mb-3 flex items-center gap-2 text-xl font-bold text-white"><?php if ($site->header->showIcon) { echo partial('mark'); } ?><?= e($site->title) ?></div><?php if ($site->tagline !== ''): ?><p class="mb-3 text-sm text-slate-400"><?= e($site->tagline) ?></p><?php endif ?><?php if ($footer->text !== ''): ?><p class="text-sm leading-relaxed text-slate-400"><?= nl2br(e($footer->text), false) ?></p><?php endif ?></div>
<?php endif ?>
<?php if ($columns): ?>
<div class="flex flex-wrap gap-8 text-sm md:gap-12">
<?php if ($footer->showLanguages && $live): ?><div><h2 class="eyebrow text-slate-400"><?= e(t('read_in')) ?></h2><?php foreach ($live as $language): ?><a class="flex min-h-11 items-center text-white" lang="<?= e($language->code) ?>" href="<?= e($language->homeUrl) ?>"><?= e($language->nativeName) ?></a><?php endforeach ?></div><?php endif ?>
<?php if ($footer->showFeeds && $live): ?><div><h2 class="eyebrow text-slate-400"><?= e(t('feeds')) ?></h2><?php foreach ($live as $language): ?><a class="flex min-h-11 items-center text-white" href="<?= e($language->feedUrl) ?>"><?= e($language->feedUrl) ?></a><?php endforeach ?></div><?php endif ?>
<?php if ($footer->showSite && $footer->menu): ?><div><h2 class="eyebrow text-slate-400"><?= e($footer->menuHeading) ?></h2>
<?php foreach ($footer->menu as $link): ?><a class="flex min-h-11 items-center text-white" href="<?= e($link->url) ?>"<?= $link->newTab ? ' target="_blank" rel="noopener"' : ($link->external ? ' rel="noopener"' : '') ?><?= $link->current ? ' aria-current="page"' : '' ?>><?= e($link->label) ?><?php if ($link->newTab) { echo partial('new-tab', ['icon' => $footer->newTabIcon]); } ?></a><?php endforeach ?>
</div><?php endif ?>
</div>
<?php endif ?>
</div>
<?php endif ?>
<?= slot('footer') ?><?php if ($footer->copyright !== ''): ?><div class="<?= $footer->brand !== 'none' || $columns ? 'border-t border-slate-800' : '' ?>"><p class="mx-auto max-w-[1120px] px-4 py-5 text-sm text-slate-400 md:px-6"><?= e($footer->copyright) ?></p></div><?php endif ?>
</footer>
<?php endif ?>
<?php // Back to top, without JavaScript: the link sits a screen and a bit down the page and sticks to the
// bottom corner once scrolled into view; on short pages it stays clipped away. ?>
<div class="pointer-events-none absolute inset-0 overflow-clip print:hidden"><a href="#top" class="back-to-top" title="<?= e(t('back_to_top')) ?>"><svg width="20" height="20" viewBox="0 0 20 20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M10 16V4M4.5 9.5 10 4l5.5 5.5"/></svg><span class="sr-only"><?= e(t('back_to_top')) ?></span></a></div>
<?= slot('body_end') ?></body></html>
