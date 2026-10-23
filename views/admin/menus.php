<?php
$locales = $i18n->registry(); $default = $i18n->defaultLocale();
$globals = array_column($db->all('SELECT `key`, value FROM settings WHERE locale_scope = ?', ['']), 'value', 'key');
$headings = array_column($db->all('SELECT locale, value FROM settings WHERE `key` = ? AND locale IS NOT NULL', ['footer_menu_heading']), 'value', 'locale');
$toggle = function (string $key, string $label) use ($old, $globals): void { ?>
<input type="hidden" name="<?= e($key) ?>" value="0">
<label class="flex min-h-11 items-center gap-3"><input type="checkbox" name="<?= e($key) ?>" value="1" <?= ($old[$key] ?? $globals[$key] ?? '1') === '1' ? 'checked' : '' ?>><?= e($label) ?></label>
<?php };
?>
<div class="space-y-6">
<header class="max-w-3xl">
<h1 class="text-[32px] font-bold tracking-tight">Header &amp; footer</h1>
<p class="text-slate-600 dark:text-slate-400">Choose what appears in the header and footer, in which order, and what each link says in every language. An empty label uses the text shown in grey.</p>
</header>

<form method="post" class="space-y-4" aria-labelledby="header-heading">
<?= \Core\Csrf::field() ?>
<input type="hidden" name="menu" value="header">
<section class="panel space-y-4">
<h2 id="header-heading" class="text-xl font-bold">Header</h2>
<div class="flex flex-wrap gap-x-8">
<?php foreach (\App\Services\MenuEditor::TOGGLES['header'] as $key => $label) { $toggle($key, $label); } ?>
</div>
<h3 class="font-semibold">Header menu</h3>
<p class="max-w-4xl text-sm text-slate-600 dark:text-slate-400">Built-in items can be hidden and reordered. Links accept /paths, #anchors, https://, http:// and mailto:. Use {locale} for the language code, e.g. /{locale}/projects. A link without a label in a language uses the <?= e($locales[$default]['name']) ?> label; leave that one empty to show the link only in languages that have a label.</p>
<?php $menu = 'header'; $rows = $menus['header']; require __DIR__ . '/menu-sheet.php'; ?>
<button class="btn-primary">Save header</button>
</section>
</form>

<form method="post" class="space-y-4" aria-labelledby="footer-heading">
<?= \Core\Csrf::field() ?>
<input type="hidden" name="menu" value="footer">
<section class="panel space-y-4">
<h2 id="footer-heading" class="text-xl font-bold">Footer</h2>
<div class="flex flex-wrap gap-x-8">
<?php foreach (\App\Services\MenuEditor::TOGGLES['footer'] as $key => $label) { $toggle($key, $label); } ?>
</div>
<h3 class="font-semibold">Footer menu</h3>
<div class="sheet">
<table>
<caption class="sr-only">Footer menu column heading</caption>
<thead><tr><th scope="col" class="sheet-key">Text</th><?php $missing = []; foreach ($locales as $code => $language) { require __DIR__ . '/sheet-head.php'; } ?></tr></thead>
<tbody><tr>
<th scope="row" class="sheet-key"><span class="block font-semibold text-slate-900 dark:text-slate-100">Column heading</span><span class="mt-0.5 block text-xs text-slate-500">Shown above the footer menu.</span></th>
<?php foreach ($locales as $code => $language): ?>
<td><input name="footer_menu_heading[<?= e($code) ?>]" lang="<?= e($code) ?>" maxlength="80" value="<?= e(($old['menu'] ?? '') === 'footer' ? ($old['footer_menu_heading'][$code] ?? '') : ($headings[$code] ?? '')) ?>" placeholder="<?= e($i18n->translateFor($code, 'site')) ?>" aria-label="<?= e($language['name']) ?> column heading"></td>
<?php endforeach ?>
</tr></tbody>
</table>
</div>
<?php $menu = 'footer'; $rows = $menus['footer']; require __DIR__ . '/menu-sheet.php'; ?>
<button class="btn-primary">Save footer</button>
</section>
</form>
</div>
