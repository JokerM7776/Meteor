<div class="max-w-3xl space-y-6">
<header><h1 class="text-3xl font-bold">Updates</h1><p class="mt-2 text-slate-500">Current version: <span class="font-mono"><?= e(\Core\Version::current()) ?></span></p></header>
<?php foreach (['zip', 'curl'] as $ext): if (!extension_loaded($ext)): ?><p role="alert">Enable <?= e('ext-' . $ext) ?> to update Meteor.</p><?php endif; endforeach ?>
<section class="rounded-xl border border-slate-200 bg-white p-6 dark:bg-slate-900">
<h2 class="mb-2 text-xl font-semibold">Online update</h2>
<p class="text-sm text-slate-500">Official releases from <a class="text-orange-700 underline dark:text-orange-400" href="https://github.com/<?= e($repository) ?>/releases" rel="noopener"><?= e('github.com/' . $repository) ?></a>. Each release zip is checked against its SHA-256 file before it is installed.</p>
<form method="post" class="mt-4"><?= \Core\Csrf::field() ?><button name="action" value="check" class="btn-primary">Check for updates</button></form>
<?php if ($release): ?>
<div class="mt-6 space-y-2"><h3 class="text-lg font-semibold"><?= e($release['name']) ?></h3><p>Latest version: <?= e($release['version']) ?> · <?= e($release['date']) ?></p><pre class="whitespace-pre-wrap break-words font-display text-sm"><?= e($release['notes']) ?></pre></div>
<form method="post" class="mt-4 space-y-3">
<?= \Core\Csrf::field() ?><input type="hidden" name="action" value="online">
<label class="block">Current admin password<input required type="password" name="current_password" autocomplete="current-password" class="mt-1 min-h-11 w-full rounded-lg border p-2"></label>
<label class="flex min-h-11 items-center gap-2"><input type="checkbox" name="allow_downgrade">Allow downgrade</label>
<button class="btn-primary">Download and install</button>
</form>
<?php endif ?>
</section>
<section class="rounded-xl border border-slate-200 bg-white p-6 dark:bg-slate-900">
<h2 class="mb-4 text-xl font-semibold">Upload update zip</h2>
<p class="mb-4 text-sm">Use a trusted Meteor release (up to 50 MB). Updates contain executable PHP. Back up your database before updating.</p>
<form method="post" enctype="multipart/form-data" class="space-y-4">
<?= \Core\Csrf::field() ?><input type="hidden" name="action" value="upload">
<label class="block">Release zip<input required type="file" name="update" accept=".zip" class="mt-1 min-h-11 w-full"></label>
<label class="block">SHA-256 (optional)<input name="sha256" class="mt-1 min-h-11 w-full rounded-lg border p-2"></label>
<label class="block">Current admin password<input required type="password" name="current_password" autocomplete="current-password" class="mt-1 min-h-11 w-full rounded-lg border p-2"></label>
<label class="flex min-h-11 items-center gap-2"><input type="checkbox" name="allow_downgrade">Allow downgrade</label>
<button class="btn-primary">Install uploaded update</button>
</form>
</section>
<section class="rounded-xl border border-slate-200 bg-white p-6 dark:bg-slate-900">
<h2 class="mb-4 text-xl font-semibold">Backups</h2><p class="mb-4 text-sm">The last five code backups are kept. Restoring code does not reverse database migrations.</p>
<?php if (!$backups): ?><p>No backups yet.</p><?php endif ?>
<?php foreach ($backups as $backup): ?>
<form method="post" class="mb-4 space-y-3 border-b border-slate-200 py-4">
<?= \Core\Csrf::field() ?><input type="hidden" name="action" value="restore"><input type="hidden" name="backup" value="<?= e($backup) ?>">
<p class="break-all font-mono text-sm"><?= e($backup) ?></p>
<label class="block">Current admin password<input required type="password" name="current_password" autocomplete="current-password" class="mt-1 min-h-11 w-full rounded-lg border p-2"></label>
<button class="btn">Restore</button></form>
<?php endforeach ?>
</section></div>
