<?php
$locales = $i18n->registry(); $default = $i18n->defaultLocale();
$text = [];
foreach ($db->all('SELECT `key`, locale, value FROM settings WHERE locale IS NOT NULL') as $row) { $text[$row['key']][$row['locale']] = $row['value']; }
if (isset($old['text']) && is_array($old['text'])) { foreach ($old['text'] as $key => $values) { foreach ((array) $values as $code => $value) { $text[$key][$code] = $value; } } }
// What visitors see when a cell is empty.
$placeholder = function (string $key, string $code) use ($i18n, $text, $default, $locales): string {
    return match ($key) {
        'site_title' => $text['site_title'][$default] ?? 'Meteor',
        'copyright' => '© {year} {site}',
        'language_label' => mb_strlen($locales[$code]['native_name']) <= 4 ? $locales[$code]['native_name'] : strtoupper($code),
        default => '',
    };
};
$missing = [];
foreach (\App\Services\AdminSettings::TEXT as $key => $meta) {
    if (in_array($key, ['site_tagline', 'footer_text'], true)) { continue; } // optional
    foreach ($locales as $code => $language) { if (($text[$key][$code] ?? '') === '' && $placeholder($key, $code) === '') { $missing[$code] = ($missing[$code] ?? 0) + 1; } }
}
?>
<div class="space-y-6">
<header class="max-w-3xl">
<h1 class="text-[32px] font-bold tracking-tight">Settings</h1>
<p class="text-slate-600 dark:text-slate-400">Your site text in every language, side by side. Leave a cell empty to use the text shown in grey.</p>
</header>
<?php require __DIR__ . '/settings-tabs.php'; ?>
<form method="post" class="space-y-6">
<?= \Core\Csrf::field() ?>
<section class="panel space-y-4">
<h2 class="text-xl font-bold">Site icon · all languages</h2>
<?php $fieldName = 'site_icon'; $fieldValue = (string) ($old['site_icon'] ?? $siteIcon); $fieldLabel = 'Site icon'; $emptyText = 'Default';
$fieldHint = 'Square, at least 180 × 180 px. PNG, JPG or WebP work best. Remove it to use the default icon.';
$pickerTitle = 'Choose site icon'; $pickerAction = 'Use as site icon'; require __DIR__ . '/media-field.php'; ?>
<p class="text-sm text-slate-500 dark:text-slate-400">Header and footer items are on <a class="text-orange-700 underline dark:text-orange-400" href="/admin/menus">Header &amp; footer</a>.</p>
</section>
<section class="panel space-y-3" aria-labelledby="writing-heading">
<h2 id="writing-heading" class="text-xl font-bold">Writing</h2>
<?php $extendedDefault = (string) ($old['markdown_extended_default'] ?? ($db->one('SELECT value FROM settings WHERE `key` = ? AND locale_scope = ?', ['markdown_extended_default', ''])['value'] ?? '0')); ?>
<input type="hidden" name="markdown_extended_default" value="0">
<label class="flex min-h-11 items-center gap-3"><input type="checkbox" name="markdown_extended_default" value="1" aria-describedby="extended-default-hint" <?= $extendedDefault === '1' ? 'checked' : '' ?>>Enable Extended Markdown</label>
<p id="extended-default-hint" class="max-w-3xl text-sm text-slate-500 dark:text-slate-400">New posts and pages start with “Extended Markdown” ticked (tables, footnotes, task lists and more). Each post or page can still turn it off under Shared settings; ones you already saved keep their own choice.</p>
</section>
<?php $dateFormat = (string) ($old['date_format'] ?? \Core\Dates::format()); $clock = (string) ($old['time_format'] ?? (\Core\Dates::hour12() ? '12' : '24'));
$zone = (string) ($old['timezone'] ?? \Core\Dates::zone()->getName()); $now = gmdate('Y-m-d H:i:s');
$zones = []; foreach (\DateTimeZone::listIdentifiers() as $id) { $zones[str_contains($id, '/') ? strstr($id, '/', true) : 'Other'][] = $id; } ?>
<section class="panel space-y-4" aria-labelledby="dates-heading">
<h2 id="dates-heading" class="text-xl font-bold">Date &amp; time · all languages</h2>
<div class="grid gap-4 md:grid-cols-3">
<label class="field">Date format<select name="date_format" aria-describedby="date-format-hint">
<?php foreach (\Core\Dates::FORMATS as $pattern => $label): ?><option value="<?= e($pattern) ?>" <?= $pattern === $dateFormat ? 'selected' : '' ?>><?= e($label) ?> — <?= e(\Core\Dates::date($now, 'en', $pattern)) ?></option><?php endforeach ?>
</select><span id="date-format-hint" class="text-sm font-normal text-slate-500 dark:text-slate-400">Month names follow each language. “Each language’s own style” shows, for example, 2026年10月25日 in Chinese.</span></label>
<fieldset class="field"><legend class="mb-2">Time</legend>
<?php foreach (['24' => '24-hour', '12' => '12-hour'] as $value => $label): $value = (string) $value; // array keys arrive as integers ?><label class="flex min-h-11 items-center gap-3 font-normal"><input type="radio" name="time_format" value="<?= $value ?>" <?= $clock === $value ? 'checked' : '' ?>><?= $label ?> <span class="font-mono text-sm text-slate-500 dark:text-slate-400"><?= e(\Core\Dates::date('2026-01-01 14:30:00', 'en', $value === '12' ? 'h:mm a' : 'HH:mm')) ?></span></label><?php endforeach ?>
</fieldset>
<label class="field">Time zone<select name="timezone" aria-describedby="timezone-hint">
<option value="UTC" <?= $zone === 'UTC' ? 'selected' : '' ?>>UTC</option>
<?php foreach ($zones as $region => $ids): ?><optgroup label="<?= e($region) ?>"><?php foreach ($ids as $id): if ($id === 'UTC') { continue; } ?><option value="<?= e($id) ?>" <?= $id === $zone ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $id)) ?> (<?= e(\Core\Dates::offset($id)) ?>)</option><?php endforeach ?></optgroup><?php endforeach ?>
</select><span id="timezone-hint" class="text-sm font-normal text-slate-500 dark:text-slate-400">Dates on the site and publish times in the editor use this zone. Now: <?= e(site_time($now)) ?>.</span></label>
</div>
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
<th scope="row" class="sheet-key"><span class="font-semibold"><?= e($label) ?></span><span class="block text-xs text-slate-500 dark:text-slate-400"><?= e($hint) ?></span></th>
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
<section class="panel space-y-4">
<h2 class="text-xl font-bold">Custom CSS · all languages</h2>
<label class="field">Stylesheet<textarea name="custom_css" rows="12" class="font-mono text-sm"><?= e($old['custom_css'] ?? $db->one('SELECT value FROM settings WHERE `key` = ? AND locale_scope = ?', ['custom_css', ''])['value'] ?? '') ?></textarea>
</label>
<p class="text-sm text-slate-500 dark:text-slate-400">Served at /custom.css.</p>
</section>
<button class="btn-primary">Save settings</button>
</form>
</div>
<?php require __DIR__ . '/media-picker.php'; ?>
