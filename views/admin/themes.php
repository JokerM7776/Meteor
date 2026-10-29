<?php
// Settings → Themes: installed themes and the active theme's settings. Expects $db and $i18n.
$themeService = new \App\Services\Themes($db);
$installed = $themeService->all();
$downloads = new \App\Services\ThemeDownloads($db, new \Core\GitHubUpdates(new \Core\CurlHttpClient()));
$checked = $downloads->catalog(); $available = $downloads->rows(); $provenance = $downloads->installed();
$activeSlug = $themeService->activeSlug();
$previewSlug = (string) ($_SESSION['theme_preview'] ?? '');
$activeTheme = null;
foreach ($installed as $row) { if ($row['slug'] === $activeSlug && $row['problem'] === '') { $activeTheme = $row; } }
$saved = $_SESSION['old']['theme_settings'] ?? null; unset($_SESSION['old']['theme_settings']);
$chip = 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap';
$themeLabel = fn (mixed $text): string => \App\Services\Themes::label($text, $i18n->defaultLocale());
?>
<div class="space-y-6">
<header><h1 class="text-[32px] font-bold tracking-tight">Settings</h1><p class="text-slate-600 dark:text-slate-400">Install, create and edit your public site's theme.</p></header>
<?php require __DIR__ . '/settings-tabs.php'; ?>
<section id="themes" class="scroll-mt-6 space-y-3" aria-labelledby="themes-heading">
<div class="flex flex-wrap items-baseline justify-between gap-x-4">
<h2 id="themes-heading" class="text-xl font-bold">Themes <span class="font-mono text-sm font-normal text-slate-500 dark:text-slate-400">· <?= e(count($installed) + 1) ?></span></h2>
<p class="text-sm text-slate-600 dark:text-slate-400">Themes live in <code class="font-mono">storage/themes/&lt;name&gt;/</code>. Visitors may see the previous look for up to 5 minutes after a change. Guide: <code class="font-mono">docs/THEMES.md</code></p>
</div>
<div class="sheet">
<table>
<caption class="sr-only">Installed themes</caption>
<thead><tr>
<th scope="col" class="sheet-key">Theme</th>
<th scope="col">Version</th>
<th scope="col">Author</th>
<th scope="col">API</th>
<th scope="col">Status</th>
<th scope="col" class="sheet-end">Actions</th>
</tr></thead>
<tbody>
<tr>
<th scope="row" class="sheet-key"><span class="block font-semibold">Built-in</span><span class="block font-mono text-xs font-normal text-slate-500 dark:text-slate-400">Meteor’s own look</span></th>
<td class="font-mono text-xs"><?= e(\Core\Version::current()) ?></td>
<td class="text-sm">Meteor Team</td>
<td class="font-mono text-xs"><?= e(implode(', ', \App\Services\Themes::API)) ?></td>
<td><?php if ($activeTheme === null): ?><span class="<?= $chip ?> bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900">Active</span><?php else: ?><span class="<?= $chip ?> border border-dashed border-slate-400 text-slate-700 dark:text-slate-300">Available</span><?php endif ?></td>
<td class="sheet-end"><?php if ($activeTheme !== null): ?><form method="post" action="/admin/themes"><?= \Core\Csrf::field() ?><input type="hidden" name="action" value="deactivate"><button class="btn">Use built-in</button></form><?php endif ?></td>
</tr>
<?php foreach ($installed as $row): $isActive = $activeTheme !== null && $activeTheme['slug'] === $row['slug']; $isPreview = $previewSlug === $row['slug'] && $row['problem'] === ''; ?>
<tr>
<th scope="row" class="sheet-key"><span class="block font-semibold"><?= e($row['name']) ?></span><span class="block font-mono text-xs font-normal text-slate-500 dark:text-slate-400"><?= e($row['slug']) ?></span><span class="block font-mono text-xs font-normal text-slate-500 dark:text-slate-400"><?= e(implode(' · ', \App\Services\Themes::files($row))) ?></span><?php if ($row['description'] !== ''): ?><span class="mt-1 block text-xs font-normal text-slate-600 dark:text-slate-400"><?= e($row['description']) ?></span><?php endif ?></th>
<td class="font-mono text-xs"><?= e($row['version']) ?>
<?php if (($available[$row['slug']]['state'] ?? '') === 'update'): ?><span class="<?= e($chip) ?> bg-amber-100 text-amber-950">Update available</span><?php endif ?>
<?php if (isset($provenance[$row['slug']])): ?><span class="block font-display text-xs text-slate-500 dark:text-slate-400">From the catalog</span><?php endif ?></td>
<td class="text-sm"><?php if ($row['homepage'] !== ''): ?><a class="text-orange-700 underline dark:text-orange-400" href="<?= e($row['homepage']) ?>" rel="noopener"><?= e($row['author'] ?: $row['homepage']) ?></a><?php else: ?><?= e($row['author']) ?><?php endif ?></td>
<td class="font-mono text-xs"><?= e($row['api'] ?: '–') ?></td>
<td><?php if ($row['problem'] !== ''): ?><span class="<?= $chip ?> bg-red-50 text-red-900 dark:bg-red-950 dark:text-red-200">Problem</span><span class="mt-1 block text-xs text-red-900 dark:text-red-300"><?= e($row['problem']) ?></span>
<?php elseif ($isActive): ?><span class="<?= $chip ?> bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900">Active</span>
<?php elseif ($isPreview): ?><span class="<?= $chip ?> bg-amber-100 text-amber-950">Previewing</span>
<?php else: ?><span class="<?= $chip ?> border border-dashed border-slate-400 text-slate-700 dark:text-slate-300">Available</span><?php endif ?></td>
<td class="sheet-end"><div class="flex justify-end gap-2 whitespace-nowrap">
<?php if ($row['problem'] === '' && !$isActive): ?><form method="post" action="/admin/themes"><?= \Core\Csrf::field() ?><input type="hidden" name="slug" value="<?= e($row['slug']) ?>"><button name="action" value="activate" class="btn-primary">Activate</button></form>
<form method="post" action="/admin/themes"><?= \Core\Csrf::field() ?><input type="hidden" name="slug" value="<?= e($row['slug']) ?>"><?php if ($isPreview): ?><button name="action" value="stop_preview" class="btn">Stop preview</button><?php else: ?><button name="action" value="preview" class="btn">Preview</button><?php endif ?></form>
<?php if ($isPreview): ?><a class="btn" href="/<?= e($i18n->defaultLocale()) ?>/">View preview</a><?php endif ?><?php endif ?>
<a class="btn" href="/admin/themes/<?= e($row['slug']) ?>/download">Download<span class="sr-only"> <?= e($row['name']) ?></span></a>
<?php if ($row['slug'] !== $activeSlug): ?><a class="btn" href="/admin/themes/<?= e($row['slug']) ?>/delete">Delete<span class="sr-only"> <?= e($row['name']) ?></span></a><?php endif ?>
</div></td>
</tr>
<?php endforeach ?>
</tbody>
</table>
</div>
<?php if (!$themeService->find('starter')): ?><form method="post" action="/admin/themes" class="flex flex-wrap items-center gap-x-4 gap-y-2"><?= \Core\Csrf::field() ?><button name="action" value="install_starter" class="btn">Install the starter theme</button><span class="text-sm text-slate-600 dark:text-slate-400">A small example theme to preview, try and copy from.</span></form><?php endif ?>
</section>
<?php if ($activeTheme !== null && $activeTheme['settings']): $values = $themeService->values($activeTheme); $errors = $saved && $saved['slug'] === $activeTheme['slug'] ? $saved['errors'] : []; ?>
<section id="theme-settings" class="panel scroll-mt-6 space-y-4" aria-labelledby="theme-settings-heading">
<h2 id="theme-settings-heading" class="text-xl font-bold"><?= e($activeTheme['name']) ?> settings</h2>
<form method="post" action="/admin/themes" class="space-y-4">
<?= \Core\Csrf::field() ?><input type="hidden" name="action" value="settings"><input type="hidden" name="slug" value="<?= e($activeTheme['slug']) ?>">
<div class="grid gap-4 sm:grid-cols-2">
<?php foreach ($activeTheme['settings'] as $setting):
$key = $setting['key']; $id = 'theme-setting-' . $key; $error = $errors[$key] ?? '';
$value = $saved && isset($saved['values'][$key]) && $saved['slug'] === $activeTheme['slug'] ? $saved['values'][$key] : $values[$key];
$invalid = $error !== '' ? ' aria-invalid="true" aria-describedby="' . e($id) . '-error"' : ''; ?>
<?php if ($setting['type'] === 'toggle'): ?>
<label class="flex min-h-11 items-center gap-2 sm:col-span-2"><input type="checkbox" name="theme[<?= e($key) ?>]" value="1" <?= $value === true || $value === '1' ? 'checked' : '' ?>><?= e($themeLabel($setting['label'])) ?></label>
<?php else: ?>
<div class="field<?= $setting['type'] === 'text' && $setting['max'] > 200 ? ' sm:col-span-2' : '' ?>"><label for="<?= e($id) ?>"><?= e($themeLabel($setting['label'])) ?></label>
<?php if ($setting['type'] === 'select'): ?><select id="<?= e($id) ?>" name="theme[<?= e($key) ?>]"<?= $invalid ?>><?php foreach ($setting['options'] as $option => $optionLabel): ?><option value="<?= e($option) ?>" <?= (string) $value === (string) $option ? 'selected' : '' ?>><?= e($themeLabel($optionLabel)) ?></option><?php endforeach ?></select>
<?php elseif ($setting['type'] === 'color'): ?><input id="<?= e($id) ?>" type="color" name="theme[<?= e($key) ?>]" value="<?= e($value) ?>" class="h-11 w-24 p-1"<?= $invalid ?>>
<?php elseif ($setting['max'] > 200): ?><textarea id="<?= e($id) ?>" name="theme[<?= e($key) ?>]" rows="3" maxlength="<?= e($setting['max']) ?>"<?= $invalid ?>><?= e($value) ?></textarea>
<?php else: ?><input id="<?= e($id) ?>" name="theme[<?= e($key) ?>]" value="<?= e($value) ?>" maxlength="<?= e($setting['max']) ?>"<?= $invalid ?>><?php endif ?>
<?php if ($error !== ''): ?><p id="<?= e($id) ?>-error" class="text-sm text-red-800 dark:text-red-300"><?= e($error) ?></p><?php endif ?>
</div>
<?php endif ?>
<?php endforeach ?>
</div>
<button class="btn-primary">Save theme settings</button>
</form>
</section>
<?php endif ?>

