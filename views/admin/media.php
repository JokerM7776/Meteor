<?php
use App\Services\MediaLibrary;
$rows = $result['rows'];
$query = fn (array $change) => '?' . http_build_query(array_filter($change + ['q' => $q, 'category' => $category], fn ($v) => $v !== '' && $v !== 1));
$action = '/admin/media' . (($q !== '' || $category !== '' || $result['page'] > 1) ? $query(['page' => $result['page']]) : '');
$formats = ['image/webp' => 'WebP', 'image/avif' => 'AVIF', 'image/jpeg' => 'JPG', 'image/png' => 'PNG', 'image/gif' => 'GIF'];
$isUpload = ($old['id'] ?? null) === null;
?>
<div class="space-y-6">
<header class="flex flex-wrap items-end justify-between gap-4">
<div class="max-w-3xl">
<h1 class="text-[32px] font-bold tracking-tight">Media</h1>
<p class="text-slate-600 dark:text-slate-400">Images for your posts and pages. Each file gets a random name, so its address cannot be guessed. Categories and tags help you find images later.</p>
</div>
<a class="btn" href="/admin/optimization">Optimization · <?= e($options['optimize'] ? strtoupper($options['format']) . ' on' : 'off') ?></a>
</header>

<form method="post" action="/admin/media" enctype="multipart/form-data" class="panel space-y-4" aria-labelledby="upload-heading">
<?= \Core\Csrf::field() ?>
<input type="hidden" name="action" value="upload">
<h2 id="upload-heading" class="text-xl font-bold">Upload images</h2>
<div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
<label class="field">Images<input type="file" name="media[]" accept="image/jpeg,image/png,image/webp,image/gif,image/avif" multiple required></label>
<label class="field">Category<input name="category" list="media-categories" maxlength="191" value="<?= e($isUpload ? ($old['category'] ?? '') : '') ?>" placeholder="e.g. Travel"></label>
<label class="field">Tags · comma separated<input name="tags" maxlength="1500" value="<?= e($isUpload ? ($old['tags'] ?? '') : '') ?>" placeholder="e.g. beach, sunset"></label>
<label class="field">Alt text · describes the image<input name="alt" maxlength="255" value="<?= e($isUpload ? ($old['alt'] ?? '') : '') ?>"></label>
</div>
<div class="flex flex-wrap items-center gap-x-6 gap-y-2">
<button class="btn-primary">Upload</button>
<p class="text-sm text-slate-600 dark:text-slate-400">JPG, PNG, WebP, GIF or AVIF, up to <?= e(MediaLibrary::size(MediaLibrary::uploadLimit())) ?> each.
<?= $options['optimize'] ? e('Saved as ' . strtoupper($options['format']) . ' at quality ' . $options['quality'] . ($options['max_size'] ? ', at most ' . $options['max_size'] . ' px on the longest side' : '') . '.') : 'Optimization is off: files are stored as uploaded.' ?></p>
</div>
</form>

<div class="flex flex-wrap items-center justify-between gap-4">
<form method="get" action="/admin/media" class="flex flex-wrap items-center gap-2" role="search">
<label class="sr-only" for="media-search">Search names, alt text, categories and tags</label>
<input id="media-search" type="search" name="q" value="<?= e($q) ?>" placeholder="Search names, alt text and tags" class="w-72">
<label class="sr-only" for="media-category">Category</label>
<select id="media-category" name="category">
<option value="">All categories</option>
<?php foreach ($categories as $name): ?><option value="<?= e($name) ?>" <?= $name === $category ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach ?>
</select>
<button class="btn">Filter</button>
<?php if ($q !== '' || $category !== ''): ?><a class="btn" href="/admin/media">Show all</a><?php endif ?>
</form>
<p class="font-mono text-sm text-slate-500 dark:text-slate-400"><?= e($result['total']) ?> <?= $result['total'] === 1 ? 'image' : 'images' ?></p>
</div>

