<div class="space-y-6">
<header class="max-w-3xl">
<h1 class="text-[32px] font-bold tracking-tight">Languages</h1>
<p class="text-slate-600 dark:text-slate-400">Choose what’s public, what’s in progress, and the order readers see.</p>
</header>
<form method="post" class="panel space-y-4">
<?= \Core\Csrf::field() ?>
<input type="hidden" name="action" value="default">
<h2 class="text-xl font-bold">Default &amp; routing</h2>
<div class="flex flex-wrap items-end gap-5">
<label class="field">Default language<select name="code">
<?php foreach ($i18n->registry() as $code => $language): if ($language['status'] !== 'live') { continue; } ?>
<option value="<?= e($code) ?>" <?= $language['is_default'] ? 'selected' : '' ?>>
<?= e($language['name']) ?>
</option>
<?php endforeach ?>
</select>
</label>
<label class="flex min-h-11 items-center gap-2">
<input type="checkbox" name="match_browser_language" <?= ($db->one('SELECT value FROM settings WHERE `key` = ? AND locale_scope = ?', ['match_browser_language', ''])['value'] ?? '0') === '1' ? 'checked' : '' ?>>Match browser language on first visit</label>
<button class="btn">Save routing</button>
</div>
</form>
<section class="overflow-x-auto rounded-[14px] border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
<h2 class="px-6 py-5 text-xl font-bold">Your languages</h2>
<table class="w-full text-left text-sm">
<thead class="bg-slate-50 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
<tr>
<?php foreach (['Order', 'Language', 'Status', 'Posts translated', 'Interface text', 'Actions'] as $heading): ?>
<th class="px-4 py-3">
<?= e($heading) ?>
</th>
<?php endforeach ?>
</tr>
</thead>
<tbody>
<?php $uiStrings = new \App\Services\UiStrings($db); $builtin = $uiStrings->builtins(); $overrides = $uiStrings->overrides(); $totalPosts = $db->one('SELECT COUNT(*) AS n FROM posts')['n']; foreach ($i18n->registry() as $code => $language): $translated = $db->one('SELECT COUNT(*) AS n FROM post_translations WHERE locale = ?', [$code])['n']; $ownText = count($uiStrings->own($code, $overrides)); ?>
<tr class="border-t border-slate-200 dark:border-slate-800">
<td class="px-4 py-4">
<form method="post" class="flex gap-1">
<?= \Core\Csrf::field() ?>
<input type="hidden" name="code" value="<?= e($code) ?>">
<button class="btn" name="action" value="up" aria-label="Move <?= e($language['name']) ?> up">↑</button>
<button class="btn" name="action" value="down" aria-label="Move <?= e($language['name']) ?> down">↓</button>
</form>
</td>
<td class="px-4 py-4">
<span class="font-semibold" lang="<?= e($code) ?>">
<?= e($language['native_name']) ?>
</span>
<span class="block text-xs text-slate-500">
<?= e($language['name']) ?> · <?= e($code) ?>
<?= $language['is_default'] ? ' · Default' : '' ?>
</span>
</td>
<td class="px-4 py-4">
<span class="status-chip <?= $language['status'] === 'live' ? 'bg-slate-900 text-white dark:bg-slate-700' : ($language['status'] === 'preview' ? 'bg-amber-100 text-amber-950' : 'border border-dashed border-slate-400') ?>">
<?= e(ucfirst($language['status'])) ?>
</span>
</td>
<td class="px-4 py-4 font-mono text-xs">
<?= e($translated) ?> / <?= e($totalPosts) ?>
</td>
<td class="px-4 py-4">
<span class="block font-mono text-xs"><?= e($ownText) ?> / <?= e(count($builtin)) ?></span>
<span class="flex flex-wrap gap-x-3 text-sm"><a class="inline-flex min-h-11 items-center text-orange-700 dark:text-orange-400" href="/admin/strings">Edit<span class="sr-only"> interface text</span></a>
<a class="inline-flex min-h-11 items-center text-orange-700 dark:text-orange-400" href="/admin/languages/export?code=<?= e(rawurlencode($code)) ?>" download>Download file<span class="sr-only"> for <?= e($language['name']) ?></span></a></span>
</td>
<td class="px-4 py-4">
<details>
<summary class="min-h-11 cursor-pointer py-3 text-orange-700 dark:text-orange-400">Edit</summary>
<form method="post" class="my-3 space-y-3">
<?= \Core\Csrf::field() ?>
<input type="hidden" name="code" value="<?= e($code) ?>">
<label class="field">Name in English<input name="name" value="<?= e($language['name']) ?>" required maxlength="191">
</label>
<label class="field">Native name<input name="native_name" value="<?= e($language['native_name']) ?>" required maxlength="191">
</label>
<label class="field">Direction<select name="direction">
<option value="ltr" <?= $language['direction'] === 'ltr' ? 'selected' : '' ?>>Left to right</option>
<option value="rtl" <?= $language['direction'] === 'rtl' ? 'selected' : '' ?>>Right to left</option>
</select>
</label>
<button class="btn" name="action" value="edit">Save names</button>
</form>
<form method="post" class="my-3 flex flex-wrap gap-2">
<?= \Core\Csrf::field() ?>
<input type="hidden" name="code" value="<?= e($code) ?>">
<input type="hidden" name="action" value="status">
<?php foreach (['live', 'preview', 'off'] as $status): ?>
<button class="btn" name="status" value="<?= e($status) ?>" <?= $language['is_default'] && $status !== 'live' ? 'disabled' : '' ?>>
<?= e(ucfirst($status)) ?>
</button>
<?php endforeach ?>
</form>
<?php if (!$language['is_default']): ?>
<form method="post">
<?= \Core\Csrf::field() ?>
<input type="hidden" name="code" value="<?= e($code) ?>">
<button class="btn text-red-700 dark:text-red-400" name="action" value="delete">Delete empty language</button>
</form>
<?php endif ?>
</details>
</td>
</tr>
<?php endforeach ?>
</tbody>
</table>
<dl class="grid gap-4 border-t border-slate-200 bg-slate-50 p-6 text-sm md:grid-cols-3 dark:border-slate-800 dark:bg-slate-950">
<div>
<dt class="font-semibold">Live</dt>
<dd>Public. In the switcher, sitemap, hreflang tags and its own RSS feed.</dd>
</div>
<div>
<dt class="font-semibold">Preview</dt>
<dd>Only signed-in editors can open it. Everyone else gets a 404.</dd>
</div>
<div>
<dt class="font-semibold">Off</dt>
<dd>Hidden everywhere. Translations are kept, not deleted.</dd>
</div>
</dl>
</section>
<form method="post" class="panel space-y-4">
<?= \Core\Csrf::field() ?>
<input type="hidden" name="action" value="add">
<h2 class="text-xl font-bold">Add a language</h2>
<p class="text-sm text-slate-600 dark:text-slate-400">It starts in Preview. Missing interface text falls back to the default language, then English. Languages that ship with Meteor are already in the list above, switched Off.</p>
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
<?php foreach (['code' => 'Code', 'native_name' => 'Name in that language', 'name' => 'Name in English'] as $key => $label): ?>
<label class="field">
<?= e($label) ?>
<input name="<?= e($key) ?>" value="<?= e(($old['action'] ?? '') === 'add' ? ($old[$key] ?? '') : '') ?>" required maxlength="<?= $key === 'code' ? '35' : '191' ?>">
</label>
<?php endforeach ?>
<label class="field">Text direction<select name="direction">
<option value="ltr">Left to right</option>
<option value="rtl">Right to left</option>
</select>
</label>
</div>
<div class="flex justify-end">
<button class="btn-primary">+ Add language</button>
</div>
</form>
<form method="post" enctype="multipart/form-data" class="panel space-y-4">
<?= \Core\Csrf::field() ?>
<input type="hidden" name="action" value="import">
<h2 class="text-xl font-bold">Import a language file</h2>
<p class="text-sm text-slate-600 dark:text-slate-400">A Meteor language file (.json) holds the interface text of one language — download one from the table above to share it with another site. A new language is added as Off. Text that matches the built-in file is not stored, so later updates can still improve it.</p>
<div class="flex flex-wrap items-end gap-5">
<label class="field">Language file<input type="file" name="language_file" accept=".json,application/json" required></label>
<label class="flex min-h-11 items-center gap-2"><input type="checkbox" name="update_names" value="1">Also update the names and text direction of an existing language</label>
<button class="btn">Import</button>
</div>
</form>
</div>
