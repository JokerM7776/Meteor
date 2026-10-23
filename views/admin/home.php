<?php
use App\Services\HomePage;
$locales = $i18n->registry(); $default = $i18n->defaultLocale();
$text = [];
foreach ($db->all("SELECT `key`, locale, value FROM settings WHERE locale IS NOT NULL AND `key` LIKE 'home!_%' ESCAPE '!'") as $row) { $text[$row['key']][$row['locale']] = $row['value']; }
$sections = HomePage::sections($settings);
if (isset($old['sections']) && is_array($old['sections'])) {
    $sections = []; uasort($old['sections'], fn ($a, $b) => (int) ($a['order'] ?? 0) <=> (int) ($b['order'] ?? 0));
    foreach ($old['sections'] as $key => $data) { if (isset(HomePage::SECTIONS[$key])) { $sections[$key] = ($data['show'] ?? '0') === '1'; } }
    $sections += HomePage::sections($settings);
}
$count = (int) ($old['home_latest_count'] ?? HomePage::latestCount($settings));
$decoration = ($old['home_decoration'] ?? $settings->global('home_decoration', '1')) === '1';
$value = fn (string $key, string $code) => $old['text'][$key][$code] ?? $text[$key][$code] ?? '';
$placeholder = fn (string $key, string $code) => match ($key) {
    'home_heading' => $settings->get('site_title', $code, 'Meteor'),
    'home_intro' => $settings->get('site_tagline', $code),
    default => '',
};
$buttonLabel = fn (int $n, string $code) => $n === 1 ? $i18n->translateFor($code, 'read_archive')
    : str_replace('{language}', $locales[$code]['native_name'], $i18n->translateFor($code, 'language_rss'));
?>
<div class="space-y-6">
<header class="max-w-3xl">
<h1 class="text-[32px] font-bold tracking-tight">Settings</h1>
<p class="text-slate-600 dark:text-slate-400">Choose what your home page shows and in which order. Every text is optional.</p>
</header>
<?php require __DIR__ . '/settings-tabs.php'; ?>
<form method="post" class="space-y-8">
<?= \Core\Csrf::field() ?>
<section class="space-y-3" aria-labelledby="sections-heading">
<div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1">
<h2 id="sections-heading" class="text-xl font-bold">Sections</h2>
<p class="text-sm text-slate-600 dark:text-slate-400">Drag ⋮⋮ to reorder. Untick a section to hide it.</p>
</div>
<div class="sheet">
<table>
<caption class="sr-only">Home page sections</caption>
<thead><tr><th scope="col" class="sheet-key">Section</th><th scope="col" class="sheet-center">Show</th><th scope="col">Options</th></tr></thead>
<tbody data-sortable>
<?php $position = 0; foreach ($sections as $key => $shown): [$name, $hint] = HomePage::SECTIONS[$key]; $position++; ?>
<tr data-sortable-row data-name="<?= e($name) ?>">
<th scope="row" class="sheet-key md:min-w-80 md:max-w-96">
<div class="flex items-center gap-1">
<?php $handleName = $name; require __DIR__ . '/drag-handle.php'; ?>
<input type="hidden" name="sections[<?= e($key) ?>][order]" value="<?= e($position) ?>" data-sort-order>
<div class="min-w-0"><span class="block font-semibold text-slate-900 dark:text-slate-100"><?= e($name) ?></span><span class="mt-0.5 block text-xs text-slate-500"><?= e($hint) ?></span></div>
</div>
</th>
<td class="sheet-center"><input type="hidden" name="sections[<?= e($key) ?>][show]" value="0"><label class="flex min-h-11 items-center justify-center"><input type="checkbox" name="sections[<?= e($key) ?>][show]" value="1" <?= $shown ? 'checked' : '' ?>><span class="sr-only">Show <?= e($name) ?></span></label></td>
<td>
<?php if ($key === 'intro'): ?>
<input type="hidden" name="home_decoration" value="0"><label class="flex min-h-11 items-center gap-3"><input type="checkbox" name="home_decoration" value="1" <?= $decoration ? 'checked' : '' ?>>Show the decoration behind the heading</label>
<?php elseif ($key === 'latest'): ?>
<label class="flex min-h-11 items-center gap-3">Number of posts<select name="home_latest_count" class="w-24"><?php foreach (HomePage::COUNTS as $n): ?><option value="<?= e($n) ?>" <?= $n === $count ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach ?></select></label>
<?php elseif ($key === 'author'): ?>
<a class="inline-flex min-h-11 items-center text-orange-700 underline dark:text-orange-400" href="/admin/author">Edit the author profile</a>
<?php elseif ($key === 'pinned'): ?>
<span class="text-slate-500 dark:text-slate-400">Pin posts in the post editor (Shared settings → Pinned).</span>
<?php else: ?>
<span class="text-slate-500 dark:text-slate-400">Shows categories that have posts in the reader’s language.</span>
<?php endif ?>
</td>
</tr>
<?php endforeach ?>
</tbody>
</table>
</div>
</section>

