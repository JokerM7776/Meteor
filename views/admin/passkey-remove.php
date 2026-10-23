<section class="panel max-w-xl space-y-5">
<h1 class="text-3xl font-bold">Remove “<?= e($key['name']) ?>”?</h1>
<p class="text-sm text-slate-600 dark:text-slate-400">Added <?= e(substr($key['created_at'], 0, 16)) ?> UTC · <?= e($key['last_used_at'] ? 'last used ' . substr($key['last_used_at'], 0, 16) . ' UTC' : 'not used yet') ?>.
<?= $last ? 'This is your last passkey. Password sign-in remains available after removal.' : 'Your other passkeys keep working.' ?></p>
<form method="post" action="/admin/security" class="space-y-4">
<?= \Core\Csrf::field() ?>
<input type="hidden" name="action" value="remove"><input type="hidden" name="id" value="<?= e($key['id']) ?>">
<input type="hidden" name="username" value="<?= e($user['username']) ?>" autocomplete="username">
<label class="field">Current password<input required type="password" name="current_password" autocomplete="current-password"></label>
<div class="flex flex-wrap gap-4">
<button class="btn bg-red-700 text-white" type="submit">Remove passkey</button>
<a href="/admin/security" class="btn">Cancel</a>
</div>
</form>
</section>
