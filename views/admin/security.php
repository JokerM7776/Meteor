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
<?php
$captchaKeys = $captcha->values(); $form = ($old['action'] ?? '') === 'captcha' ? $old : [];
$provider = $form['captcha_provider'] ?? $captchaKeys['captcha_provider'];
$signInOn = $captcha->signIn() !== null;
$chip = fn (bool $on, string $yes, string $no) => '<span class="status-chip ' . ($on ? 'bg-slate-900 text-white dark:bg-slate-700">' . $yes : 'border border-dashed border-slate-400 text-slate-600 dark:text-slate-300">' . $no) . '</span>';
$help = ['turnstile' => 'Create a widget for this site’s domain in <a class="underline" href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank" rel="noopener">Cloudflare → Turnstile ↗</a>.',
    'recaptcha' => 'Create v2 “I’m not a robot” checkbox keys at <a class="underline" href="https://www.google.com/recaptcha/admin" target="_blank" rel="noopener">google.com/recaptcha/admin ↗</a> and add your domain.'];
?>
<form class="panel flex flex-col gap-5" method="post" action="/admin/security" aria-labelledby="captcha-heading">
<?= \Core\Csrf::field() ?>
<input type="hidden" name="action" value="captcha">
<input type="hidden" name="username" value="<?= e($user['username']) ?>" autocomplete="username">
<div>
<h2 id="captcha-heading" class="text-xl font-bold">CAPTCHA</h2>
<p class="mt-1 max-w-4xl text-sm text-slate-600 dark:text-slate-400">A check that stops bots. You can save keys for both providers; the whole site uses the one you choose, on the sign-in page and for comments.</p>
</div>
<?php if (!$captcha->allowed()): ?>
<p class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950">CAPTCHA=off (or TURNSTILE=off) is set in .env, so the sign-in page skips the check. Remove that line to use the sign-in setting below.</p>
<?php endif ?>
<div class="grid gap-4 xl:grid-cols-2">
<?php foreach (\Core\Captcha::PROVIDERS as $code => $info): $saved = $captcha->ready($code, $captchaKeys); ?>
<section class="flex flex-col gap-4 rounded-[10px] border p-4 <?= $provider === $code ? 'border-orange-500 dark:border-orange-400' : 'border-slate-200 dark:border-slate-800' ?>" aria-labelledby="<?= e($code) ?>-heading">
<div class="flex flex-wrap items-center justify-between gap-2">
<h3 id="<?= e($code) ?>-heading" class="font-bold"><?= e($info['name']) ?></h3>
<?= $chip($saved, 'Keys saved', 'Not set up') ?>
</div>
<label class="flex min-h-11 items-center gap-3 font-semibold"><input type="radio" name="captcha_provider" value="<?= e($code) ?>" <?= $provider === $code ? 'checked' : '' ?>>Use <?= e($info['name']) ?> on this site</label>
<div class="grid gap-4 sm:grid-cols-2">
<label class="field">Site key<input name="<?= e($code) ?>_site_key" maxlength="200" autocomplete="off" spellcheck="false" class="font-mono" value="<?= e($form[$code . '_site_key'] ?? $captchaKeys[$code . '_site_key']) ?>">
</label>
<label class="field">Secret key<input type="password" name="<?= e($code) ?>_secret_key" maxlength="200" autocomplete="off" spellcheck="false" placeholder="<?= $captchaKeys[$code . '_secret_key'] !== '' ? 'Saved · leave empty to keep' : '' ?>">
</label>
</div>
<?php if ($saved): ?><label class="flex min-h-11 items-center gap-3"><input type="checkbox" name="<?= e($code) ?>_forget" value="1">Remove the saved <?= e($info['company']) ?> keys</label><?php endif ?>
<p class="text-sm text-slate-600 dark:text-slate-400"><?= $help[$code] ?></p>
</section>
<?php endforeach ?>
</div>
<fieldset class="space-y-1"><legend class="mb-1 font-semibold">Where it is shown</legend>
<input type="hidden" name="turnstile_enabled" value="0">
<label class="flex min-h-11 flex-wrap items-center gap-3"><input type="checkbox" name="turnstile_enabled" value="1" <?= ($form['turnstile_enabled'] ?? $captchaKeys['turnstile_enabled']) === '1' ? 'checked' : '' ?>>Sign-in page (password form; passkeys skip it) <?= $chip($signInOn, 'On', 'Off') ?></label>
<p class="flex min-h-11 flex-wrap items-center gap-3 ps-8">Comments <?= $chip($commentsCaptcha, 'On', 'Off') ?> <a class="text-sm underline" href="/admin/comments?tab=settings">Change in Comments → Settings</a></p>
</fieldset>
<p class="text-sm text-slate-600 dark:text-slate-400">Meteor asks the provider to confirm the secret key when the sign-in check is on. Locked out by a wrong site key? Sign in with a passkey, or add CAPTCHA=off to .env.</p>
<div class="flex flex-wrap items-end justify-between gap-4">
<label class="field w-full sm:max-w-xs">Current password<input type="password" name="current_password" autocomplete="current-password" required>
</label>
<button class="btn">Save CAPTCHA</button>
</div>
</form>
<section class="space-y-3" aria-labelledby="devices-heading">
<div class="flex flex-wrap items-baseline justify-between gap-x-4">
<h2 id="devices-heading" class="text-xl font-bold">Signed-in devices <span class="font-mono text-sm font-normal text-slate-500 dark:text-slate-400">· <?= e(count($devices)) ?></span></h2>
<p class="text-sm text-slate-600 dark:text-slate-400">Browsers that ticked “Keep me signed in”. Each stays signed in for <?= \Core\Remember::DAYS ?> days.</p>
</div>
<div class="sheet">
<table>
<caption class="sr-only">Browsers that stay signed in</caption>
<thead><tr>
<th scope="col" class="sheet-key">Device</th>
<th scope="col">Signed in (UTC)</th>
<th scope="col">Last used (UTC)</th>
<th scope="col">Expires (UTC)</th>
<th scope="col" class="sheet-end">Actions</th>
</tr></thead>
<tbody>
<?php foreach ($devices as $device): ?>
<tr>
<th scope="row" class="sheet-key"><span class="font-semibold"><?= e($device['device']) ?></span><?php if ($device['current']): ?> <span class="font-mono text-xs font-normal text-orange-800 dark:text-orange-300">this device</span><?php endif ?></th>
<td class="whitespace-nowrap font-mono text-xs"><?= e(substr((string) $device['created_at'], 0, 16)) ?></td>
<td class="whitespace-nowrap font-mono text-xs"><?= e($device['last_used_at'] ? substr((string) $device['last_used_at'], 0, 16) : '—') ?></td>
<td class="whitespace-nowrap font-mono text-xs"><?= e(substr((string) $device['expires_at'], 0, 10)) ?></td>
<td class="sheet-end">
<?php if ($device['current']): ?><span class="whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">Sign out from the menu</span><?php else: ?>
<form method="post" action="/admin/security" class="flex justify-end"><?= \Core\Csrf::field() ?><input type="hidden" name="id" value="<?= e($device['id']) ?>">
<button class="btn" name="action" value="sign_out_device">Sign out<span class="sr-only"> <?= e($device['device']) ?></span></button></form>
<?php endif ?>
</td>
</tr>
<?php endforeach ?>
<?php if (!$devices): ?>
<tr><td colspan="5" class="p-8 text-center text-slate-500 dark:text-slate-400">No browser is remembered.</td></tr>
<?php endif ?>
</tbody>
</table>
</div>
<form method="post" action="/admin/security" class="flex flex-wrap items-center justify-between gap-4">
<?= \Core\Csrf::field() ?>
<p class="max-w-3xl text-sm text-slate-600 dark:text-slate-400">Signing out everywhere else also ends sessions that did not tick the box; this browser stays signed in. On the server, <code class="whitespace-nowrap font-mono">php bin/sign-out.php</code> signs out every device, this one included.</p>
<button class="btn-danger-outline" name="action" value="sign_out_others">Sign out everywhere else</button>
</form>
</section>
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
