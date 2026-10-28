<div class="min-w-0 space-y-6">
<header><h1 class="text-[32px] font-bold tracking-tight">Import</h1><p class="text-slate-600 dark:text-slate-400">Bring posts, pages and images from your old blog.</p></header>
<?php $tabs = ['/admin/import' => 'Hexo', '/admin/import?source=typecho' => 'Typecho']; $tabCurrent = $source === 'hexo' ? '/admin/import' : '/admin/import?source=typecho'; $tabsLabel = 'Import source'; require __DIR__ . '/tabs.php'; ?>
<?php if (!extension_loaded('zip')): ?><p role="alert" class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-amber-950">Enable ext-zip to import zip files. The CLI can also read folders.</p><?php endif ?>
<?php if ($error !== ''): ?><p role="alert" class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-amber-950"><?= e($error) ?></p><?php endif ?>
<form method="post" enctype="multipart/form-data" class="panel space-y-5">
<?= \Core\Csrf::field() ?>
<?php if ($source === 'hexo'): ?>
<p>Zip your Hexo blog folder, or just <code>source/</code>.</p>
<label class="field">Hexo zip<input type="file" name="archive" accept=".zip" required></label>
<p class="text-sm text-slate-600 dark:text-slate-400">For large blogs: <code>php bin/import.php hexo &lt;folder-or-zip&gt;</code></p>
<?php else: ?>
<fieldset><legend class="font-semibold">Database type</legend><div class="flex flex-wrap gap-6">
<?php foreach (['mysql' => 'MySQL/MariaDB', 'sqlite' => 'SQLite file'] as $driver => $label): ?><label class="flex min-h-11 items-center gap-2"><input type="radio" name="driver" value="<?= e($driver) ?>" <?= ($old['driver'] ?? 'mysql') === $driver ? 'checked' : '' ?>><?= e($label) ?></label><?php endforeach ?>
</div></fieldset>
<fieldset class="space-y-3"><legend class="font-semibold">MySQL/MariaDB connection</legend><div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
<?php foreach (['host' => ['Host', '127.0.0.1'], 'port' => ['Port', '3306'], 'database' => ['Database', ''], 'user' => ['Username', '']] as $key => [$label, $default]): ?><label class="field"><?= e($label) ?><input name="<?= e($key) ?>" value="<?= e($old[$key] ?? $default) ?>"></label><?php endforeach ?>
<label class="field">Password<input type="password" name="password" autocomplete="off"></label>
</div></fieldset>
<fieldset><legend class="font-semibold">SQLite database</legend><label class="field">SQLite file<input type="file" name="sqlite" accept=".db,.sqlite,.sqlite3"></label></fieldset>
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
<label class="field">Table prefix<input name="prefix" value="<?= e($old['prefix'] ?? 'typecho_') ?>" maxlength="32"></label>
<label class="field">Uploads zip (optional)<input type="file" name="uploads" accept=".zip"></label>
<label class="field">Old site address (optional)<input type="url" name="site" value="<?= e($old['site'] ?? '') ?>" placeholder="https://old.example.com"></label>
</div>
<p class="text-sm text-slate-600 dark:text-slate-400">Only the selected database type is used. Zip Typecho’s usr/uploads folder to include images. PostgreSQL is not supported; export to MySQL or SQLite first.</p>
<?php endif ?>
<p class="text-sm text-slate-600 dark:text-slate-400">Server upload limit: <?= e(\App\Services\MediaLibrary::size($uploadLimit)) ?> per file; total request limit: <?= e(ini_get('post_max_size')) ?>.</p>
<div class="grid gap-4 sm:grid-cols-2">
<label class="field">Target language<select name="locale"><option value="auto" <?= ($old['locale'] ?? 'auto') === 'auto' ? 'selected' : '' ?>><?= e($source === 'hexo' ? 'Automatic (Hexo: from _config.yml)' : 'Default language (' . (array_column($locales, 'name', 'code')[$i18n->defaultLocale()] ?? $i18n->defaultLocale()) . ')') ?></option><?php foreach ($locales as $language): ?><option value="<?= e($language['code']) ?>" <?= ($old['locale'] ?? 'auto') === $language['code'] ? 'selected' : '' ?>><?= e($language['name'] . ' (' . $language['code'] . ') · ' . $language['status']) ?></option><?php endforeach ?></select></label>
<label class="field">If a slug is already used<select name="conflict"><option value="skip" <?= ($old['conflict'] ?? 'skip') === 'skip' ? 'selected' : '' ?>>Skip</option><option value="rename" <?= ($old['conflict'] ?? '') === 'rename' ? 'selected' : '' ?>>Import with a new slug</option></select></label>
</div>
<label class="flex min-h-11 items-center gap-2"><input type="checkbox" name="drafts" <?= isset($old['drafts']) ? 'checked' : '' ?>>Import everything as drafts</label>
<div class="flex gap-3"><button name="action" value="check" class="btn">Check</button><button name="action" value="import" class="btn-primary">Import</button></div>
</form>
<?php if ($report) { require __DIR__ . '/import-report.php'; } ?>
</div>