<?php require __DIR__ . '/theme-catalog.php'; ?>
<div class="grid items-start gap-6 xl:grid-cols-3">
<section class="panel space-y-4" aria-labelledby="upload-heading">
<h2 id="upload-heading" class="text-xl font-bold">Upload a theme zip</h2>
<p>Themes run PHP code on your server; install only themes from people you trust.</p>
<form method="post" action="/admin/themes" enctype="multipart/form-data" class="space-y-4">
<?= \Core\Csrf::field() ?><input type="hidden" name="action" value="upload">
<div class="field"><label for="theme-zip">Theme zip (up to 20 MB)</label><input id="theme-zip" type="file" name="theme_zip" accept=".zip" required></div>
<label class="flex min-h-11 items-center gap-2"><input type="checkbox" name="replace" value="1">Replace the installed theme with the same name</label>
<button class="btn-primary">Upload theme</button>
</form>
</section>
<section class="panel space-y-4" aria-labelledby="create-heading">
<h2 id="create-heading" class="text-xl font-bold">Create a theme</h2>
<form method="post" action="/admin/themes" class="space-y-4">
<?= \Core\Csrf::field() ?><input type="hidden" name="action" value="create">
<div class="field"><label for="theme-name">Name</label><input id="theme-name" name="name" maxlength="100" required></div>
<div class="field"><label for="theme-slug">Folder name (optional)</label><input id="theme-slug" name="slug" maxlength="63" pattern="[a-z0-9][a-z0-9-]{0,62}" placeholder="Derived from the name"></div>
<button class="btn-primary">Create theme</button>
</form>
</section>
<section class="panel space-y-4" aria-labelledby="copy-heading">
<h2 id="copy-heading" class="text-xl font-bold">Copy a template</h2>
<form method="post" action="/admin/themes" class="space-y-4">
<?= \Core\Csrf::field() ?><input type="hidden" name="action" value="copy">
<div class="field"><label for="copy-theme">Theme</label><select id="copy-theme" name="slug" required><option value="">Choose a theme</option>
<?php foreach ($installed as $row): if ($row['problem'] !== '') { continue; } ?><option value="<?= e($row['slug']) ?>"><?= e($row['name']) ?></option><?php endforeach ?>
</select></div>
<div class="field"><label for="copy-template">Template</label><select id="copy-template" name="template" required>
<?php foreach (\App\Services\Themes::TEMPLATES as $group => $templates): ?><optgroup label="<?= e($group) ?>">
<?php foreach ($templates as $path => $description): ?><option value="<?= e($path) ?>"><?= e($path . ' — ' . $description) ?></option><?php endforeach ?>
</optgroup><?php endforeach ?>
</select></div>
<button class="btn-primary">Copy template</button>
</form>
</section>
</div>
</div>
