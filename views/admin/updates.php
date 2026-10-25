<div class="space-y-6">
<header class="max-w-3xl">
<h1 class="text-[32px] font-bold tracking-tight">Updates</h1>
<p class="text-slate-600 dark:text-slate-400">Current version: <span class="font-mono"><?= e(\Core\Version::current()) ?></span></p>
</header>
<?php foreach (['zip', 'curl'] as $ext): if (!extension_loaded($ext)): ?><p role="alert" class="rounded-[10px] border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">Enable <?= e('ext-' . $ext) ?> to update Meteor.</p><?php endif; endforeach ?>
<div class="grid gap-6 xl:grid-cols-2 [&>*]:min-w-0">
<section class="panel space-y-4" aria-labelledby="online-heading">
<h2 id="online-heading" class="text-xl font-bold">Online update</h2>
<form method="post"><?= \Core\Csrf::field() ?><button name="action" value="check" class="btn-primary">Check for updates</button></form>
<?php if ($release): ?>
<div class="space-y-2 border-t border-slate-200 pt-4 dark:border-slate-800">
<h3 class="text-lg font-semibold"><?= e($release['name']) ?></h3>
<p class="font-mono text-sm wrap-anywhere text-slate-600 dark:text-slate-400">Latest version: <?= e($release['version']) ?> · <?= e($release['date']) ?></p>
<pre class="max-h-72 overflow-y-auto whitespace-pre-wrap wrap-anywhere font-display text-sm"><?= e($release['notes']) ?></pre>
</div>
<form method="post" class="flex flex-wrap items-center gap-x-6 gap-y-2">
<?= \Core\Csrf::field() ?><input type="hidden" name="action" value="online">
<button class="btn-primary">Download and install</button>
<label class="flex min-h-11 items-center gap-2"><input type="checkbox" name="allow_downgrade">Allow downgrade</label>
</form>
<?php endif ?>
</section>
<section class="panel space-y-4" aria-labelledby="upload-heading">
<h2 id="upload-heading" class="text-xl font-bold">Upload update zip</h2>
<p class="text-sm text-slate-600 dark:text-slate-400">Use a trusted Meteor release (up to 50 MB). Updates contain executable PHP. Back up your database before updating.</p>
<form method="post" enctype="multipart/form-data" class="space-y-4">
<?= \Core\Csrf::field() ?><input type="hidden" name="action" value="upload">
<div class="grid gap-4 sm:grid-cols-2">
<label class="field">Release zip<input required type="file" name="update" accept=".zip"></label>
<label class="field">SHA-256 (optional)<input name="sha256" class="font-mono" spellcheck="false" autocomplete="off"></label>
</div>
<div class="flex flex-wrap items-center gap-x-6 gap-y-2">
<button class="btn-primary">Install uploaded update</button>
<label class="flex min-h-11 items-center gap-2"><input type="checkbox" name="allow_downgrade">Allow downgrade</label>
</div>
</form>
</section>
</div>
<section class="space-y-3" aria-labelledby="backups-heading">
<div class="flex flex-wrap items-baseline justify-between gap-x-4">
<h2 id="backups-heading" class="text-xl font-bold">Backups <span class="font-mono text-sm font-normal text-slate-500 dark:text-slate-400">· <?= e(count($backups)) ?></span></h2>
<p class="text-sm text-slate-600 dark:text-slate-400">A copy of the code is saved before every update. The last five are kept. Restoring code does not reverse database changes.</p>
</div>
<div class="sheet">
<table>
<caption class="sr-only">Code backups</caption>
<thead><tr>
<th scope="col" class="sheet-key">Backup</th>
<th scope="col">Version</th>
<th scope="col">Created</th>
<th scope="col" class="sheet-end">Size</th>
<th scope="col" class="sheet-end">Actions</th>
</tr></thead>
<tbody>
<?php foreach ($backups as $backup): ?>
<tr>
<th scope="row" class="sheet-key max-w-none! font-mono text-xs break-all text-slate-600 dark:text-slate-300"><?= e($backup['name']) ?></th>
<td class="font-mono text-xs"><?= e($backup['version']) ?></td>
<td class="whitespace-nowrap font-mono text-xs"><?= e(site_time($backup['created'])) ?></td>
<td class="sheet-end whitespace-nowrap font-mono text-xs"><?= e(\App\Services\MediaLibrary::size($backup['size'])) ?></td>
<td class="sheet-end"><a class="btn" href="/admin/updates/restore?<?= e(http_build_query(['backup' => $backup['name']])) ?>">Restore…<span class="sr-only"> <?= e($backup['name']) ?></span></a></td>
</tr>
<?php endforeach ?>
<?php if (!$backups): ?>
<tr><td colspan="5" class="p-8 text-center text-slate-500 dark:text-slate-400">No backups yet. One is made automatically before each update.</td></tr>
<?php endif ?>
</tbody>
</table>
</div>
</section>
</div>
