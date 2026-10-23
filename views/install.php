<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Install Meteor</title><link rel="stylesheet" href="<?= e(asset('assets/style.css')) ?>"><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;500;600;700&amp;display=swap"></head>
<body class="admin bg-slate-50 font-display text-slate-900 dark:bg-slate-950 dark:text-slate-100">
<main class="mx-auto max-w-2xl space-y-6 px-6 py-12">
<h1 class="text-3xl font-bold">Install Meteor</h1>
<p>Connect an empty database and create your first admin account. If this form is blocked by your host, see <code>config/.env.example</code> to install from a <code>.env</code> file instead.</p>
<ul class="rounded-xl border border-slate-200 p-5">
<?php foreach ($checks as $label => $passed): ?><li><?= $passed ? '✓' : '✗' ?> <?= e($label) ?></li><?php endforeach ?>
</ul>
<?php foreach ([$error, $message] as $notice): if ($notice): ?><p role="status" class="rounded-lg bg-amber-50 p-4 text-amber-950"><?= e($notice) ?></p><?php endif; endforeach ?>
<form method="post" action="/" class="space-y-5">
<?= \Core\Csrf::field() ?>
<label class="block">Database driver<select name="db_driver" class="mt-1 min-h-11 w-full rounded-lg border p-2"><?php foreach (['mysql' => 'MariaDB / MySQL', 'sqlite' => 'SQLite'] as $key => $label): ?><option value="<?= e($key) ?>" <?= $values['db_driver'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach ?></select></label>
<?php foreach (['db_host' => 'MySQL host', 'db_port' => 'MySQL port', 'db_name' => 'MySQL database', 'db_user' => 'MySQL user', 'db_path' => 'SQLite file path', 'url' => 'Site URL', 'title' => 'Site title', 'username' => 'Admin username'] as $key => $label): ?>
<label class="block"><?= e($label) ?><input class="mt-1 min-h-11 w-full rounded-lg border p-2" name="<?= e($key) ?>" value="<?= e($values[$key]) ?>"></label>
<?php endforeach ?>
<?php foreach (['db_password' => 'Database password (MySQL)', 'password' => 'Admin password (at least 12 characters)', 'confirm' => 'Confirm admin password'] as $key => $label): ?>
<label class="block"><?= e($label) ?><input class="mt-1 min-h-11 w-full rounded-lg border p-2" type="password" autocomplete="new-password" name="<?= e($key) ?>"></label>
<?php endforeach ?>
<div class="flex gap-3"><button class="min-h-11 rounded-lg border px-4" name="action" value="test">Test connection</button><button class="min-h-11 rounded-lg bg-slate-900 px-4 text-white" name="action" value="install" <?= in_array(false, $checks, true) ? 'disabled' : '' ?>>Install Meteor</button></div>
</form></main></body></html>
