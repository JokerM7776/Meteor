<?php
// One menu as a table. Expects $menu, $rows, $pages, $locales, $i18n, $default and $old (the re-shown input or []).
$oldItems = ($old['menu'] ?? '') === $menu ? ($old['items'] ?? []) : [];
$missing = [];
foreach ($rows as $row) {
    if ($row['kind'] === 'pages' || !empty($row['page_id'])) { continue; }
    foreach ($locales as $code => $language) {
        $value = $oldItems[$row['id']]['labels'][$code] ?? $row['labels'][$code] ?? '';
        $fallback = $row['kind'] === 'link' ? ($row['labels'][$default] ?? '') : $i18n->translateFor($code, $row['kind']);
        if ($value === '' && $fallback === '') { $missing[$code] = ($missing[$code] ?? 0) + 1; }
    }
}
?>
<div class="sheet">
<table>
<caption class="sr-only"><?= e(ucfirst($menu)) ?> menu</caption>
<thead><tr>
<th scope="col" class="sheet-key">Item</th>
<th scope="col" class="sheet-center">Show</th>
<?php foreach ($locales as $code => $language) { require __DIR__ . '/sheet-head.php'; } ?>
<th scope="col" class="sheet-center">Remove</th>
</tr></thead>
<tbody data-sortable>
<?php foreach ($rows as $index => $row): $id = $row['id']; $o = $oldItems[$id] ?? []; $name = 'items[' . $id . ']';
$page = !empty($row['page_id']) ? ($pages[$row['page_id']] ?? null) : null;
$pageTitle = $page ? ($page['labels'][$default] ?? $page['labels'][$page['base']] ?? $page['slug']) : '';
$rowName = $page ? $pageTitle : ($row['kind'] === 'link' ? (($row['labels'][$default] ?? '') ?: (string) $row['url']) : \App\Services\MenuEditor::NAMES[$row['kind']] ?? $row['kind']); ?>
<tr data-sortable-row data-name="<?= e($rowName) ?>">
<th scope="row" class="sheet-key">
<div class="flex items-center gap-1">
<?php $handleName = $rowName; require __DIR__ . '/drag-handle.php'; ?>
<input type="hidden" name="<?= e($name) ?>[sort_order]" value="<?= e($o['sort_order'] ?? $index + 1) ?>" data-sort-order>
<div class="min-w-0 flex-1">
<?php if ($page): ?>
<span class="block font-semibold text-slate-900 dark:text-slate-100"><?= e($pageTitle) ?></span>
<span class="mt-0.5 block font-mono text-xs text-slate-500">Page · /{locale}/<?= e($page['slug']) ?><?= !$page['visible'] || !$page['published'] ? ' · not published yet' : '' ?></span>
<a class="inline-flex min-h-11 items-center text-sm text-orange-700 underline dark:text-orange-400" href="/admin/editor?<?= e(http_build_query(['post' => $page['id'], 'locale' => $page['base'], 'kind' => 'page'])) ?>">Edit page<span class="sr-only"> <?= e($pageTitle) ?></span></a>
<?php elseif ($row['kind'] === 'link'): ?>
<label class="sr-only" for="<?= e($menu . '-url-' . $id) ?>">Link URL, row <?= e($index + 1) ?></label>
<input id="<?= e($menu . '-url-' . $id) ?>" name="<?= e($name) ?>[url]" value="<?= e($o['url'] ?? $row['url']) ?>" placeholder="https://… or /{locale}/…" required>
<?php else: ?>
<span class="block font-semibold text-slate-900 dark:text-slate-100"><?= e($rowName) ?></span>
<span class="mt-0.5 block font-mono text-xs text-slate-500"><?= $row['kind'] === 'pages' ? 'Pages with “Show in navigation”' : e(\App\Repositories\Menus::PATHS[$row['kind']] ?? '') ?></span>
<?php endif ?>
</div>
</div>
</th>
<td class="sheet-center"><input type="hidden" name="<?= e($name) ?>[visible]" value="0"><label class="flex min-h-11 items-center justify-center"><input type="checkbox" name="<?= e($name) ?>[visible]" value="1" <?= ($o['visible'] ?? (string) $row['is_visible']) === '1' ? 'checked' : '' ?>><span class="sr-only">Show row <?= e($index + 1) ?></span></label></td>
<?php if ($row['kind'] === 'pages'): ?>
<td colspan="<?= e(count($locales)) ?>" class="text-slate-500 dark:text-slate-400">Labels come from each page’s “Navigation label”. <a class="text-orange-700 underline dark:text-orange-400" href="/admin/pages">Edit pages</a></td>
<?php else: foreach ($locales as $code => $language): ?>
<td><input name="<?= e($name) ?>[labels][<?= e($code) ?>]" lang="<?= e($code) ?>" maxlength="80" value="<?= e($o['labels'][$code] ?? $row['labels'][$code] ?? '') ?>"
 placeholder="<?= e($page ? ($page['labels'][$code] ?? 'Not translated') : ($row['kind'] === 'link' ? ($code === $default ? 'Label' : ($row['labels'][$default] ?? 'Label')) : $i18n->translateFor($code, $row['kind']))) ?>" aria-label="<?= e($language['name']) ?> label, row <?= e($index + 1) ?>"></td>
<?php endforeach; endif ?>
<td class="sheet-center"><?php if ($row['kind'] === 'link'): ?><label class="flex min-h-11 items-center justify-center"><input type="checkbox" name="<?= e($name) ?>[remove]" value="1" <?= !empty($o['remove']) ? 'checked' : '' ?>><span class="sr-only">Remove row <?= e($index + 1) ?></span></label><?php endif ?></td>
</tr>
<?php endforeach ?>
<?php $o = $oldItems['new'] ?? []; ?>
<tr><th scope="rowgroup" colspan="<?= e(count($locales) + 3) ?>" class="sheet-group"><span class="sticky left-4">Add a page or a link</span></th></tr>
<tr class="sheet-new">
<th scope="row" class="sheet-key">
<div class="flex flex-col gap-2" data-link-kind>
<label class="sr-only" for="<?= e($menu . '-page-new') ?>">Link to</label>
<select id="<?= e($menu . '-page-new') ?>" name="items[new][page_id]" class="w-full text-sm">
<option value="">Custom link (enter the URL)</option>
<?php foreach ($pages as $choice): ?><option value="<?= e($choice['id']) ?>" <?= (string) ($o['page_id'] ?? '') === (string) $choice['id'] ? 'selected' : '' ?>>Page: <?= e($choice['labels'][$default] ?? $choice['labels'][$choice['base']] ?? $choice['slug']) ?></option><?php endforeach ?>
</select>
<label class="sr-only" for="<?= e($menu . '-url-new') ?>">New link URL</label>
<input id="<?= e($menu . '-url-new') ?>" name="items[new][url]" value="<?= e($o['url'] ?? '') ?>" placeholder="https://… or /{locale}/…">
</div></th>
<td></td>
<?php foreach ($locales as $code => $language): ?>
<td><input name="items[new][labels][<?= e($code) ?>]" lang="<?= e($code) ?>" maxlength="80" value="<?= e($o['labels'][$code] ?? '') ?>" placeholder="Label" data-page-placeholder="Page title" aria-label="<?= e($language['name']) ?> label of the new link"></td>
<?php endforeach ?>
<td></td>
</tr>
</tbody>
</table>
</div>
