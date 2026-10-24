<?php use App\Services\MediaLibrary; ?>
<section class="panel max-w-xl space-y-5">
<h1 class="text-3xl font-bold">Delete this image?</h1>
<div class="flex items-center gap-4">
<img src="<?= e(MediaLibrary::url($row['thumb_name'] ?: $row['file_name'])) ?>" alt="" width="96" height="96" class="size-24 shrink-0 rounded-lg bg-slate-100 object-cover dark:bg-slate-800">
<div class="min-w-0">
<p class="truncate font-semibold"><?= e($row['original_name']) ?></p>
<p class="font-mono text-xs text-slate-500 dark:text-slate-400"><?= e($row['width'] . '×' . $row['height']) ?> · <?= e(MediaLibrary::size((int) $row['size_bytes'])) ?> · <?= e(substr($row['created_at'], 0, 10)) ?></p>
</div>
</div>
<?php if ($usage): ?>
<p role="alert" class="rounded-[10px] border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">Used in <?= e($usage) ?> <?= $usage === 1 ? 'place' : 'places' ?> (posts, pages, covers, the site icon or the author photo). It will stop showing there.</p>
<?php else: ?>
<p class="text-sm text-slate-600 dark:text-slate-400">No post or page uses this image.</p>
<?php endif ?>
<form method="post" action="<?= e($action) ?>" class="flex gap-4">
<?= \Core\Csrf::field() ?>
<button class="btn bg-red-700 text-white" type="submit">Delete permanently</button>
<a href="/admin/media" class="btn">Cancel</a>
</form>
</section>
