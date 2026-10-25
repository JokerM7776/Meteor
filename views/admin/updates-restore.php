<section class="panel max-w-xl space-y-5">
<h1 class="text-3xl font-bold">Restore this backup?</h1>
<dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
<dt class="text-slate-500 dark:text-slate-400">Backup</dt><dd class="font-mono text-xs break-all"><?= e($backup['name']) ?></dd>
<dt class="text-slate-500 dark:text-slate-400">Version</dt><dd class="font-mono text-xs"><?= e($backup['version']) ?></dd>
<dt class="text-slate-500 dark:text-slate-400">Created</dt><dd class="font-mono text-xs"><?= e(site_time($backup['created'])) ?></dd>
</dl>
<p class="text-sm text-slate-600 dark:text-slate-400">The code goes back to this copy. Your posts, uploads and settings stay as they are, and database changes made by later updates are not reversed.</p>
<form method="post" action="/admin/updates" class="space-y-4">
<?= \Core\Csrf::field() ?>
<input type="hidden" name="action" value="restore"><input type="hidden" name="backup" value="<?= e($backup['name']) ?>">
<input type="hidden" name="username" value="<?= e($user['username']) ?>" autocomplete="username">
<label class="field">Current password<input required type="password" name="current_password" autocomplete="current-password"></label>
<div class="flex flex-wrap gap-4">
<button class="btn-primary">Restore backup</button>
<a href="/admin/updates" class="btn">Cancel</a>
</div>
</form>
</section>
