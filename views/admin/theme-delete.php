<section class="panel max-w-xl space-y-5">
<h1 class="text-3xl font-bold">Delete theme?</h1>
<p class="font-semibold"><?= e($row['name']) ?></p>
<?php if ($isActive): ?><p>Switch to another theme before deleting it.</p><a class="btn" href="/admin/themes">Cancel</a>
<?php else: ?>
<p>The theme folder, including any templates you edited, and its settings will be permanently deleted. Any preview of this theme will stop. To keep a copy, <a class="text-orange-700 underline dark:text-orange-400" href="/admin/themes/<?= e($row['slug']) ?>/download">download it</a> first.</p>
<form method="post" action="/admin/themes" class="flex gap-4">
<?= \Core\Csrf::field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="slug" value="<?= e($row['slug']) ?>">
<button class="btn-danger" type="submit">Delete permanently</button><a href="/admin/themes" class="btn">Cancel</a>
</form>
<?php endif ?>
</section>
