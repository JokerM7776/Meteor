<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
<div>
<h1 class="text-[32px] font-bold tracking-tight">
<?= e($title) ?>
</h1>
<p class="text-slate-600 dark:text-slate-400">One <?= $pageContent ? 'page' : 'post' ?>, every language. Choose a translation to keep writing.</p>
</div>
<a class="btn-primary" href="/admin/editor?kind=<?= $pageContent ? 'page' : 'post' ?>">+ New <?= $pageContent ? 'page' : 'post' ?>
</a>
</div>
<div class="mb-5 flex flex-wrap justify-between gap-4">
<nav aria-label="Filter" class="flex flex-wrap gap-2">
<?php foreach (['all' => 'All', 'missing' => 'Missing translations', 'drafts' => 'Has drafts'] as $key => $label): ?>
<a class="btn <?= $filter === $key ? 'bg-slate-900 text-white' : '' ?>" href="?<?= e(http_build_query(['filter' => $key, 'q' => $q])) ?>" <?= $filter === $key ? 'aria-current="page"' : '' ?>>
<?= e($label) ?> <span class="ml-2 font-mono text-xs">
<?= e($counts[$key]) ?>
</span>
</a>
<?php endforeach ?>
</nav>
<form method="get" class="flex gap-2">
<input type="hidden" name="filter" value="<?= e($filter) ?>">
<label class="sr-only" for="search">Search titles and slugs</label>
<input id="search" type="search" name="q" value="<?= e($q) ?>" placeholder="Search titles and slugs">
<button class="btn">Search</button>
</form>
</div>
<div class="sheet">
<table>
<caption class="sr-only"><?= e($title) ?></caption>
<thead>
<tr>
<th scope="col" class="sheet-key"><?= e($title) ?></th>
<?php foreach ($locales as $code => $language) { require __DIR__ . '/sheet-head.php'; } ?>
<th scope="col" class="sheet-end">Updated</th>
</tr>
</thead>
<tbody>
<?php foreach ($rows as $row): ?>
<tr>
<th scope="row" class="sheet-key py-4 md:min-w-64">
<a class="text-base font-semibold text-slate-900 hover:text-orange-700 dark:text-slate-100 dark:hover:text-orange-400" href="/admin/editor?<?= e(http_build_query(['post' => $row['id'], 'locale' => $row['base_locale'], 'kind' => $pageContent ? 'page' : 'post'])) ?>">
<?= e($row['translations'][$row['base_locale']]['title'] ?? $row['slug']) ?>
</a>
<span class="mt-1 block font-mono text-xs text-slate-500">/<?= e($row['slug']) ?> <?= !empty($row['is_pinned']) ? '· Pinned' : '' ?> <?= !$row['is_published'] ? '· Hidden' : '' ?></span>
</th>
<?php foreach ($locales as $code => $language): $status = $row['translations'][$code]['status'] ?? 'missing'; ?>
<td>
<span class="flex items-center gap-2">
<a class="status-chip <?= $status === 'published' ? 'bg-slate-900 text-white dark:bg-slate-700' : ($status === 'draft' ? 'bg-amber-100 text-amber-950' : 'border border-dashed border-slate-400 text-slate-600 dark:text-slate-300') ?>" href="/admin/editor?<?= e(http_build_query(['post' => $row['id'], 'locale' => $code, 'kind' => $pageContent ? 'page' : 'post', 'from' => $row['base_locale']])) ?>">
<?= e($status === 'missing' ? '+ Add' : ($status === 'published' ? '✓ Published' : '◐ Draft')) ?>
</a>
<?php if ($code === $row['base_locale']): ?>
<span class="font-mono text-[10px] uppercase tracking-wider text-slate-500">base</span>
<?php endif ?>
</span>
</td>
<?php endforeach ?>
<td class="sheet-end whitespace-nowrap font-mono text-xs text-slate-500">
<?= e(substr($row['updated_at'], 0, 10)) ?>
</td>
</tr>
<?php endforeach ?>
<?php if (!$rows): ?>
<tr>
<td colspan="<?= e(count($locales) + 2) ?>" class="p-8 text-center text-slate-500">No results. Start with a new <?= $pageContent ? 'page' : 'post' ?>.</td>
</tr>
<?php endif ?>
</tbody>
</table>
</div>
<div class="mt-5 flex items-center justify-between text-sm">
<span>
<?= e($total) ?> results · Page <?= e($number) ?>
</span>
<nav aria-label="Pagination" class="flex gap-2">
<?php if ($number > 1): ?>
<a class="btn" href="?<?= e(http_build_query(['page' => $number - 1, 'filter' => $filter, 'q' => $q])) ?>">← Previous</a>
<?php endif ?>
<?php if ($number * 20 < $total): ?>
<a class="btn" href="?<?= e(http_build_query(['page' => $number + 1, 'filter' => $filter, 'q' => $q])) ?>">Next →</a>
<?php endif ?>
</nav>
</div>
