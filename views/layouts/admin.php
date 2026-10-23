<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(\Core\Csrf::token()) ?>">
<title>
<?= e($title) ?> · <?= e($siteTitle) ?> Admin</title>
<?php require __DIR__ . '/../partials/favicon.php' ?>
<link rel="stylesheet" href="<?= e(asset('assets/style.css')) ?>">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;500;600;700&amp;family=Source+Serif+4:wght@400;600&amp;family=JetBrains+Mono:wght@400;500&amp;family=Noto+Sans+SC:wght@400;600&amp;family=Noto+Serif+SC:wght@400;600&amp;display=swap">
<script src="<?= e(asset('assets/admin.js')) ?>" defer></script>
</head>
<body class="admin min-h-screen <?= $active === 'login' || $active === 'editor' ? 'bg-slate-100' : 'bg-slate-50' ?> font-display text-slate-900 dark:bg-slate-950 dark:text-slate-100">
<?php if ($active === 'login'): ?>
<main class="mx-auto flex min-h-screen max-w-[468px] flex-col justify-center gap-6 px-6 py-12">
<div class="flex items-center justify-center gap-3 text-2xl font-bold">
<?php require __DIR__ . '/../partials/mark.php'; ?> <?= e($siteTitle) ?> <span class="rounded-md bg-slate-200 px-2 py-1 font-mono text-[11px] font-medium uppercase tracking-wider text-slate-700">Admin</span>
</div>
<?php if ($flash): ?>
<p role="status" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-950">
<?= e($flash) ?>
</p>
<?php endif ?>
<?= $content ?>
</main>
<?php elseif ($active === 'editor'): ?>
<main class="min-h-screen p-5 lg:p-6">
<?php if ($flash): ?>
<p role="status" class="mb-5 rounded-lg border border-green-200 bg-green-50 p-4 text-green-900">
<?= e($flash) ?>
</p>
<?php endif ?>
<?= $content ?>
</main>
<?php else: ?>
<div class="flex min-h-screen flex-col md:flex-row">
<aside class="flex shrink-0 flex-col gap-2 bg-slate-900 px-4 py-3 text-slate-300 md:w-60 md:gap-6 md:py-5">
<a href="/admin/posts" class="flex min-h-11 items-center gap-3 px-2 text-xl font-bold text-white">
<?php require __DIR__ . '/../partials/mark.php'; ?> <?= e($siteTitle) ?></a>
<nav aria-label="Admin" class="-mx-4 flex gap-1 overflow-x-auto px-4 text-[15px] md:mx-0 md:flex-col md:gap-0.5 md:overflow-visible md:px-0">
<?php foreach (['posts' => 'Posts', 'pages' => 'Pages', 'media' => 'Media', 'taxonomy' => 'Categories & tags', 'languages' => 'Languages', 'menus' => 'Header & footer', 'settings' => 'Settings', 'security' => 'Security', 'updates' => 'Updates'] as $key => $label): $selected = $active === $key || (in_array($active, ['strings', 'optimization'], true) && $key === 'settings') || ($active === 'media-delete' && $key === 'media') || ($active === 'updates-restore' && $key === 'updates') || ($active === 'passkey-remove' && $key === 'security') || ($active === 'taxonomy-delete' && $key === 'taxonomy') || ($active === 'matrix' && $key === (!empty($pageContent) ? 'pages' : 'posts')) || ($active === 'editor' && $key === (!empty($page) ? 'pages' : 'posts')); ?>
<a href="/admin/<?= e($key) ?>" <?= $selected ? 'aria-current="page"' : '' ?> class="flex min-h-11 shrink-0 items-center gap-3 whitespace-nowrap rounded-lg px-3 <?= $selected ? 'bg-slate-800 font-semibold text-white' : 'hover:bg-slate-800' ?>">
<?php require __DIR__ . "/../admin/nav-icon.php"; ?>
<?= e($label) ?>
</a>
<?php endforeach ?>
</nav>
<div class="flex flex-wrap items-center gap-x-2 border-t border-slate-800 pt-2 text-sm md:mt-auto md:block md:pt-4">
<p class="px-3 font-mono text-xs">Meteor <?= e(\Core\Version::current()) ?></p>
<a class="flex min-h-11 items-center px-3" href="/<?= e($i18n->defaultLocale()) ?>/">View site ↗</a>
<form method="post" action="/admin/logout">
<?= \Core\Csrf::field() ?>
<button class="min-h-11 px-3 text-left md:w-full" type="submit">Sign out</button>
</form>
</div>
</aside>
<main class="min-w-0 flex-1 p-5 lg:p-8">
<?php if ($flash): ?>
<p role="status" class="mb-6 rounded-[10px] border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900">
<?= e($flash) ?>
</p>
<?php endif ?>
<?= $content ?>
</main>
</div>
<?php endif ?>
</body>
</html>
