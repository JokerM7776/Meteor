<?php $repo = 'https://github.com/' . \Core\GitHubUpdates::REPOSITORY . '/blob/' . \Core\GitHubUpdates::BRANCH; ?>
<section id="catalog" class="scroll-mt-6 space-y-3" aria-labelledby="catalog-heading">
<div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-3">
<div class="max-w-3xl">
<h2 id="catalog-heading" class="text-xl font-bold">Get themes</h2>
<p class="text-sm text-slate-600 dark:text-slate-400">Find themes in the <a class="text-orange-700 underline dark:text-orange-400" href="<?= e($repo . '/themes/index.json') ?>">catalog on GitHub</a>, or <a class="text-orange-700 underline dark:text-orange-400" href="<?= e($repo . '/CONTRIBUTING.md#publishing-a-theme') ?>">publish your own</a>.</p>
<p class="mt-2 text-sm text-slate-600 dark:text-slate-400">Themes run PHP code on your server. Each catalog version is pinned by checksum to the file that was reviewed, but install only themes you trust.</p>
</div>
<form method="post" action="/admin/themes" class="flex flex-wrap items-center gap-3">
<?= \Core\Csrf::field() ?><input type="hidden" name="action" value="catalog">
<?php if ($checked): ?><p class="text-sm text-slate-500 dark:text-slate-400">Checked <?= e(site_time($checked['checked_at'])) ?></p><?php endif ?>
<button class="btn">Check for themes</button>
</form>
</div>
<?php if ($checked && !$available): ?>
<p class="text-sm text-slate-600 dark:text-slate-400">The catalog has no themes yet. Made one? <a class="text-orange-700 underline dark:text-orange-400" href="<?= e($repo . '/CONTRIBUTING.md#publishing-a-theme') ?>">Publish it</a></p>
<?php elseif ($checked): ?>
<div class="sheet"><table>
<caption class="sr-only">Theme catalog</caption>
<thead><tr><th scope="col" class="sheet-key">Theme</th><th scope="col">Version</th><th scope="col">License</th><th scope="col">Requires</th><th scope="col">Status</th><th scope="col" class="sheet-end">Action</th></tr></thead>
<tbody>
<?php foreach ($available as $slug => $entry): ?>
<tr>
<th scope="row" class="sheet-key"><span class="block font-semibold"><?= e($entry['name']) ?></span><span class="block font-mono text-xs font-normal text-slate-500 dark:text-slate-400"><?= e($slug) ?></span><span class="block text-xs font-normal text-slate-600 dark:text-slate-400"><?= e($entry['description']) ?></span><a class="inline-flex min-h-11 items-center text-xs font-normal text-orange-700 underline dark:text-orange-400" href="<?= e('https://github.com/' . $entry['repo']) ?>"><?= e($entry['author'] ?: $entry['repo']) ?></a></th>
<td class="font-mono text-xs"><?= e($entry['version']) ?></td><td class="text-sm"><?= e($entry['license']) ?></td><td class="font-mono text-xs">Meteor <?= e($entry['requires']) ?> · API <?= e($entry['api']) ?></td>
<td><span class="<?= e($chip) ?> <?= $entry['state'] === 'current' ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900' : ($entry['state'] === 'update' ? 'bg-amber-100 text-amber-950' : 'border border-dashed border-slate-400 text-slate-700 dark:text-slate-300') ?>"><?= e(match ($entry['state']) { 'new' => 'New', 'current' => 'Installed', 'update' => 'Update available', 'local' => 'Name in use', default => $entry['message'] }) ?></span>
<?php if ($entry['state'] === 'local'): ?><span class="mt-1 block text-xs text-slate-600 dark:text-slate-400"><?= e($entry['message']) ?></span><?php endif ?></td>
<td class="sheet-end"><?php if (in_array($entry['state'], ['new', 'update'], true)): ?><form method="post" action="/admin/themes"><?= \Core\Csrf::field() ?><input type="hidden" name="action" value="catalog_install"><input type="hidden" name="slug" value="<?= e($slug) ?>"><button class="btn"><?= $entry['state'] === 'new' ? 'Install' : 'Update' ?><span class="sr-only"> <?= e($entry['name']) ?></span></button></form><?php endif ?></td>
</tr>
<?php endforeach ?>
</tbody></table></div>
<?php endif ?>
</section>