<div class="sheet">
<table>
<caption class="sr-only">Uploaded images</caption>
<thead><tr>
<th scope="col" class="sheet-key md:min-w-72">Image</th>
<th scope="col">Alt text</th>
<th scope="col">Category</th>
<th scope="col">Tags</th>
<th scope="col">Uploaded</th>
<th scope="col" class="sheet-end">Actions</th>
</tr></thead>
<tbody>
<?php foreach ($rows as $row): $form = 'media-' . $row['id']; $o = (int) ($old['id'] ?? 0) === (int) $row['id'] ? $old : []; $label = $row['original_name']; ?>
<tr>
<th scope="row" class="sheet-key md:min-w-72">
<span class="flex items-center gap-3">
<a href="<?= e(MediaLibrary::url($row['file_name'])) ?>" target="_blank" rel="noopener" class="shrink-0"><img src="<?= e(MediaLibrary::url($row['thumb_name'] ?: $row['file_name'])) ?>" alt="Open <?= e($label) ?>" width="56" height="56" loading="lazy" class="size-14 rounded-lg bg-slate-100 object-cover dark:bg-slate-800"></a>
<span class="min-w-0">
<span class="block truncate font-semibold text-slate-900 dark:text-slate-100" title="<?= e($label) ?>"><?= e($label) ?></span>
<span class="mt-0.5 block font-mono text-xs text-slate-500 dark:text-slate-400"><?= e($row['width'] . '×' . $row['height']) ?> · <?= e($formats[$row['mime_type']] ?? $row['mime_type']) ?> · <?= e(MediaLibrary::size((int) $row['size_bytes'])) ?></span>
</span>
</span>
</th>
<td class="min-w-48"><input form="<?= e($form) ?>" name="alt" maxlength="255" value="<?= e($o['alt'] ?? $row['alt_text']) ?>" placeholder="Missing" aria-label="Alt text of <?= e($label) ?>"></td>
<td class="min-w-40"><input form="<?= e($form) ?>" name="category" list="media-categories" maxlength="191" value="<?= e($o['category'] ?? $row['category']) ?>" aria-label="Category of <?= e($label) ?>"></td>
<td class="min-w-48"><input form="<?= e($form) ?>" name="tags" maxlength="1500" value="<?= e($o['tags'] ?? implode(', ', $row['tags'])) ?>" aria-label="Tags of <?= e($label) ?>"></td>
<td class="whitespace-nowrap font-mono text-xs text-slate-500 dark:text-slate-400"><?= e(substr($row['created_at'], 0, 10)) ?></td>
<td class="sheet-end">
<form id="<?= e($form) ?>" method="post" action="<?= e($action) ?>" class="flex justify-end gap-2">
<?= \Core\Csrf::field() ?>
<input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= e($row['id']) ?>">
<button class="btn">Save<span class="sr-only"> <?= e($label) ?></span></button>
<a class="btn text-red-700 dark:text-red-400" href="/admin/media/<?= e($row['id']) ?>/delete">Delete<span class="sr-only"> <?= e($label) ?></span></a>
</form>
</td>
</tr>
<?php endforeach ?>
<?php if (!$rows): ?>
<tr><td colspan="6" class="p-8 text-center text-slate-500 dark:text-slate-400"><?= $q !== '' || $category !== '' ? 'No images match this filter.' : 'No images yet. Upload one above, or from the editor.' ?></td></tr>
<?php endif ?>
</tbody>
</table>
</div>
<div class="flex items-center justify-between text-sm">
<span>Page <?= e($result['page']) ?> of <?= e($result['pages']) ?></span>
<nav aria-label="Pagination" class="flex gap-2">
<?php if ($result['page'] > 1): ?><a class="btn" href="<?= e($query(['page' => $result['page'] - 1])) ?>">← Previous</a><?php endif ?>
<?php if ($result['page'] < $result['pages']): ?><a class="btn" href="<?= e($query(['page' => $result['page'] + 1])) ?>">Next →</a><?php endif ?>
</nav>
</div>
<datalist id="media-categories"><?php foreach ($categories as $name): ?><option value="<?= e($name) ?>"></option><?php endforeach ?></datalist>
</div>
