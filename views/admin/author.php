<?php
use App\Services\Author;
$locales = $i18n->registry();
$text = [];
foreach ($db->all("SELECT `key`, locale, value FROM settings WHERE locale IS NOT NULL AND `key` IN ('author_name', 'author_bio')") as $row) { $text[$row['key']][$row['locale']] = $row['value']; }
$value = fn (string $key, string $code) => $old['text'][$key][$code] ?? $text[$key][$code] ?? '';
?>
<div class="space-y-6">
<header class="max-w-3xl">
<h1 class="text-[32px] font-bold tracking-tight">Settings</h1>
<p class="text-slate-600 dark:text-slate-400">Who writes this site. The profile is published as an <a class="text-orange-700 underline dark:text-orange-400" href="https://microformats.org/wiki/representative-h-card-authoring" rel="noopener" target="_blank">h-card</a>, so other sites and readers can find your name, photo and profiles.</p>
</header>
<?php require __DIR__ . '/settings-tabs.php'; ?>
<form method="post" class="space-y-8">
<?= \Core\Csrf::field() ?>
<section class="panel space-y-5" aria-labelledby="profile-heading">
<h2 id="profile-heading" class="text-xl font-bold">Photo &amp; profiles · all languages</h2>
<div class="grid gap-6 lg:grid-cols-2">
<?php $fieldName = 'author_avatar'; $fieldValue = (string) ($old['author_avatar'] ?? $settings->global('author_avatar')); $fieldLabel = 'Photo'; $emptyText = 'No photo';
$fieldHint = 'Square works best, at least 160 × 160 px.'; $pickerTitle = 'Choose your photo'; $pickerAction = 'Use as photo'; require __DIR__ . '/media-field.php'; ?>
<label class="field">Profile links
<textarea name="author_links" rows="4" class="font-mono text-sm" placeholder="https://github.com/you&#10;Mastodon | https://mastodon.social/@you&#10;mailto:you@example.com" spellcheck="false"><?= e($old['author_links'] ?? $settings->global('author_links')) ?></textarea>
<span class="text-sm font-normal text-slate-500 dark:text-slate-400">One per line, up to <?= e(Author::MAX_LINKS) ?>. Optionally “Label | URL”. They are marked rel="me" so profiles can verify this site.</span>
</label>
</div>
</section>
<section class="space-y-3" aria-labelledby="bio-heading">
<div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1">
<h2 id="bio-heading" class="text-xl font-bold">Name &amp; bio</h2>
<p class="text-sm text-slate-600 dark:text-slate-400">An empty cell uses the <?= e($locales[$i18n->defaultLocale()]['name']) ?> text. Without a name, the author card is not shown.</p>
</div>
<div class="sheet">
<table>
<caption class="sr-only">Author name and bio by language</caption>
<thead><tr><th scope="col" class="sheet-key">Text</th><?php $missing = []; foreach ($locales as $code => $language) { require __DIR__ . '/sheet-head.php'; } ?></tr></thead>
<tbody>
<?php foreach (Author::TEXT as $key => [$label, $hint, $max, $multiline]): ?>
<tr>
<th scope="row" class="sheet-key"><span class="font-semibold"><?= e($label) ?></span><span class="block text-xs text-slate-500 dark:text-slate-400"><?= e($hint) ?></span></th>
<?php foreach ($locales as $code => $language): $name = 'text[' . $key . '][' . $code . ']'; $aria = $label . ', ' . $language['name']; $fallback = $code === $i18n->defaultLocale() ? '' : ($text[$key][$i18n->defaultLocale()] ?? ''); ?>
<td><?php if ($multiline): ?><textarea name="<?= e($name) ?>" lang="<?= e($code) ?>" rows="3" maxlength="<?= e($max) ?>" placeholder="<?= e($fallback) ?>" aria-label="<?= e($aria) ?>"><?= e($value($key, $code)) ?></textarea>
<?php else: ?><input name="<?= e($name) ?>" lang="<?= e($code) ?>" maxlength="<?= e($max) ?>" value="<?= e($value($key, $code)) ?>" placeholder="<?= e($fallback) ?>" aria-label="<?= e($aria) ?>"><?php endif ?></td>
<?php endforeach ?>
</tr>
<?php endforeach ?>
</tbody>
</table>
</div>
</section>
<section class="panel space-y-3" aria-labelledby="where-heading">
<h2 id="where-heading" class="text-xl font-bold">Where the card appears</h2>
<input type="hidden" name="post_author_card" value="0">
<label class="flex min-h-11 items-start gap-3"><input type="checkbox" name="post_author_card" value="1" class="mt-0.5 size-5 shrink-0 accent-orange-700" <?= ($old['post_author_card'] ?? $settings->global('post_author_card', '0')) === '1' ? 'checked' : '' ?>><span><span class="font-semibold">At the end of each post</span><span class="block text-sm text-slate-600 dark:text-slate-400">Below the article, before Previous / Next. It is marked as the post’s author (p-author h-card) for readers and other sites.</span></span></label>
<p class="text-sm text-slate-600 dark:text-slate-400">Show the card in the footer on <a class="text-orange-700 underline dark:text-orange-400" href="/admin/menus">Header &amp; footer</a> (left side of the footer → Author card), or on the home page on <a class="text-orange-700 underline dark:text-orange-400" href="/admin/home">Home page</a> (section Author).</p>
</section>
<button class="btn-primary">Save author</button>
</form>
</div>
<?php require __DIR__ . '/media-picker.php'; ?>
