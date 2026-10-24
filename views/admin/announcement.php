<?php
// Edit or create one announcement. Expects $row (null when new), $old (re-shown input), $locales and $action.
$value = fn (string $key, string $saved = '') => is_string($old[$key] ?? null) ? $old[$key] : $saved;
$chosen = $old ? (is_array($old['locales'] ?? null) ? array_map('strval', $old['locales']) : []) : ($row['locales'] ?? []);
$scope = $old ? (($old['scope'] ?? 'all') === 'all' ? 'all' : 'some') : (($row['locales'] ?? []) === [] ? 'all' : 'some');
$visible = $old ? ($old['visible'] ?? '1') === '1' : (bool) ($row['is_visible'] ?? 1);
?>
<div class="space-y-6">
<p><a class="inline-flex min-h-11 items-center text-orange-700 underline dark:text-orange-400" href="/admin/announcements">← Announcements</a></p>
<header class="max-w-3xl">
<h1 class="text-[32px] font-bold tracking-tight"><?= e($title) ?></h1>
<p class="text-slate-600 dark:text-slate-400">Shown in a bar above the header. Keep it short: one or two sentences read best.</p>
</header>
<form method="post" action="<?= e($action) ?>" class="grid gap-6 xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
<?= \Core\Csrf::field() ?>
<section class="panel space-y-5" aria-labelledby="content-heading">
<h2 id="content-heading" class="text-xl font-bold">Content</h2>
<label class="field">Title · optional<input name="title" maxlength="120" value="<?= e($value('title', $row['title'] ?? '')) ?>"></label>
<label class="field">Message<textarea name="message" rows="4" maxlength="1000" required><?= e($value('message', $row['message'] ?? '')) ?></textarea></label>
<div class="grid gap-4 sm:grid-cols-2">
<label class="field">Button text · optional<input name="button_text" maxlength="40" value="<?= e($value('button_text', $row['button_text'] ?? '')) ?>" placeholder="Learn more"></label>
<label class="field">Button link<input name="button_url" maxlength="2048" value="<?= e($value('button_url', $row['button_url'] ?? '')) ?>" placeholder="https://… or /{locale}/…" aria-describedby="button-hint"></label>
</div>
<p id="button-hint" class="text-sm text-slate-600 dark:text-slate-400">Fill in both for a button, or leave both empty. Links accept /paths, #anchors, https://, http:// and mailto:. {locale} becomes the reader’s language code, e.g. /{locale}/posts.</p>
</section>
<section class="panel space-y-5" aria-labelledby="where-heading">
<h2 id="where-heading" class="text-xl font-bold">Where it shows</h2>
<fieldset class="space-y-1">
<legend class="mb-1 text-sm font-semibold">Languages</legend>
<label class="flex min-h-11 items-center gap-3"><input type="radio" name="scope" value="all" <?= $scope === 'all' ? 'checked' : '' ?>>All languages, including ones added later</label>
<label class="flex min-h-11 items-center gap-3"><input type="radio" name="scope" value="some" <?= $scope === 'some' ? 'checked' : '' ?>>Only these languages:</label>
<div class="ms-8">
<?php foreach ($locales as $code => $language): ?>
<label class="flex min-h-11 items-center gap-3"><input type="checkbox" name="locales[]" value="<?= e($code) ?>" <?= in_array($code, $chosen, true) ? 'checked' : '' ?>><span lang="<?= e($code) ?>"><?= e($language['native_name']) ?></span><span class="font-mono text-xs text-slate-500 dark:text-slate-400"><?= e($code) ?> · <?= e($language['status']) ?></span></label>
<?php endforeach ?>
</div>
</fieldset>
<input type="hidden" name="visible" value="0">
<label class="flex min-h-11 items-center gap-3 font-semibold"><input type="checkbox" name="visible" value="1" <?= $visible ? 'checked' : '' ?>>Show this announcement</label>
</section>
<div class="flex flex-wrap items-center gap-3 xl:col-span-2">
<button class="btn-primary">Save announcement</button>
<a class="btn" href="/admin/announcements">Cancel</a>
<?php if ($row): ?><a class="btn-danger-outline ms-auto" href="/admin/announcements/<?= e($row['id']) ?>/delete">Delete</a><?php endif ?>
</div>
</form>
</div>
