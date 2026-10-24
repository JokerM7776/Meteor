<?php
// Announcements as a table: one row per announcement, drag to reorder, Show toggles saved together.
$languageNames = function (array $codes) use ($locales): string {
    if ($codes === []) { return 'All languages'; }
    $names = array_map(fn ($code) => isset($locales[$code]) ? '<span lang="' . e($code) . '">' . e($locales[$code]['native_name']) . '</span>' : '', $codes);
    $names = array_filter($names);
    return $names ? implode(', ', $names) : '<span class="text-amber-800 dark:text-amber-300">No language: its languages were deleted</span>';
};
?>
<div class="space-y-6">
<div class="flex flex-wrap items-center justify-between gap-4">
<header class="max-w-3xl">
<h1 class="text-[32px] font-bold tracking-tight">Announcements</h1>
<p class="text-slate-600 dark:text-slate-400">A bar above the header of every page. When a language has several announcements, readers use the arrows to see the next one.</p>
</header>
<a class="btn-primary" href="/admin/announcements/new">+ New announcement</a>
</div>
<?php if (!$rows): ?>
<section class="panel space-y-2">
<h2 class="text-xl font-bold">No announcements yet</h2>
<p class="text-slate-600 dark:text-slate-400">Add a title, a message and an optional button, then choose the languages that show it.</p>
</section>
<?php else: ?>
<form method="post" action="/admin/announcements" class="space-y-4">
<?= \Core\Csrf::field() ?>
<div class="sheet">
<table>
<caption class="sr-only">Announcements</caption>
<thead><tr>
<th scope="col" class="sheet-key">Announcement</th>
<th scope="col" class="sheet-center">Show</th>
<th scope="col">Message</th>
<th scope="col">Languages</th>
<th scope="col">Button</th>
<th scope="col" class="sheet-end"><span class="sr-only">Actions</span></th>
</tr></thead>
<tbody data-sortable>
<?php foreach ($rows as $index => $row): $id = $row['id']; $name = $row['title'] !== '' ? $row['title'] : mb_strimwidth(preg_replace('/\s+/u', ' ', $row['message']), 0, 48, '…'); ?>
<tr data-sortable-row data-name="<?= e($name) ?>">
<th scope="row" class="sheet-key">
<div class="flex items-center gap-1">
<?php $handleName = $name; require __DIR__ . '/drag-handle.php'; ?>
<input type="hidden" name="items[<?= e($id) ?>][sort_order]" value="<?= e($index + 1) ?>" data-sort-order>
<span class="min-w-0 flex-1 font-semibold text-slate-900 dark:text-slate-100"><?= $row['title'] !== '' ? e($row['title']) : '<span class="font-normal text-slate-500 dark:text-slate-400">No title</span>' ?></span>
</div>
</th>
<td class="sheet-center"><input type="hidden" name="items[<?= e($id) ?>][visible]" value="0"><label class="flex min-h-11 items-center justify-center"><input type="checkbox" name="items[<?= e($id) ?>][visible]" value="1" <?= $row['is_visible'] ? 'checked' : '' ?>><span class="sr-only">Show <?= e($name) ?></span></label></td>
<td class="min-w-64"><span class="line-clamp-2"><?= e($row['message']) ?></span></td>
<td class="min-w-36"><?= $languageNames($row['locales']) ?></td>
<td class="min-w-36"><?php if ($row['button_text'] !== ''): ?><span class="block font-semibold"><?= e($row['button_text']) ?></span><span class="block max-w-56 truncate font-mono text-xs text-slate-500 dark:text-slate-400"><?= e($row['button_url']) ?></span><?php else: ?><span class="text-slate-500 dark:text-slate-400">None</span><?php endif ?></td>
<td class="sheet-end whitespace-nowrap"><a class="btn" href="/admin/announcements/<?= e($id) ?>">Edit<span class="sr-only"> <?= e($name) ?></span></a> <a class="btn" href="/admin/announcements/<?= e($id) ?>/delete">Delete<span class="sr-only"> <?= e($name) ?></span></a></td>
</tr>
<?php endforeach ?>
</tbody>
</table>
</div>
<p class="text-sm text-slate-600 dark:text-slate-400">Drag ⋮⋮ to change the order readers see them in.</p>
<button class="btn-primary">Save order and visibility</button>
</form>
<?php endif ?>
</div>
