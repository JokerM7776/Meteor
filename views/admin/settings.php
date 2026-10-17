<div class="max-w-4xl space-y-6">
<header>
<h1 class="text-[32px] font-bold tracking-tight">Settings</h1>
<p class="text-slate-600 dark:text-slate-400">Your site and interface text, in every language. Leave a value empty to use the fallback.</p>
</header>
<nav aria-label="Settings language" class="flex flex-wrap gap-2">
<?php foreach ($i18n->registry() as $code => $language): ?>
<a class="btn <?= $code === $locale ? 'bg-slate-900 text-white' : '' ?>" href="?locale=<?= e($code) ?>" lang="<?= e($code) ?>" <?= $code === $locale ? 'aria-current="page"' : '' ?>>
<?= e($language['native_name']) ?>
</a>
<?php endforeach ?>
</nav>
<?php $values = array_column($db->all('SELECT `key`, value FROM settings WHERE locale_scope = ?', [$locale]), 'value', 'key'); $strings = array_column($db->all('SELECT `key`, value FROM ui_strings WHERE locale = ?', [$locale]), 'value', 'key'); if ($old) { $values = $old + $values; $strings = $old['strings'] ?? $strings; } ?>
<form method="post" class="space-y-6">
<?= \Core\Csrf::field() ?>
<input type="hidden" name="locale" value="<?= e($locale) ?>">
<section class="panel space-y-4">
<h2 class="text-xl font-bold">Site text · <?= e($locale) ?>
</h2>
<?php foreach (\App\Services\AdminSettings::FIELDS as $key): ?>
<label class="field">
<?= e(ucfirst(str_replace('_', ' ', $key))) ?>
<textarea name="<?= e($key) ?>" rows="2">
<?= e($values[$key] ?? '') ?>
</textarea>
</label>
<?php endforeach ?>
</section>
<section class="panel space-y-5">
<h2 class="text-xl font-bold">Interface strings</h2>
<?php foreach (require dirname(__DIR__, 2) . '/lang/en.php' as $key => $english): ?>
<label class="field">
<span class="font-mono text-xs">
<?= e($key) ?>
</span>
<span class="font-normal text-slate-600 dark:text-slate-400">English: <?= e($english) ?>
</span>
<input name="strings[<?= e($key) ?>]" value="<?= e($strings[$key] ?? '') ?>">
</label>
<?php endforeach ?>
</section>
<section class="panel space-y-4">
<h2 class="text-xl font-bold">Custom CSS · all languages</h2>
<label class="field">Stylesheet<textarea name="custom_css" rows="12" class="font-mono text-sm">
<?= e($old['custom_css'] ?? $db->one('SELECT value FROM settings WHERE `key` = ? AND locale_scope = ?', ['custom_css', ''])['value'] ?? '') ?>
</textarea>
</label>
<p class="text-sm text-slate-500">Served at /custom.css.</p>
</section>
<button class="btn-primary">Save settings</button>
</form>
</div>
