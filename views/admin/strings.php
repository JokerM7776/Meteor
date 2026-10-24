<?php
$service = new \App\Services\UiStrings($db);
$locales = $i18n->registry(); $default = $i18n->defaultLocale();
$builtins = $service->builtins(); $overrides = $service->overrides();
$own = []; foreach ($locales as $code => $language) { $own[$code] = $service->own($code, $overrides); }
$posted = is_array($old['strings'] ?? null) ? $old['strings'] : [];
$q = trim((string) ($_GET['q'] ?? '')); $onlyMissing = ($_GET['missing'] ?? '') === '1';
$needle = mb_strtolower($q);
$missing = [];
foreach ($locales as $code => $language) { $missing[$code] = count($builtins) - count($own[$code]); }
// What visitors of a language see when its cell is empty: the default language, then English.
$fallback = fn (string $key) => $own[$default][$key] ?? $builtins[$key];
$shown = 0;
?>
<div class="space-y-6">
<header class="max-w-3xl">
<h1 class="text-[32px] font-bold tracking-tight">Settings</h1>
<p class="text-slate-600 dark:text-slate-400">The words around your content — menus, buttons, headings — in every language. An empty cell shows the text in grey. Built-in text comes from each language file; your changes are kept separately and survive updates.</p>
</header>
<?php require __DIR__ . '/settings-tabs.php'; ?>
<form method="get" class="flex flex-wrap items-center gap-3" role="search">
<label class="sr-only" for="string-search">Search interface text</label>
<input id="string-search" type="search" name="q" value="<?= e($q) ?>" placeholder="Search keys and text" class="w-72">
<label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" name="missing" value="1" <?= $onlyMissing ? 'checked' : '' ?>>Only rows with missing text</label>
<button class="btn">Filter</button>
<?php if ($q !== '' || $onlyMissing): ?><a class="btn" href="/admin/strings">Show all</a><?php endif ?>
</form>
<form method="post" action="/admin/strings<?= $q !== '' || $onlyMissing ? '?' . e(http_build_query(array_filter(['q' => $q, 'missing' => $onlyMissing ? '1' : '']))) : '' ?>" class="space-y-4" data-changed-only>
<?= \Core\Csrf::field() ?>
<div class="sheet">
<table>
<caption class="sr-only">Interface text by language</caption>
<thead><tr>
<th scope="col" class="sheet-key">Text</th>
<?php foreach ($locales as $code => $language): ?>
<?php require __DIR__ . '/sheet-head.php'; ?>
<?php endforeach ?>
</tr></thead>
<?php foreach (\App\Services\UiStrings::GROUPS as $group => $keys):
    $rows = array_filter($keys, function (string $key) use ($needle, $onlyMissing, $own, $builtins, $locales): bool {
        if ($onlyMissing && !array_filter(array_keys($locales), fn ($code) => !isset($own[$code][$key]))) { return false; }
        if ($needle === '') { return true; }
        $haystack = $key . "\n" . $builtins[$key];
        foreach ($own as $texts) { $haystack .= "\n" . ($texts[$key] ?? ''); }
        return str_contains(mb_strtolower($haystack), $needle);
    });
    if (!$rows) { continue; } $shown += count($rows);
?>
<tbody>
<tr><th scope="rowgroup" colspan="<?= e(count($locales) + 1) ?>" class="sheet-group"><span class="sticky left-3"><?= e($group) ?></span></th></tr>
<?php foreach ($rows as $key): $long = mb_strlen($builtins[$key]) > 60; ?>
<tr>
<th scope="row" class="sheet-key"><span class="font-mono text-xs text-slate-500 dark:text-slate-400"><?= e($key) ?></span><span class="block font-normal"><?= e($builtins[$key]) ?></span></th>
<?php foreach ($locales as $code => $language): $value = $posted[$code][$key] ?? $own[$code][$key] ?? ''; $unsaved = isset($posted[$code][$key]) && $posted[$code][$key] !== ($own[$code][$key] ?? '') ? ' data-unsaved' : ''; $name = 'strings[' . $code . '][' . $key . ']'; $aria = $key . ', ' . $language['name']; ?>
<td><?php if ($long): ?><textarea name="<?= e($name) ?>" lang="<?= e($code) ?>" dir="<?= e($language['direction']) ?>" rows="3" maxlength="2000" placeholder="<?= e($fallback($key)) ?>" aria-label="<?= e($aria) ?>"<?= $unsaved ?>><?= e($value) ?></textarea>
<?php else: ?><input name="<?= e($name) ?>" lang="<?= e($code) ?>" dir="<?= e($language['direction']) ?>" maxlength="2000" value="<?= e($value) ?>" placeholder="<?= e($fallback($key)) ?>" aria-label="<?= e($aria) ?>"<?= $unsaved ?>><?php endif ?></td>
<?php endforeach ?>
</tr>
<?php endforeach ?>
</tbody>
<?php endforeach ?>
</table>
</div>
<?php if (!$shown): ?><p class="text-slate-600 dark:text-slate-400">No interface text matches this filter.</p><?php endif ?>
<div class="flex flex-wrap items-center gap-4">
<button class="btn-primary">Save interface text</button>
<p class="text-sm text-slate-500 dark:text-slate-400">Keep placeholders such as {language} or {minutes}. To share a language, download its file on <a class="text-orange-700 underline dark:text-orange-400" href="/admin/languages">Languages</a>.</p>
</div>
</form>
</div>
