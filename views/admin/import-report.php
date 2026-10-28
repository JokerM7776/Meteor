<section class="min-w-0 space-y-4">
<h2 class="text-xl font-bold"><?= e($report->check ? 'Check results — nothing was imported yet' : 'Import finished') ?></h2>
<?php if ($checked): ?>
<form method="post" action="<?= e($checked['source'] === 'hexo' ? '/admin/import' : '/admin/import?source=typecho') ?>" class="panel space-y-3">
<?= \Core\Csrf::field() ?><input type="hidden" name="token" value="<?= e($checked['token']) ?>">
<?php if ($checked['source'] === 'typecho' && ($checked['options']['driver'] ?? 'mysql') === 'mysql'): ?>
<label class="field">Typecho database password<input type="password" name="password" required autocomplete="off"></label>
<?php endif ?>
<p class="text-sm text-slate-600 dark:text-slate-400">Checked files are kept privately for up to two hours.</p>
<div class="flex flex-wrap gap-3"><button class="btn-primary" name="action" value="import_checked">Import these <?= e($report->totals['translations']['imported']) ?> items</button><button class="btn" name="action" value="cancel" formnovalidate>Cancel</button></div>
</form>
<?php endif ?>
<?php if ($report->warnings): ?>
<div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950"><ul class="list-disc space-y-1 pl-5">
<?php foreach ($report->warnings as $warning): ?><li><?= e($warning) ?></li><?php endforeach ?>
</ul></div>
<?php endif ?>
<div class="sheet max-w-full"><table><caption class="sr-only">Import totals</caption>
<thead><tr><th scope="col">Content</th><th scope="col"><?= e($report->check ? 'Would import' : 'Imported') ?></th><th scope="col">Skipped</th><th scope="col">Failed</th></tr></thead>
<tbody><?php foreach ($report->totals as $kind => $counts): ?><tr>
<th scope="row"><?= e(ucfirst($kind)) ?><?php if ($kind === 'comments' && $report->commentText() !== ''): ?><span class="block text-xs font-normal text-slate-500 dark:text-slate-400"><?= e($report->commentText()) ?></span><?php endif ?></th>
<?php foreach ($counts as $count): ?><td class="font-mono <?= $count === 0 ? 'text-slate-400 dark:text-slate-500' : '' ?>"><?= e($count) ?></td><?php endforeach ?>
</tr><?php endforeach ?></tbody></table></div>
<div class="sheet max-w-full"><table class="min-w-[720px]"><caption class="sr-only">Import results</caption>
<thead><tr><?php foreach (['Kind', 'Title', 'Language', 'Slug', 'Result', 'Notes'] as $label): ?><th scope="col"><?= e($label) ?></th><?php endforeach ?></tr></thead>
<tbody><?php foreach (array_slice($report->rows, 0, 1000) as $row): ?><tr>
<td><?= e($row['kind']) ?></td>
<td class="min-w-44 max-w-72 wrap-anywhere">
<?php if ($row['id'] !== null): ?><a class="inline-flex min-h-11 items-center font-semibold text-orange-700 dark:text-orange-400" href="/admin/editor?<?= e(http_build_query(['post' => $row['id'], 'locale' => $row['language'], 'kind' => $row['kind']])) ?>"><?= e($row['title']) ?></a><?php else: ?><?= e($row['title']) ?><?php endif ?>
<span class="block font-mono text-xs break-all text-slate-500 dark:text-slate-400"><?= e($row['source']) ?></span>
</td>
<td><?= e($row['language']) ?></td><td class="max-w-48 wrap-anywhere"><?= e($row['slug']) ?></td>
<?php $chip = match ($row['result']) {
    'skipped' => 'border border-dashed border-slate-400 text-slate-600 dark:text-slate-300',
    'failed' => 'border border-red-200 bg-red-50 text-red-900',
    default => 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900',
}; ?>
<td><span class="inline-flex whitespace-nowrap rounded px-2 py-1 text-xs font-semibold <?= e($chip) ?>"><?= e($row['result']) ?></span></td>
<td class="min-w-48 max-w-96 text-xs wrap-anywhere text-slate-500 dark:text-slate-400"><?= e($row['notes']) ?></td>
</tr><?php endforeach ?></tbody></table></div>
<?php if (count($report->rows) > 1000): ?><p>+<?= e(count($report->rows) - 1000) ?> more</p><?php endif ?>
</section>
