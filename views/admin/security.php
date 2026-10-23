<div class="space-y-6">
<header class="max-w-3xl">
<h1 class="text-[32px] font-bold tracking-tight">Security</h1>
<p class="text-slate-600 dark:text-slate-400">Your username, password and passkeys. Passkeys are the quickest way in; your password stays on as a fallback.</p>
</header>
<div class="grid gap-6 xl:grid-cols-2">
<form class="panel flex flex-col gap-4" method="post" action="/admin/security">
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
<div class="mt-auto flex justify-end">
<button class="btn">Change username</button>
</div>
</form>
<form class="panel flex flex-col gap-4" method="post" action="/admin/security">
<?= \Core\Csrf::field() ?>
<input type="hidden" name="action" value="password">
<input type="hidden" name="username" value="<?= e($user['username']) ?>" autocomplete="username">
<div class="flex items-center justify-between">
<h2 class="text-xl font-bold">Password</h2>
<span class="rounded-full bg-slate-100 px-3 py-1 font-mono text-xs text-slate-700 dark:bg-slate-800 dark:text-slate-300">Fallback · on</span>
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
<div class="mt-auto flex justify-end">
<button class="btn">Update password</button>
</div>
</form>
</div>
<section class="space-y-3" aria-labelledby="passkeys-heading">
<?php $keys = $db->all('SELECT * FROM passkeys WHERE user_id = ? ORDER BY created_at DESC, id DESC', [$user['id']]); ?>
<div class="flex flex-wrap items-baseline justify-between gap-x-4">
<h2 id="passkeys-heading" class="text-xl font-bold">Passkeys <span class="font-mono text-sm font-normal text-slate-500">· <?= e(count($keys)) ?></span></h2>
<p class="text-sm text-slate-600 dark:text-slate-400">Sign in with Touch ID, Face ID, Windows Hello or a security key. Add one for each device you write on.</p>
</div>
<div class="sheet">
<table>
<caption class="sr-only">Your passkeys</caption>
<thead><tr>
<th scope="col" class="sheet-key">Name</th>
<th scope="col">Added (UTC)</th>
<th scope="col">Last used (UTC)</th>
<th scope="col" class="sheet-end">Actions</th>
</tr></thead>
<tbody>
<?php foreach ($keys as $key): $form = 'passkey-' . $key['id']; ?>
<tr>
<th scope="row" class="sheet-key"><input form="<?= e($form) ?>" name="name" value="<?= e($key['name']) ?>" maxlength="191" required aria-label="Name of passkey <?= e($key['name']) ?>"></th>
<td class="whitespace-nowrap font-mono text-xs"><?= e(substr($key['created_at'], 0, 16)) ?></td>
<td class="whitespace-nowrap font-mono text-xs"><?= e($key['last_used_at'] ? substr($key['last_used_at'], 0, 16) : 'Not used yet') ?></td>
<td class="sheet-end">
<form id="<?= e($form) ?>" method="post" action="/admin/security" class="flex justify-end gap-2">
<?= \Core\Csrf::field() ?>
<input type="hidden" name="id" value="<?= e($key['id']) ?>">
<button class="btn" name="action" value="rename">Rename<span class="sr-only"> <?= e($key['name']) ?></span></button>
<a class="btn text-red-700 dark:text-red-400" href="/admin/security/passkeys/<?= e($key['id']) ?>/remove">Remove…<span class="sr-only"> <?= e($key['name']) ?></span></a>
</form>
</td>
</tr>
<?php endforeach ?>
<?php if (!$keys): ?>
<tr><td colspan="4" class="p-8 text-center text-slate-500">No passkeys yet. Password sign-in is available.</td></tr>
<?php endif ?>
<tr><th scope="rowgroup" colspan="4" class="sheet-group"><span class="sticky left-4">Add a passkey</span></th></tr>
<tr class="sheet-new">
<th scope="row" class="sheet-key"><input form="passkey-add" id="passkey-name" name="name" placeholder="My laptop" maxlength="191" required aria-label="New passkey name"></th>
<td colspan="2"><p id="passkey-message" role="status" class="text-sm text-orange-700 dark:text-orange-400"></p></td>
<td class="sheet-end">
<form id="passkey-add" method="post" action="/admin/security" class="flex justify-end">
<?= \Core\Csrf::field() ?>
<button class="btn-primary whitespace-nowrap" type="submit">+ Add a passkey</button>
</form>
</td>
</tr>
</tbody>
</table>
</div>
<p class="text-sm text-slate-600 dark:text-slate-400">Removing every passkey leaves password sign-in only. You’ll be asked for your current password before a passkey is removed.</p>
</section>
</div>
