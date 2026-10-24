<form action="/admin/login" method="post" class="flex flex-col gap-5 rounded-2xl border border-slate-200 bg-white p-8 shadow-lg shadow-slate-900/5 dark:border-slate-800 dark:bg-slate-900">
<div>
<h1 class="text-[26px] font-bold tracking-tight">Sign in</h1>
<p class="mt-1 text-[15px] text-slate-600 dark:text-slate-400">Write and translate posts.</p>
</div>
<button id="passkey-login" type="button" class="btn-primary min-h-[52px] w-full">Sign in with a passkey</button>
<p class="text-center text-sm text-slate-600 dark:text-slate-400">Use Touch ID, Face ID, Windows Hello or a security key.</p>
<p id="passkey-message" role="status" class="text-sm text-orange-700 dark:text-orange-400">
</p>
<div class="flex items-center gap-4 text-sm text-slate-500 dark:text-slate-400">
<hr class="flex-1 border-slate-200">or use your password<hr class="flex-1 border-slate-200">
</div>

<?= \Core\Csrf::field() ?>
<label class="field">Username<input id="username" name="username" autocomplete="username webauthn" required maxlength="191" value="<?= e($username ?? '') ?>">
</label>
<label class="field">Password<input name="password" type="password" autocomplete="current-password" required<?= ($username ?? '') !== '' ? ' autofocus' : '' ?>>
</label>
<?php if ($turnstileKey !== ''): ?>
<div class="cf-turnstile min-h-[65px]" data-sitekey="<?= e($turnstileKey) ?>" data-theme="auto" data-size="flexible"></div>
<?php endif ?>
<button type="submit" class="btn">Sign in with password</button>
</form>
<?php if ($turnstileKey !== ''): ?><script src="<?= e(\Core\Turnstile::SCRIPT) ?>" async defer></script><?php endif ?>
<a href="/<?= e($i18n->defaultLocale()) ?>/" class="flex min-h-11 items-center justify-center text-sm text-slate-600 dark:text-slate-400">← Back to the site</a>
