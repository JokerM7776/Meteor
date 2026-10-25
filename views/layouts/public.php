<?php $siteIcon = $settings->icon(); ?>
<!doctype html>
<html lang="<?= e($locale) ?>" dir="<?= e($i18n->direction()) ?>" data-theme="<?= e($scheme) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title === $settings->label('site_title') ? $title : $title . ' — ' . $settings->label('site_title')) ?></title>
<meta name="description" content="<?= e($description) ?>">
<?php if ($noindex): ?><meta name="robots" content="noindex, nofollow"><?php endif ?>
<?php if ($canonical !== null): ?><link rel="canonical" href="<?= e(absolute_url($canonical)) ?>">
<meta property="og:url" content="<?= e(absolute_url($canonical)) ?>"><?php endif ?>
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($description) ?>">
<meta property="og:type" content="<?= isset($item) && !$isPage ? 'article' : 'website' ?>">
<meta property="og:site_name" content="<?= e($settings->label('site_title')) ?>">
<?php foreach ($alternates as $code => $href): ?><link rel="alternate" hreflang="<?= e($code) ?>" href="<?= e(absolute_url($href)) ?>"><?php endforeach ?>
<?php if (!$preview): ?><link rel="alternate" type="application/rss+xml" title="<?= e(__('rss')) ?>" href="<?= e('/' . $locale . '/rss.xml') ?>"><?php endif ?>
<?php if (!empty($webmention)): ?><link rel="webmention" href="<?= e($webmention) ?>">
<?php endif ?><?php require __DIR__ . '/../partials/favicon.php' ?>
<?php require __DIR__ . '/../partials/fonts.php' ?>
<link rel="stylesheet" href="<?= e(asset('assets/public.css')) ?>">
<?php if ($themeCss !== ''): ?><link rel="stylesheet" href="<?= e($themeCss) ?>"><?php endif ?>
<?php if (trim($settings->get('custom_css')) !== ''): ?><link rel="stylesheet" href="/custom.css"><?php endif ?>
</head>
<body id="top" class="relative flex min-h-screen flex-col <?= $is404 ? 'bg-slate-900 text-slate-100' : 'bg-slate-50' ?> font-display text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
<a class="sr-only focus:not-sr-only focus:inline-flex focus:self-start focus:min-h-11 focus:items-center" href="#main"><?= e(__('skip_content')) ?></a>
<?php if (!empty($announcements)) { require __DIR__ . '/../partials/announcements.php'; } ?>
<header class="<?= $is404 ? '[&]:bg-slate-900 [&]:text-slate-100 [&]:border-slate-800' : '' ?> border-b border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
<div class="relative mx-auto flex max-w-[1120px] items-center justify-between gap-2 px-4 py-2 md:px-6 md:py-4">
<a href="<?= e('/' . $locale . '/') ?>" class="flex min-h-11 items-center gap-2 text-xl font-bold tracking-tight md:text-2xl"><?php if ($settings->enabled('header_show_icon')) { require __DIR__ . '/../partials/mark.php'; } ?><span<?= !$settings->enabled('header_show_title') ? ' class="sr-only"' : '' ?>><?= e($settings->label('site_title')) ?></span></a>
<div class="flex items-center gap-2 md:gap-6">
<div class="hidden md:block"><?php require __DIR__ . '/../partials/nav.php' ?></div>
<?php if ($settings->enabled('header_show_switcher')): $short = fn (string $code): string => $settings->get('language_label', $code, mb_strlen($registry[$code]['native_name']) <= 4 ? $registry[$code]['native_name'] : strtoupper($code)); ?>
<?php $pills = count($switcher) <= 4; if ($pills): ?>
<nav aria-label="<?= e(__('language')) ?>" class="hidden gap-0.5 rounded-full border border-slate-200 bg-slate-50 p-0.5 md:flex dark:border-slate-700 dark:bg-slate-950">
<?php foreach ($switcher as $code => $href): ?>
<a href="<?= e($href) ?>" lang="<?= e($code) ?>" <?= $locale === $code ? 'aria-current="page"' : '' ?> class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-full px-3.5 text-xs font-semibold whitespace-nowrap <?= $locale === $code ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900' : 'text-slate-600 dark:text-slate-300' ?>"><?= e($short($code)) ?></a>
<?php endforeach ?>
</nav>
<?php endif // A menu on phones (and everywhere with many languages), so the header never wraps. ?>
<details class="<?= $pills ? 'md:hidden' : 'md:relative' ?>">
<summary class="flex size-11 cursor-pointer list-none items-center justify-center gap-2 rounded-lg text-slate-600 hover:bg-slate-100 md:w-auto md:rounded-full md:border md:border-slate-200 md:bg-slate-50 md:px-3 md:text-xs md:font-semibold md:text-slate-700 dark:text-slate-300 dark:hover:bg-slate-800 md:dark:border-slate-700 md:dark:bg-slate-950 md:dark:text-slate-200 [&::-webkit-details-marker]:hidden" title="<?= e(__('language')) ?>"><svg width="20" height="20" viewBox="0 0 16 16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.25"><circle cx="8" cy="8" r="6.25"/><path d="M1.75 8h12.5M8 1.75c1.8 1.9 2.6 4 2.6 6.25S9.8 12.35 8 14.25C6.2 12.35 5.4 10.25 5.4 8S6.2 3.65 8 1.75"/></svg><span class="sr-only"><?= e(__('language')) ?>: </span><span lang="<?= e($locale) ?>" class="sr-only md:not-sr-only md:whitespace-nowrap"><?= e($short($locale)) ?></span><svg class="hidden md:block" width="10" height="10" viewBox="0 0 10 10" aria-hidden="true"><path d="M2 3.5 5 6.5 8 3.5" fill="none" stroke="currentColor" stroke-width="1.5"/></svg></summary>
<nav aria-label="<?= e(__('language')) ?>" class="absolute end-4 top-full z-20 mt-1 max-h-[70vh] w-60 md:end-0 md:top-auto md:mt-2 overflow-y-auto rounded-xl border border-slate-200 bg-white p-2 shadow-lg dark:border-slate-700 dark:bg-slate-900">
<p class="eyebrow px-3 pt-1 pb-2"><?= e(__('language')) ?></p>
<ul>
<?php foreach ($switcher as $code => $href): ?>
<li><a href="<?= e($href) ?>" <?= $locale === $code ? 'aria-current="page"' : '' ?> class="flex min-h-11 items-center justify-between gap-3 rounded-lg px-3 text-sm hover:bg-slate-100 aria-[current=page]:font-semibold dark:hover:bg-slate-800"><span lang="<?= e($code) ?>" dir="<?= e($registry[$code]['direction']) ?>"><?= e($registry[$code]['native_name']) ?></span><span class="font-mono text-xs text-slate-600 dark:text-slate-400"><?= $locale === $code ? '<span aria-hidden="true">✓</span>' : e($code) ?></span></a></li>
<?php endforeach ?>
</ul>
</nav>
</details>
<?php endif ?>
<?php if ($settings->enabled('header_show_search')): ?>
<a href="<?= e('/' . $locale . '/search') ?>" aria-label="<?= e(__('search')) ?>" title="<?= e(__('search')) ?>" <?= $active === 'search' ? 'aria-current="page"' : '' ?> class="flex size-11 items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100 aria-[current=page]:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800 dark:aria-[current=page]:bg-slate-800"><svg width="20" height="20" viewBox="0 0 20 20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"><circle cx="8.75" cy="8.75" r="5.5"/><path d="m13 13 4 4"/></svg></a>
<?php endif ?>
<?php if ($themeSwitch): $back = (string) ($_SERVER['REQUEST_URI'] ?? '/'); ?>
<details class="relative"><summary aria-label="<?= e(__('color_scheme')) ?>" title="<?= e(__('color_scheme')) ?>" class="flex size-11 cursor-pointer list-none items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800 [&::-webkit-details-marker]:hidden"><svg width="20" height="20" viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="7.25" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M10 2.75a7.25 7.25 0 0 1 0 14.5z" fill="currentColor"/></svg></summary>
<div class="absolute end-0 z-20 mt-2 w-44 rounded-xl border border-slate-200 bg-white p-2 shadow-lg dark:border-slate-700 dark:bg-slate-900"><p class="eyebrow px-3 pt-1 pb-2"><?= e(__('color_scheme')) ?></p>
<?php foreach (['auto', 'light', 'dark'] as $option): ?><a rel="nofollow" href="<?= e('/theme?' . http_build_query(['set' => $option, 'back' => $back])) ?>" <?= $scheme === $option ? 'aria-current="true"' : '' ?> class="flex min-h-11 items-center justify-between rounded-lg px-3 text-sm hover:bg-slate-100 aria-[current=true]:font-semibold dark:hover:bg-slate-800"><?= e(__('scheme_' . $option)) ?><?php if ($scheme === $option): ?><span aria-hidden="true">✓</span><?php endif ?></a><?php endforeach ?>
</div></details>
<?php endif ?>
<details class="relative md:hidden"><summary aria-label="<?= e(__('menu')) ?>" class="flex size-11 cursor-pointer list-none items-center [&::-webkit-details-marker]:hidden justify-center rounded-lg border border-slate-300 dark:border-slate-700"><svg width="20" height="20" viewBox="0 0 20 20" aria-hidden="true"><path d="M3 6h14M3 10h14M3 14h14" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/></svg></summary><div class="absolute end-0 z-20 mt-2 w-56 rounded-xl border border-slate-200 bg-white p-2 shadow-lg dark:border-slate-700 dark:bg-slate-900"><?php $navStacked = true; require __DIR__ . '/../partials/nav.php' ?></div></details>
</div></div></header>
<main id="main" class="flex-1"><?= $content ?></main>
<?php
$brand = $settings->global('footer_brand', 'site');
if ($brand === 'author' && $author === null) { $brand = 'site'; }
$columns = ($settings->enabled('footer_show_languages') && $live) || ($settings->enabled('footer_show_feeds') && $live) || ($settings->enabled('footer_show_site') && $footerLinks);
$copyright = $settings->enabled('footer_show_copyright') ? str_replace(['{year}', '{site}'], [(new DateTimeImmutable('now', Core\Dates::zone()))->format('Y'), $settings->label('site_title')], $settings->get('copyright', $locale, '© {year} {site}')) : '';
?>
<?php if ($brand !== 'none' || $columns || $copyright !== ''): ?>
<footer class="bg-slate-900 text-slate-300">
<?php if ($brand !== 'none' || $columns): ?>
<div class="mx-auto flex max-w-[1120px] flex-wrap justify-between gap-10 px-4 py-12 md:px-6">
<?php if ($brand === 'author'): $card = $author; $cardVariant = 'footer'; ?><div class="max-w-sm"><?php require __DIR__ . '/../partials/author-card.php' ?></div>
<?php elseif ($brand === 'site'): ?>
<div class="max-w-xs"><div class="mb-3 flex items-center gap-2 text-xl font-bold text-white"><?php if ($settings->enabled('header_show_icon')) { require __DIR__ . '/../partials/mark.php'; } ?><?= e($settings->label('site_title')) ?></div><?php $tagline = $settings->get('site_tagline', $locale); if ($tagline !== ''): ?><p class="mb-3 text-sm text-slate-400"><?= e($tagline) ?></p><?php endif ?><?php $footerText = $settings->get('footer_text', $locale); if ($footerText !== ''): ?><p class="text-sm leading-relaxed text-slate-400"><?= nl2br(e($footerText), false) ?></p><?php endif ?></div>
<?php endif ?>
<?php if ($columns): ?>
<div class="flex flex-wrap gap-8 text-sm md:gap-12">
<?php if ($settings->enabled('footer_show_languages') && $live): ?><div><h2 class="eyebrow text-slate-400"><?= e(__('read_in')) ?></h2><?php foreach ($live as $code): ?><a class="flex min-h-11 items-center text-white" lang="<?= e($code) ?>" href="<?= e('/' . $code . '/') ?>"><?= e($registry[$code]['native_name']) ?></a><?php endforeach ?></div><?php endif ?>
<?php if ($settings->enabled('footer_show_feeds') && $live): ?><div><h2 class="eyebrow text-slate-400"><?= e(__('feeds')) ?></h2><?php foreach ($live as $code): ?><a class="flex min-h-11 items-center text-white" href="<?= e('/' . $code . '/rss.xml') ?>"><?= e('/' . $code . '/rss.xml') ?></a><?php endforeach ?></div><?php endif ?>
<?php if ($settings->enabled('footer_show_site') && $footerLinks): ?><div><h2 class="eyebrow text-slate-400"><?= e($settings->get('footer_menu_heading', $locale, __('site'))) ?></h2>
<?php $newTabIcon = $settings->enabled('footer_new_tab_icon'); foreach ($footerLinks as $link): ?><a class="flex min-h-11 items-center text-white" href="<?= e($link['url']) ?>"<?= $link['new_tab'] ? ' target="_blank" rel="noopener"' : ($link['external'] ? ' rel="noopener"' : '') ?><?= $link['current'] ? ' aria-current="page"' : '' ?>><?= e($link['label']) ?><?php if ($link['new_tab']) { require __DIR__ . '/../partials/new-tab.php'; } ?></a><?php endforeach ?>
</div><?php endif ?>
</div>
<?php endif ?>
</div>
<?php endif ?>
<?php if ($copyright !== ''): ?><div class="<?= $brand !== 'none' || $columns ? 'border-t border-slate-800' : '' ?>"><p class="mx-auto max-w-[1120px] px-4 py-5 text-sm text-slate-400 md:px-6"><?= e($copyright) ?></p></div><?php endif ?>
</footer>
<?php endif ?>
<?php // Back to top, without JavaScript: the link sits a screen and a bit down the page and sticks to the
// bottom corner once scrolled into view; on short pages it stays clipped away. ?>
<div class="pointer-events-none absolute inset-0 overflow-clip print:hidden"><a href="#top" class="back-to-top" title="<?= e(__('back_to_top')) ?>"><svg width="20" height="20" viewBox="0 0 20 20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M10 16V4M4.5 9.5 10 4l5.5 5.5"/></svg><span class="sr-only"><?= e(__('back_to_top')) ?></span></a></div>
</body></html>
