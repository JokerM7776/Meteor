<?php
$locales = $i18n->registry();
$q = trim((string) ($_GET['q'] ?? ''));
$editor = new \App\Services\TaxonomyEditor($db);
$kind = ($_GET['tab'] ?? '') === 'tags' ? 'tag' : 'category';
$heading = $kind === 'tag' ? 'Tags' : 'Categories';
$here = static fn (string $tab, string $q): string => '/admin/taxonomy' . (($query = http_build_query(array_filter(['tab' => $tab, 'q' => $q], 'strlen'))) !== '' ? '?' . $query : '');
$tab = $kind === 'tag' ? 'tags' : '';
?>
<div class="space-y-6">
<header class="flex flex-wrap items-end justify-between gap-4">
<div class="max-w-3xl">
<h1 class="text-[32px] font-bold tracking-tight">Categories &amp; tags</h1>
<p class="text-slate-600 dark:text-slate-400">One row per term, one column per language. Slugs are shared; each row saves on its own.</p>
</div>
<form method="get" class="flex gap-2" role="search">
<label class="sr-only" for="term-search">Search slugs and names</label>
<?php if ($tab !== ''): ?><input type="hidden" name="tab" value="<?= e($tab) ?>"><?php endif ?>
<input id="term-search" type="search" name="q" value="<?= e($q) ?>" placeholder="Search slugs and names" class="w-64">
<button class="btn">Search</button>
<?php if ($q !== ''): ?><a class="btn" href="<?= e($here($tab, '')) ?>">Clear</a><?php endif ?>
</form>
</header>
<?php
$all = ['category' => $editor->rows('category', $q), 'tag' => $editor->rows('tag', $q)];
$tabs = [$here('', $q) => 'Categories', $here('tags', $q) => 'Tags']; $tabCurrent = $here($tab, $q); $tabsLabel = 'Term types';
$tabCounts = [$here('', $q) => count($all['category']), $here('tags', $q) => count($all['tag'])];
require __DIR__ . '/tabs.php';
$rows = $all[$kind];
$missing = [];
foreach ($rows as $row) { foreach ($locales as $code => $language) { if (($row['names'][$code] ?? '') === '') { $missing[$code] = ($missing[$code] ?? 0) + 1; } } }
$rows[] = ['id' => 0, 'slug' => '', 'sort_order' => '', 'names' => [], 'post_count' => null];
$oldRow = ($old['kind'] ?? '') === $kind ? $old : [];
?>
<section class="space-y-3" aria-label="<?= e($heading) ?>">
<div class="sheet">
<table>
<caption class="sr-only"><?= e($heading) ?></caption>
<thead><tr>
<th scope="col" class="sheet-key">Slug</th>
<?php if ($kind === 'category'): ?><th scope="col" class="sheet-center">Order</th><?php endif ?>
<?php foreach ($locales as $code => $language) { require __DIR__ . '/sheet-head.php'; } ?>
<th scope="col" class="sheet-center">Posts</th>
<th scope="col" class="sheet-end">Actions</th>
</tr></thead>
<tbody>
<?php foreach ($rows as $row):
    $form = 'term-' . $kind . '-' . ($row['id'] ?: 'new');
    $o = (int) ($oldRow['id'] ?? -1) === (int) $row['id'] ? $oldRow : [];
    $label = $row['id'] ? $row['slug'] : 'new ' . $kind;
?>
<?php if (!$row['id']): ?><tr><th scope="rowgroup" colspan="<?= e(count($locales) + ($kind === 'category' ? 4 : 3)) ?>" class="sheet-group"><span class="sticky left-4">Add a <?= e($kind) ?></span></th></tr><?php endif ?>
<tr<?= $row['id'] ? '' : ' class="sheet-new"' ?>>
<th scope="row" class="sheet-key">
<input form="<?= e($form) ?>" name="slug" value="<?= e($o['slug'] ?? $row['slug']) ?>" maxlength="191" <?= $row['id'] ? 'required' : '' ?> placeholder="slug" aria-label="Slug of <?= e($label) ?>" class="font-mono">
</th>
<?php if ($kind === 'category'): ?><td class="sheet-center"><input form="<?= e($form) ?>" type="number" name="sort_order" value="<?= e($o['sort_order'] ?? $row['sort_order']) ?>" min="-9999" max="9999" aria-label="Order of <?= e($label) ?>"></td><?php endif ?>
<?php foreach ($locales as $code => $language): ?>
<td><input form="<?= e($form) ?>" name="names[<?= e($code) ?>]" lang="<?= e($code) ?>" maxlength="191" value="<?= e($o['names'][$code] ?? $row['names'][$code] ?? '') ?>" placeholder="<?= $row['id'] ? 'Missing' : 'Name' ?>" aria-label="<?= e($language['name']) ?> name of <?= e($label) ?>"></td>
<?php endforeach ?>
<td class="sheet-center font-mono text-slate-600 dark:text-slate-300"><span class="inline-flex min-h-11 items-center"><?= $row['post_count'] === null ? '' : e($row['post_count']) ?></span></td>
<td class="sheet-end">
<form id="<?= e($form) ?>" method="post" action="<?= e($here($tab, $q)) ?>" class="flex justify-end gap-2">
<?= \Core\Csrf::field() ?>
<input type="hidden" name="kind" value="<?= e($kind) ?>">
<input type="hidden" name="id" value="<?= e($row['id']) ?>">
<button class="btn" name="action" value="save"><?= $row['id'] ? 'Save' : 'Add' ?><span class="sr-only"> <?= e($label) ?></span></button>
<?php if ($row['id']): ?><a class="btn text-red-700 dark:text-red-400" href="/admin/taxonomy/<?= e($kind) ?>/<?= e($row['id']) ?>/delete">Delete<span class="sr-only"> <?= e($label) ?></span></a><?php endif ?>
</form>
</td>
</tr>
<?php endforeach ?>
</tbody>
</table>
</div>
</section>
</div>
