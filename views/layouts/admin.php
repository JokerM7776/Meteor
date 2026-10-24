<!doctype html>
<html lang="en" data-theme="auto">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(\Core\Csrf::token()) ?>">
<title>
<?= e($title) ?> · <?= e($siteTitle) ?> Admin</title>
<?php require __DIR__ . '/../partials/favicon.php' ?>
<?php require __DIR__ . '/../partials/fonts.php' ?>
<link rel="stylesheet" href="<?= e(asset('assets/style.css')) ?>">
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
<?php $navCollapsed = ($_COOKIE['meteor_admin_nav'] ?? '') === 'collapsed'; ?>
<div class="flex min-h-screen flex-col md:flex-row" data-nav="<?= $navCollapsed ? 'collapsed' : 'expanded' ?>">
<aside id="admin-sidebar" class="admin-sidebar flex shrink-0 flex-col gap-2 bg-slate-900 px-4 py-3 text-slate-300 md:gap-6 md:py-5">
<div class="nav-brand flex items-center justify-between gap-2">
<a href="/admin/posts" class="flex min-h-11 min-w-0 items-center gap-3 px-2 text-xl font-bold text-white" title="<?= e($siteTitle) ?>">
<?php require __DIR__ . '/../partials/mark.php'; ?><span class="nav-label truncate"><?= e($siteTitle) ?></span></a>
<button type="button" class="nav-toggle hidden size-11 shrink-0 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-800 hover:text-white focus-visible:outline-2 focus-visible:outline-orange-500 md:inline-flex" data-nav-toggle aria-controls="admin-sidebar" aria-expanded="<?= $navCollapsed ? 'false' : 'true' ?>" aria-label="<?= $navCollapsed ? 'Expand menu' : 'Collapse menu' ?>" title="<?= $navCollapsed ? 'Expand menu' : 'Collapse menu' ?>">
<svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2.75 3.25h12.5v11.5H2.75z M7 3.25v11.5 M12 7l-2 2 2 2"/></svg>
</button>
</div>
<nav aria-label="Admin" class="-mx-4 flex gap-1 overflow-x-auto px-4 text-[15px] md:mx-0 md:flex-col md:gap-0.5 md:overflow-visible md:px-0">
<?php foreach (['posts' => 'Posts', 'pages' => 'Pages', 'comments' => 'Comments', 'media' => 'Media', 'taxonomy' => 'Categories & tags', 'languages' => 'Languages', 'menus' => 'Header & footer', 'announcements' => 'Announcements', 'settings' => 'Settings', 'security' => 'Security', 'updates' => 'Updates'] as $key => $label): $selected = $active === $key || (in_array($active, ['strings', 'optimization', 'home', 'author', 'appearance'], true) && $key === 'settings') || ($active === 'media-delete' && $key === 'media') || ($active === 'updates-restore' && $key === 'updates') || ($active === 'passkey-remove' && $key === 'security') || ($active === 'comment-reply' && $key === 'comments') || (in_array($active, ['announcement', 'announcement-delete'], true) && $key === 'announcements') || ($active === 'taxonomy-delete' && $key === 'taxonomy') || ($active === 'matrix' && $key === (!empty($pageContent) ? 'pages' : 'posts')) || ($active === 'editor' && $key === (!empty($page) ? 'pages' : 'posts')); ?>
<a href="/admin/<?= e($key) ?>" <?= $selected ? 'aria-current="page"' : '' ?> title="<?= e($label) ?>" class="nav-item relative flex min-h-11 shrink-0 items-center gap-3 whitespace-nowrap rounded-lg px-3 <?= $selected ? 'bg-slate-800 font-semibold text-white' : 'hover:bg-slate-800' ?>">
<?php require __DIR__ . "/../admin/nav-icon.php"; ?>
<span class="nav-label"><?= e($label) ?></span><?php if ($key === 'comments' && !empty($pendingComments)): ?><span class="nav-badge ms-auto rounded-full bg-orange-700 px-2 font-mono text-xs leading-5 font-semibold text-white" title="<?= e($pendingComments) ?> waiting for approval"><?= e($pendingComments) ?><span class="sr-only"> waiting for approval</span></span><?php endif ?>
</a>
<?php endforeach ?>
</nav>
<div class="flex flex-wrap items-center gap-x-2 border-t border-slate-800 pt-2 text-sm md:mt-auto md:block md:pt-4">
<p class="nav-version px-3 font-mono text-xs">Meteor <?= e(\Core\Version::current()) ?></p>
<a class="nav-item flex min-h-11 items-center gap-3 rounded-lg px-3 hover:bg-slate-800" href="/<?= e($i18n->defaultLocale()) ?>/" title="View site">
<svg class="hidden shrink-0 md:block" width="18" height="18" viewBox="0 0 18 18" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 3.25h4.75V8 M14.75 3.25L8 10 M12.5 10.5v4.25H3.25V5.5H7.5"/></svg>
<span class="nav-label">View site ↗</span></a>
<form method="post" action="/admin/logout">
<?= \Core\Csrf::field() ?>
<button class="nav-item flex min-h-11 items-center gap-3 rounded-lg px-3 text-left hover:bg-slate-800 md:w-full" type="submit" title="Sign out">
<svg class="hidden shrink-0 md:block" width="18" height="18" viewBox="0 0 18 18" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3.25H3.25v11.5H7 M11 5.5L14.5 9 11 12.5 M14.5 9H6.5"/></svg>
<span class="nav-label">Sign out</span></button>
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
