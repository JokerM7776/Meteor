<div class="max-w-[760px] space-y-6">
<header>
<h1 class="text-[32px] font-bold tracking-tight">Security</h1>
<p class="text-slate-600 dark:text-slate-400">Your username, password and passkeys. Passkeys are the quickest way in; your password stays on as a fallback.</p>
</header>
<form class="panel space-y-4" method="post" action="/admin/security">
<?= \Core\Csrf::field() ?>
<input type="hidden" name="action" value="username">
<h2 class="text-xl font-bold">Username</h2>
<p class="text-sm text-slate-600 dark:text-slate-400">Used with your password. Your passkeys keep working after a change.</p>
<div class="grid gap-4 sm:grid-cols-2">
<label class="field">New username<input name="username" autocomplete="username" required maxlength="191" value="<?= e($user['username']) ?>">
</label>
<label class="field">Current password<input type="password" name="current_password" autocomplete="current-password" required>
</label>
</div>
<div class="flex justify-end">
<button class="btn">Change username</button>
</div>
</form>
<form class="panel space-y-4" method="post" action="/admin/security">
<?= \Core\Csrf::field() ?>
<input type="hidden" name="action" value="password">
<input type="hidden" name="username" value="<?= e($user['username']) ?>" autocomplete="username">
<div class="flex items-center justify-between">
<h2 class="text-xl font-bold">Password</h2>
<span class="rounded-full bg-slate-100 px-3 py-1 font-mono text-xs text-slate-700">Fallback · on</span>
</div>
<p class="text-sm text-slate-600 dark:text-slate-400">Fallback sign-in when a passkey isn’t available. Last changed <?= e(substr($user['password_changed_at'], 0, 10)) ?>.</p>
<label class="field">Current password<input type="password" name="current_password" autocomplete="current-password" required>
</label>
<div class="grid gap-4 sm:grid-cols-2">
<label class="field">New password<input type="password" name="new_password" autocomplete="new-password" minlength="12" required>
</label>
<label class="field">Confirm new password<input type="password" name="new_password_confirm" autocomplete="new-password" minlength="12" required>
</label>
</div>
<p class="text-sm text-slate-600 dark:text-slate-400">At least 12 characters. Changing it signs out every other session.</p>
<div class="flex justify-end">
<button class="btn">Update password</button>
</div>
</form>
<section class="panel space-y-4">
<h2 class="text-xl font-bold">Passkeys</h2>
<p class="text-sm text-slate-600 dark:text-slate-400">Sign in with Touch ID, Face ID, Windows Hello or a security key. Add one for each device you write on.</p>
<form id="passkey-add" method="post" action="/admin/security" class="flex flex-wrap items-end gap-3">
<?= \Core\Csrf::field() ?>
<label class="field flex-1">Passkey name<input id="passkey-name" name="name" placeholder="My laptop" maxlength="191" required>
</label>
<button class="btn-primary" type="submit">+ Add a passkey</button>
</form>
<p id="passkey-message" role="status" class="text-sm text-orange-700 dark:text-orange-400">
</p>
<?php $keys = $db->all('SELECT * FROM passkeys WHERE user_id = ? ORDER BY created_at DESC, id DESC', [$user['id']]); ?>
<?php foreach ($keys as $key): ?>
<article class="space-y-3 border-t border-slate-200 py-4 dark:border-slate-800">
<h3 class="font-semibold">
<?= e($key['name']) ?>
</h3>
<p class="text-xs text-slate-600 dark:text-slate-400">Added <?= e($key['created_at']) ?> · <?= e($key['last_used_at'] ? 'Last used ' . $key['last_used_at'] : 'Not used yet') ?>
</p>
<details>
<summary class="flex min-h-11 cursor-pointer items-center text-sm text-orange-700 dark:text-orange-400">Rename or remove</summary>
<form method="post" action="/admin/security" class="my-3 flex flex-wrap items-end gap-2">
<?= \Core\Csrf::field() ?>
<input type="hidden" name="id" value="<?= e($key['id']) ?>">
<label class="field flex-1">Name<input name="name" value="<?= e($key['name']) ?>" maxlength="191" required>
</label>
<button class="btn" name="action" value="rename">Rename</button>
</form>
<form method="post" action="/admin/security" class="flex flex-wrap items-end gap-2">
<?= \Core\Csrf::field() ?>
<input type="hidden" name="id" value="<?= e($key['id']) ?>">
<label class="field flex-1">Current password<input name="current_password" type="password" autocomplete="current-password" required>
</label>
<button class="btn text-red-700 dark:text-red-400" name="action" value="remove">Remove passkey</button>
</form>
<?php if (count($keys) === 1): ?>
<p class="mt-3 text-sm">This is your last passkey. Password sign-in remains available after removal.</p>
<?php endif ?>
</details>
</article>
<?php endforeach ?>
<?php if (!$keys): ?>
<p class="text-sm text-slate-500">No passkeys yet. Password sign-in is available.</p>
<?php endif ?>
<p class="border-t border-slate-200 pt-4 text-sm text-slate-600 dark:border-slate-800 dark:text-slate-400">Removing every passkey leaves password sign-in only. You’ll be asked for your current password before a passkey is removed.</p>
</section>
</div>
