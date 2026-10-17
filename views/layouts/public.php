<!doctype html>
<html lang="<?= e($locale) ?>" dir="<?= e($i18n->direction()) ?>">
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
<link rel="stylesheet" href="/assets/style.css">
<?php if (trim($settings->get('custom_css')) !== ''): ?><link rel="stylesheet" href="/custom.css"><?php endif ?>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Source+Sans+3:ital,wght@0,400;0,500;0,600;0,700;1,400&amp;family=Source+Serif+4:ital,opsz,wght@0,8..60,400;0,8..60,500;0,8..60,600;1,8..60,400&amp;family=JetBrains+Mono:wght@400;500&amp;family=Noto+Sans+SC:wght@400;500;700&amp;family=Noto+Serif+SC:wght@400;600&amp;display=swap">
</head>
<body class="min-h-screen <?= $is404 ? 'bg-slate-900 text-slate-100' : 'bg-slate-50' ?> font-display text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
<a class="sr-only focus:not-sr-only focus:inline-flex focus:min-h-11 focus:items-center" href="#main"><?= e(__('skip_content')) ?></a>
<header class="<?= $is404 ? '[&]:bg-slate-900 [&]:text-slate-100 [&]:border-slate-800' : '' ?> border-b border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
<div class="mx-auto flex max-w-[1120px] items-center justify-between gap-2 px-4 py-2 md:px-6 md:py-4">
<a href="<?= e('/' . $locale . '/') ?>" class="flex min-h-11 items-center gap-2 text-xl font-bold tracking-tight md:text-2xl"><?php require __DIR__ . '/../partials/mark.php' ?><?= e($settings->label('site_title')) ?></a>
<div class="flex items-center gap-2 md:gap-6">
<div class="hidden md:block"><?php require __DIR__ . '/../partials/nav.php' ?></div>
<nav aria-label="<?= e(__('language')) ?>" class="flex flex-wrap gap-0.5 rounded-full border border-slate-200 bg-slate-50 p-0.5 dark:border-slate-700 dark:bg-slate-950">
<?php foreach ($switcher as $code => $href): ?>
<a href="<?= e($href) ?>" lang="<?= e($code) ?>" <?= $locale === $code ? 'aria-current="page"' : '' ?> class="<?= $locale === $code ? 'hidden md:inline-flex' : 'inline-flex' ?> min-h-11 items-center rounded-full px-2 text-xs font-semibold md:px-3.5 <?= $locale === $code ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900' : 'text-slate-600 dark:text-slate-300' ?>"><?= e($settings->get('language_label', $code, mb_strlen($registry[$code]['native_name']) <= 4 ? $registry[$code]['native_name'] : strtoupper($code))) ?></a>
<?php endforeach ?>
</nav>
<details class="relative md:hidden"><summary aria-label="<?= e(__('menu')) ?>" class="flex size-11 cursor-pointer list-none items-center justify-center rounded-lg border border-slate-300 dark:border-slate-700"><svg width="20" height="20" viewBox="0 0 20 20" aria-hidden="true"><path d="M3 6h14M3 10h14M3 14h14" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/></svg></summary><div class="absolute end-0 z-20 mt-2 w-48 rounded-xl border border-slate-200 bg-white p-3 shadow-lg dark:border-slate-700 dark:bg-slate-900"><?php require __DIR__ . '/../partials/nav.php' ?></div></details>
</div></div></header>
<main id="main"><?= $content ?></main>
<footer class="bg-slate-900 text-slate-300">
<div class="mx-auto flex max-w-[1120px] flex-wrap justify-between gap-10 px-4 py-12 md:px-6">
<div class="max-w-xs"><div class="mb-3 flex items-center gap-2 text-xl font-bold text-white"><?php require __DIR__ . '/../partials/mark.php' ?><?= e($settings->label('site_title')) ?></div><p class="text-sm leading-relaxed text-slate-400"><?= e(__('footer_description')) ?></p></div>
<div class="flex flex-wrap gap-8 text-sm md:gap-12">
<div><h2 class="eyebrow text-slate-400"><?= e(__('read_in')) ?></h2><?php foreach ($live as $code): ?><a class="flex min-h-11 items-center text-white" lang="<?= e($code) ?>" href="<?= e('/' . $code . '/') ?>"><?= e($registry[$code]['native_name']) ?></a><?php endforeach ?></div>
<div><h2 class="eyebrow text-slate-400"><?= e(__('feeds')) ?></h2><?php foreach ($live as $code): ?><a class="flex min-h-11 items-center text-white" href="<?= e('/' . $code . '/rss.xml') ?>"><?= e('/' . $code . '/rss.xml') ?></a><?php endforeach ?></div>
<div><h2 class="eyebrow text-slate-400"><?= e(__('site')) ?></h2><a class="flex min-h-11 items-center text-white" href="<?= e('/' . $locale . '/about') ?>"><?= e($settings->get('nav_about', $locale, __('about'))) ?></a><a class="flex min-h-11 items-center text-white" href="/sitemap.xml"><?= e(__('sitemap')) ?></a></div>
</div></div>
<div class="border-t border-slate-800"><p class="mx-auto max-w-[1120px] px-4 py-5 text-sm text-slate-400 md:px-6"><?= e($settings->get('copyright', $locale, '© ' . gmdate('Y') . ' Meteor')) ?></p></div>
</footer>
</body></html>
