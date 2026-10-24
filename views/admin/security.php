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
<?php $turnstile = $protection->values(); $turnstileOld = ($old['action'] ?? '') === 'turnstile' ? $old : []; $turnstileOn = $protection->active(); ?>
<form class="panel flex flex-col gap-4" method="post" action="/admin/security" aria-labelledby="turnstile-heading">
<?= \Core\Csrf::field() ?>
<input type="hidden" name="action" value="turnstile">
<input type="hidden" name="username" value="<?= e($user['username']) ?>" autocomplete="username">
<div class="flex items-center justify-between gap-4">
<h2 id="turnstile-heading" class="text-xl font-bold">Sign-in check · Cloudflare Turnstile</h2>
<span class="status-chip <?= $turnstileOn ? 'bg-slate-900 text-white dark:bg-slate-700' : 'border border-dashed border-slate-400 text-slate-600 dark:text-slate-300' ?>"><?= $turnstileOn ? 'On' : 'Off' ?></span>
</div>
<p class="max-w-4xl text-sm text-slate-600 dark:text-slate-400">Shows a Cloudflare check on the password form of the sign-in page to stop bots. Passkey sign-in skips it. Create a widget for this site’s domain in Cloudflare → Turnstile, then copy its keys here.</p>
<?php if (!$protection->allowed()): ?>
<p class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950">TURNSTILE=off is set in .env, so the sign-in page skips the check. Remove that line to use the settings below.</p>
<?php endif ?>
<input type="hidden" name="turnstile_enabled" value="0">
<label class="flex min-h-11 items-center gap-3"><input type="checkbox" name="turnstile_enabled" value="1" <?= ($turnstileOld['turnstile_enabled'] ?? ($turnstile['enabled'] ? '1' : '0')) === '1' ? 'checked' : '' ?>>Show Turnstile on the sign-in page</label>
<div class="grid gap-4 sm:grid-cols-3">
<label class="field">Site key<input name="turnstile_site_key" maxlength="200" autocomplete="off" spellcheck="false" class="font-mono" value="<?= e($turnstileOld['turnstile_site_key'] ?? $turnstile['site_key']) ?>">
</label>
<label class="field">Secret key<input type="password" name="turnstile_secret_key" maxlength="200" autocomplete="off" spellcheck="false" class="font-mono" placeholder="<?= $turnstile['secret'] !== '' ? 'Saved · leave empty to keep' : '' ?>">
</label>
<label class="field">Current password<input type="password" name="current_password" autocomplete="current-password" required>
</label>
</div>
<p class="text-sm text-slate-600 dark:text-slate-400">Meteor asks Cloudflare to confirm the secret key when you turn the check on. Locked out by a wrong site key? Sign in with a passkey, or add TURNSTILE=off to .env.</p>
<div class="mt-auto flex justify-end">
<button class="btn">Save sign-in check</button>
</div>
</form>
<section class="space-y-3" aria-labelledby="passkeys-heading">
<?php $keys = $db->all('SELECT * FROM passkeys WHERE user_id = ? ORDER BY created_at DESC, id DESC', [$user['id']]); ?>
<div class="flex flex-wrap items-baseline justify-between gap-x-4">
<h2 id="passkeys-heading" class="text-xl font-bold">Passkeys <span class="font-mono text-sm font-normal text-slate-500 dark:text-slate-400">· <?= e(count($keys)) ?></span></h2>
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
<a class="btn-danger-outline" href="/admin/security/passkeys/<?= e($key['id']) ?>/remove">Remove…<span class="sr-only"> <?= e($key['name']) ?></span></a>
</form>
</td>
</tr>
<?php endforeach ?>
<?php if (!$keys): ?>
<tr><td colspan="4" class="p-8 text-center text-slate-500 dark:text-slate-400">No passkeys yet. Password sign-in is available.</td></tr>
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
