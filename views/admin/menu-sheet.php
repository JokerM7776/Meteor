<?php
// One menu as a table. Expects $menu, $rows, $locales, $i18n, $default and $old (the re-shown input or []).
$oldItems = ($old['menu'] ?? '') === $menu ? ($old['items'] ?? []) : [];
$missing = [];
foreach ($rows as $row) {
    if ($row['kind'] === 'pages') { continue; }
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
<th scope="col">Order</th>
<th scope="col">Show</th>
<?php foreach ($locales as $code => $language) { require __DIR__ . '/sheet-head.php'; } ?>
<th scope="col">Remove</th>
</tr></thead>
<tbody>
<?php foreach ($rows as $index => $row): $id = $row['id']; $o = $oldItems[$id] ?? []; $name = 'items[' . $id . ']'; ?>
<tr>
<th scope="row" class="sheet-key">
<?php if ($row['kind'] === 'link'): ?>
<label class="sr-only" for="<?= e($menu . '-url-' . $id) ?>">Link URL, row <?= e($index + 1) ?></label>
<input id="<?= e($menu . '-url-' . $id) ?>" name="<?= e($name) ?>[url]" value="<?= e($o['url'] ?? $row['url']) ?>" placeholder="https://… or /{locale}/…" required>
<?php else: ?>
<span class="font-semibold"><?= e(\App\Services\MenuEditor::NAMES[$row['kind']]) ?></span>
<span class="block font-mono text-xs text-slate-500"><?= $row['kind'] === 'pages' ? 'Pages with “Show in navigation”' : e(\App\Repositories\Menus::PATHS[$row['kind']]) ?></span>
<?php endif ?>
</th>
<td><input type="number" name="<?= e($name) ?>[sort_order]" value="<?= e($o['sort_order'] ?? $index + 1) ?>" min="-9999" max="9999" aria-label="Order, row <?= e($index + 1) ?>"></td>
<td><input type="hidden" name="<?= e($name) ?>[visible]" value="0"><label class="flex min-h-11 items-center"><input type="checkbox" name="<?= e($name) ?>[visible]" value="1" <?= ($o['visible'] ?? (string) $row['is_visible']) === '1' ? 'checked' : '' ?>><span class="sr-only">Show row <?= e($index + 1) ?></span></label></td>
<?php if ($row['kind'] === 'pages'): ?>
<td colspan="<?= e(count($locales)) ?>" class="text-slate-500">Labels come from each page’s “Navigation label”. <a class="text-orange-700 underline dark:text-orange-400" href="/admin/pages">Edit pages</a></td>
<?php else: foreach ($locales as $code => $language): ?>
<td><input name="<?= e($name) ?>[labels][<?= e($code) ?>]" lang="<?= e($code) ?>" maxlength="80" value="<?= e($o['labels'][$code] ?? $row['labels'][$code] ?? '') ?>"
 placeholder="<?= e($row['kind'] === 'link' ? ($code === $default ? 'Label' : ($row['labels'][$default] ?? 'Label')) : $i18n->translateFor($code, $row['kind'])) ?>" aria-label="<?= e($language['name']) ?> label, row <?= e($index + 1) ?>"></td>
<?php endforeach; endif ?>
<td><?php if ($row['kind'] === 'link'): ?><label class="flex min-h-11 items-center"><input type="checkbox" name="<?= e($name) ?>[remove]" value="1" <?= !empty($o['remove']) ? 'checked' : '' ?>><span class="sr-only">Remove row <?= e($index + 1) ?></span></label><?php endif ?></td>
</tr>
<?php endforeach ?>
<?php $o = $oldItems['new'] ?? []; ?>
<tr class="bg-slate-50/60 dark:bg-slate-950/30">
<th scope="row" class="sheet-key"><span class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">Add a link</span>
<label class="sr-only" for="<?= e($menu . '-url-new') ?>">New link URL</label>
<input id="<?= e($menu . '-url-new') ?>" name="items[new][url]" value="<?= e($o['url'] ?? '') ?>" placeholder="https://… or /{locale}/…"></th>
<td><input type="number" name="items[new][sort_order]" value="<?= e($o['sort_order'] ?? '') ?>" min="-9999" max="9999" aria-label="Order of the new link"></td>
<td></td>
<?php foreach ($locales as $code => $language): ?>
<td><input name="items[new][labels][<?= e($code) ?>]" lang="<?= e($code) ?>" maxlength="80" value="<?= e($o['labels'][$code] ?? '') ?>" placeholder="Label" aria-label="<?= e($language['name']) ?> label of the new link"></td>
<?php endforeach ?>
<td></td>
</tr>
</tbody>
</table>
</div>
