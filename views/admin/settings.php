<?php
$locales = $i18n->registry(); $default = $i18n->defaultLocale();
$text = [];
foreach ($db->all('SELECT `key`, locale, value FROM settings WHERE locale IS NOT NULL') as $row) { $text[$row['key']][$row['locale']] = $row['value']; }
if (isset($old['text']) && is_array($old['text'])) { foreach ($old['text'] as $key => $values) { foreach ((array) $values as $code => $value) { $text[$key][$code] = $value; } } }
// What visitors see when a cell is empty.
$placeholder = function (string $key, string $code) use ($i18n, $text, $default, $locales): string {
    return match ($key) {
        'site_title' => $text['site_title'][$default] ?? 'Meteor',
        'home_intro' => $i18n->translateFor($code, 'home_intro'),
        'footer_text' => $i18n->translateFor($code, 'footer_description'),
        'copyright' => '© {year} Meteor',
        'language_label' => mb_strlen($locales[$code]['native_name']) <= 4 ? $locales[$code]['native_name'] : strtoupper($code),
        default => '',
    };
};
$missing = [];
foreach (\App\Services\AdminSettings::TEXT as $key => $meta) {
    if ($key === 'site_tagline') { continue; } // optional
    foreach ($locales as $code => $language) { if (($text[$key][$code] ?? '') === '' && $placeholder($key, $code) === '') { $missing[$code] = ($missing[$code] ?? 0) + 1; } }
}
?>
<div class="space-y-6">
<header class="max-w-3xl">
<h1 class="text-[32px] font-bold tracking-tight">Settings</h1>
<p class="text-slate-600 dark:text-slate-400">Your site text in every language, side by side. Leave a cell empty to use the text shown in grey.</p>
</header>
<?php require __DIR__ . '/settings-tabs.php'; ?>
<form method="post" enctype="multipart/form-data" class="space-y-6">
<?= \Core\Csrf::field() ?>
<section class="panel max-w-3xl space-y-4">
<h2 class="text-xl font-bold">Site icon · all languages</h2>
<div class="flex flex-wrap items-start gap-5">
<img src="<?= e($siteIcon ?: '/assets/favicon.svg') ?>" alt="Current site icon" width="48" height="48" class="size-12 rounded-md object-contain">
<label class="field min-w-64 flex-1">Site icon<input type="file" name="site_icon" accept="image/png,image/jpeg,image/webp,image/vnd.microsoft.icon,image/x-icon,.ico">
<span class="text-sm font-normal text-slate-500">Square, at least 180 × 180 px. PNG, JPG, WebP or ICO up to 1 MB.</span></label>
</div>
<input type="hidden" name="remove_site_icon" value="0">
<label class="flex min-h-11 items-center gap-3"><input type="checkbox" name="remove_site_icon" value="1" <?= !empty($old['remove_site_icon']) ? 'checked' : '' ?>>Use the default Meteor icon</label>
<p class="text-sm text-slate-500">Header and footer items are on <a class="text-orange-700 underline dark:text-orange-400" href="/admin/menus">Header &amp; footer</a>.</p>
</section>
<section class="space-y-3">
<h2 class="text-xl font-bold">Site text</h2>
<div class="sheet">
<table>
<caption class="sr-only">Site text by language</caption>
<thead><tr><th scope="col" class="sheet-key">Text</th><?php foreach ($locales as $code => $language) { require __DIR__ . '/sheet-head.php'; } ?></tr></thead>
<tbody>
<?php foreach (\App\Services\AdminSettings::TEXT as $key => [$label, $hint, $max, $multiline]): ?>
<tr>
<th scope="row" class="sheet-key"><span class="font-semibold"><?= e($label) ?></span><span class="block text-xs text-slate-500"><?= e($hint) ?></span></th>
<?php foreach ($locales as $code => $language): $name = 'text[' . $key . '][' . $code . ']'; $aria = $label . ', ' . $language['name']; ?>
<td><?php if ($multiline): ?><textarea name="<?= e($name) ?>" lang="<?= e($code) ?>" rows="3" maxlength="<?= e($max) ?>" placeholder="<?= e($placeholder($key, $code)) ?>" aria-label="<?= e($aria) ?>"><?= e($text[$key][$code] ?? '') ?></textarea>
<?php else: ?><input name="<?= e($name) ?>" lang="<?= e($code) ?>" maxlength="<?= e($max) ?>" value="<?= e($text[$key][$code] ?? '') ?>" placeholder="<?= e($placeholder($key, $code)) ?>" aria-label="<?= e($aria) ?>"><?php endif ?></td>
<?php endforeach ?>
</tr>
<?php endforeach ?>
</tbody>
</table>
</div>
</section>
<section class="panel max-w-4xl space-y-4">
<h2 class="text-xl font-bold">Custom CSS · all languages</h2>
<label class="field">Stylesheet<textarea name="custom_css" rows="12" class="font-mono text-sm"><?= e($old['custom_css'] ?? $db->one('SELECT value FROM settings WHERE `key` = ? AND locale_scope = ?', ['custom_css', ''])['value'] ?? '') ?></textarea>
</label>
<p class="text-sm text-slate-500">Served at /custom.css.</p>
</section>
<button class="btn-primary">Save settings</button>
</form>
</div>
