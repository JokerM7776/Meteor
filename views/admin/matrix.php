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
<?php // Query string for list links; values equal to the defaults are left out.
$query = function (array $change) use ($filter, $q, $sort): string {
    $defaults = ['filter' => 'all', 'q' => '', 'sort' => 'updated', 'page' => 1];
    $values = $change + ['filter' => $filter, 'q' => $q, 'sort' => $sort];
    return '?' . http_build_query(array_filter($values, fn ($value, $key) => $value !== $defaults[$key], ARRAY_FILTER_USE_BOTH));
}; ?>
<div class="mb-5 flex flex-wrap items-center justify-between gap-4">
<nav aria-label="Filter" class="flex flex-wrap gap-2">
<?php foreach (['all' => 'All', 'missing' => 'Missing translations', 'drafts' => 'Has drafts'] as $key => $label): ?>
<a class="btn" href="<?= e($query(['filter' => $key, 'page' => 1])) ?>" <?= $filter === $key ? 'aria-current="page"' : '' ?>><?= e($label) ?> <span class="font-mono text-xs"><?= e($counts[$key]) ?></span></a>
<?php endforeach ?>
</nav>
<form method="get" class="flex flex-wrap items-center gap-2" data-autosubmit>
<?php if ($filter !== 'all'): ?><input type="hidden" name="filter" value="<?= e($filter) ?>"><?php endif ?>
<label class="sr-only" for="search">Search titles and slugs</label>
<input id="search" type="search" name="q" value="<?= e($q) ?>" placeholder="Search titles and slugs" class="w-64 max-w-full sm:w-72">
<label class="sr-only" for="sort">Sort by</label>
<select id="sort" name="sort" data-autosubmit-change>
<?php foreach ($sorts as $key => $label): ?><option value="<?= e($key) ?>" <?= $sort === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach ?>
</select>
<button class="btn">Apply</button>
</form>
</div>
<?php $compact = count($locales) > 6; // Many languages: icon cells under language codes, so the table fits the screen. ?>
<?php if ($compact): ?>
<p class="mb-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-slate-600 dark:text-slate-400" id="matrix-legend">
<span class="flex items-center gap-2"><span class="status-chip min-w-11 justify-center bg-slate-900 px-0 text-white dark:bg-slate-700" aria-hidden="true">✓</span>Published</span>
<span class="flex items-center gap-2"><span class="status-chip min-w-11 justify-center bg-amber-100 px-0 text-amber-950" aria-hidden="true">◐</span>Draft</span>
<span class="flex items-center gap-2"><span class="status-chip min-w-11 justify-center border border-dashed border-slate-500 px-0 text-slate-700 dark:text-slate-300" aria-hidden="true">+</span>Not translated · add it</span>
<span class="flex items-center gap-2"><span class="status-chip min-w-11 justify-center border border-dashed border-slate-500 px-0 ring-2 ring-orange-600 ring-offset-2 ring-offset-white dark:ring-offset-slate-900" aria-hidden="true"></span>Base language</span>
</p>
<?php endif ?>
<div class="sheet">
<table<?= $compact ? ' aria-describedby="matrix-legend"' : '' ?>>
<caption class="sr-only"><?= e($title) ?></caption>
<thead>
<tr>
<th scope="col" class="sheet-key"><?= e($title) ?></th>
<?php foreach ($locales as $code => $language): ?>
<?php if (!$compact) { require __DIR__ . '/sheet-head.php'; continue; } ?>
<th scope="col" class="sheet-lang-compact" title="<?= e($language['name'] . ' · ' . $language['status']) ?>">
<span class="block font-mono text-xs text-slate-900 dark:text-slate-100"><?= e($code) ?></span>
<span class="mt-1 flex justify-center"><span class="status-dot <?= $language['status'] === 'live' ? 'bg-slate-900 dark:bg-slate-100' : ($language['status'] === 'preview' ? 'bg-amber-400' : 'border border-dashed border-slate-400') ?>" aria-hidden="true"></span></span>
<span class="sr-only"><?= e($language['name']) ?>, <?= e($language['status']) ?></span>
</th>
<?php endforeach ?>
<th scope="col" class="sheet-end sheet-pin-end">Updated</th>
</tr>
</thead>
<tbody>
<?php foreach ($rows as $row): ?>
<tr>
<th scope="row" class="sheet-key py-4 md:min-w-64">
<a class="inline-flex min-h-11 items-center text-base font-semibold text-slate-900 hover:text-orange-700 dark:text-slate-100 dark:hover:text-orange-400" href="/admin/editor?<?= e(http_build_query(['post' => $row['id'], 'locale' => $row['base_locale'], 'kind' => $pageContent ? 'page' : 'post'])) ?>">
<?= e($row['translations'][$row['base_locale']]['title'] ?? $row['slug']) ?>
</a>
<span class="mt-1 block font-mono text-xs text-slate-500 dark:text-slate-400">/<?= e($row['slug']) ?> <?= !empty($row['is_pinned']) ? '· Pinned' : '' ?> <?= !$row['is_published'] ? '· Hidden' : '' ?></span>
</th>
<?php foreach ($locales as $code => $language): $status = $row['translations'][$code]['status'] ?? 'missing'; ?>
<?php $href = '/admin/editor?' . http_build_query(['post' => $row['id'], 'locale' => $code, 'kind' => $pageContent ? 'page' : 'post', 'from' => $row['base_locale']]);
$chip = $status === 'published' ? 'bg-slate-900 text-white dark:bg-slate-700' : ($status === 'draft' ? 'bg-amber-100 text-amber-950' : 'border border-dashed border-slate-500 text-slate-700 dark:text-slate-300');
$isBase = $code === $row['base_locale']; ?>
<?php if ($compact): ?>
<td class="sheet-cell-compact">
<a class="status-chip min-w-11 justify-center px-0 <?= $chip ?><?= $isBase ? ' ring-2 ring-orange-600 ring-offset-2 ring-offset-white dark:ring-offset-slate-900' : '' ?>" href="<?= e($href) ?>" title="<?= e(($status === 'missing' ? 'Add ' : ucfirst($status) . ' · ') . $language['name'] . ($isBase ? ' · base' : '')) ?>"><span aria-hidden="true"><?= $status === 'missing' ? '+' : ($status === 'published' ? '✓' : '◐') ?></span><span class="sr-only"><?= e(($status === 'missing' ? 'Add ' : ucfirst($status) . ': ') . $language['name'] . ($isBase ? ' (base language)' : '')) ?></span></a>
</td>
<?php else: ?>
<td>
<span class="flex items-center gap-2">
<a class="status-chip <?= $chip ?>" href="<?= e($href) ?>">
<?= e($status === 'missing' ? '+ Add' : ($status === 'published' ? '✓ Published' : '◐ Draft')) ?>
</a>
<?php if ($isBase): ?>
<span class="font-mono text-[10px] uppercase tracking-wider text-slate-500 dark:text-slate-400">base</span>
<?php endif ?>
</span>
</td>
<?php endif ?>
<?php endforeach ?>
<td class="sheet-end sheet-pin-end">
<?php
// Link to the base language when it is published there, else the first published language.
$viewLocale = null;
if ($row['is_published'] && ($pageContent || ($row['published_at'] !== null && $row['published_at'] <= gmdate('Y-m-d H:i:s')))) {
    foreach (array_unique(array_merge([$row['base_locale']], array_keys($locales))) as $code) {
        if (isset($locales[$code]) && ($row['translations'][$code]['status'] ?? '') === 'published') { $viewLocale = $code; break; }
    }
}
?>
<span class="flex items-center justify-end gap-3">
<span class="whitespace-nowrap font-mono text-xs text-slate-500 dark:text-slate-400"><?= e(site_time($row['updated_at'], false)) ?></span>
<?php if ($viewLocale !== null): ?>
<a class="btn whitespace-nowrap" href="<?= e('/' . $viewLocale . ($pageContent ? '/' : '/posts/') . rawurlencode($row['slug'])) ?>" target="_blank" rel="noopener">View ↗<span class="sr-only"> <?= e($row['translations'][$viewLocale]['title'] ?? $row['slug']) ?> (opens in a new tab)</span></a>
<?php else: ?>
<span class="inline-flex min-h-11 items-center whitespace-nowrap text-xs text-slate-500 dark:text-slate-400">Not public</span>
<?php endif ?>
</span>
</td>
</tr>
<?php endforeach ?>
<?php if (!$rows): ?>
<tr>
<td colspan="<?= e(count($locales) + 2) ?>" class="p-8 text-center text-slate-500 dark:text-slate-400">No results. Start with a new <?= $pageContent ? 'page' : 'post' ?>.</td>
</tr>
<?php endif ?>
</tbody>
</table>
</div>
<div class="mt-5 flex flex-wrap items-center justify-between gap-3 text-sm">
<span><?= e($total) ?> <?= $total === 1 ? 'result' : 'results' ?><?= $pages > 1 ? ' · page ' . e($number) . ' of ' . e($pages) : '' ?></span>
<?php if ($pages > 1): ?>
<nav aria-label="Pagination" class="flex flex-wrap gap-1">
<?php if ($number > 1): ?><a class="btn" rel="prev" href="<?= e($query(['page' => $number - 1])) ?>">← Previous</a><?php endif ?>
<?php for ($n = 1; $n <= $pages; $n++): if ($pages > 9 && $n !== 1 && $n !== $pages && abs($n - $number) > 2) { if ($n === 2 || $n === $pages - 1) { echo '<span class="inline-flex min-h-11 items-center px-1" aria-hidden="true">…</span>'; } continue; } ?>
<a class="btn" href="<?= e($query(['page' => $n])) ?>" <?= $n === $number ? 'aria-current="page"' : '' ?>><span class="sr-only">Page </span><?= e($n) ?></a>
<?php endfor ?>
<?php if ($number < $pages): ?><a class="btn" rel="next" href="<?= e($query(['page' => $number + 1])) ?>">Next →</a><?php endif ?>
</nav>
<?php endif ?>
</div>
