<section class="panel max-w-xl space-y-5">
<h1 class="text-3xl font-bold">Delete announcement?</h1>
<blockquote class="border-s-4 border-slate-300 ps-4 text-slate-700 dark:border-slate-600 dark:text-slate-300">
<?php if ($row['title'] !== ''): ?><p class="font-semibold"><?= e($row['title']) ?></p><?php endif ?>
<p><?= nl2br(e($row['message']), false) ?></p>
</blockquote>
<p>It disappears from every language. To hide it for now, untick “Show” instead.</p>
<form method="post" action="<?= e($action) ?>" class="flex gap-4">
<?= \Core\Csrf::field() ?>
<button class="btn-danger" type="submit">Delete permanently</button>
<a href="/admin/announcements" class="btn">Cancel</a>
</form>
</section>