<section class="space-y-3" aria-labelledby="intro-heading">
<div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1">
<h2 id="intro-heading" class="text-xl font-bold">Intro</h2>
<p class="text-sm text-slate-600 dark:text-slate-400">Leave a cell empty to use the text shown in grey, or nothing.</p>
</div>
<div class="sheet">
<table>
<caption class="sr-only">Home page intro by language</caption>
<thead><tr><th scope="col" class="sheet-key">Text</th><?php $missing = []; foreach ($locales as $code => $language) { require __DIR__ . '/sheet-head.php'; } ?></tr></thead>
<tbody>
<?php foreach (HomePage::TEXT as $key => [$label, $hint, $max, $multiline]): ?>
<tr>
<th scope="row" class="sheet-key"><span class="font-semibold"><?= e($label) ?></span><span class="block text-xs text-slate-500"><?= e($hint) ?></span></th>
<?php foreach ($locales as $code => $language): $name = 'text[' . $key . '][' . $code . ']'; $aria = $label . ', ' . $language['name']; ?>
<td><?php if ($multiline): ?><textarea name="<?= e($name) ?>" lang="<?= e($code) ?>" rows="3" maxlength="<?= e($max) ?>" placeholder="<?= e($placeholder($key, $code)) ?>" aria-label="<?= e($aria) ?>"><?= e($value($key, $code)) ?></textarea>
<?php else: ?><input name="<?= e($name) ?>" lang="<?= e($code) ?>" maxlength="<?= e($max) ?>" value="<?= e($value($key, $code)) ?>" placeholder="<?= e($placeholder($key, $code)) ?>" aria-label="<?= e($aria) ?>"><?php endif ?></td>
<?php endforeach ?>
</tr>
<?php endforeach ?>
</tbody>
</table>
</div>
</section>

<section class="space-y-3" aria-labelledby="buttons-heading">
<div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1">
<h2 id="buttons-heading" class="text-xl font-bold">Buttons</h2>
<p class="text-sm text-slate-600 dark:text-slate-400">Up to two buttons below the intro. Untick both to show none. Use {locale} for the language code.</p>
</div>
<div class="sheet">
<table>
<caption class="sr-only">Home page buttons</caption>
<thead><tr><th scope="col" class="sheet-key">Button · link</th><th scope="col" class="sheet-center">Show</th><?php foreach ($locales as $code => $language) { require __DIR__ . '/sheet-head.php'; } ?></tr></thead>
<tbody>
<?php foreach (HomePage::BUTTONS as $n => [$url]): $o = $old['buttons'][$n] ?? null; ?>
<tr>
<th scope="row" class="sheet-key">
<span class="mb-1 block font-semibold"><?= $n === 1 ? 'Main button' : 'Second button' ?></span>
<label class="sr-only" for="button-url-<?= e($n) ?>">Link of button <?= e($n) ?></label>
<input id="button-url-<?= e($n) ?>" name="buttons[<?= e($n) ?>][url]" value="<?= e($o['url'] ?? $settings->global('home_button_' . $n . '_url', $url)) ?>" placeholder="<?= e($url) ?>">
</th>
<td class="sheet-center"><input type="hidden" name="buttons[<?= e($n) ?>][show]" value="0"><label class="flex min-h-11 items-center justify-center"><input type="checkbox" name="buttons[<?= e($n) ?>][show]" value="1" <?= ($o['show'] ?? $settings->global('home_button_' . $n . '_show', '1')) === '1' ? 'checked' : '' ?>><span class="sr-only">Show button <?= e($n) ?></span></label></td>
<?php foreach ($locales as $code => $language): $key = 'home_button_' . $n . '_label'; ?>
<td><input name="buttons[<?= e($n) ?>][labels][<?= e($code) ?>]" lang="<?= e($code) ?>" maxlength="80" value="<?= e($o['labels'][$code] ?? $text[$key][$code] ?? '') ?>" placeholder="<?= e($buttonLabel($n, $code)) ?>" aria-label="<?= e($language['name']) ?> label of button <?= e($n) ?>"></td>
<?php endforeach ?>
</tr>
<?php endforeach ?>
</tbody>
</table>
</div>
</section>
<button class="btn-primary">Save home page</button>
</form>
</div>
