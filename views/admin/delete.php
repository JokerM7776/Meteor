<section class="panel max-w-xl space-y-5">
<h1 class="text-3xl font-bold">
<?= e($title) ?>?</h1>
<p>All translations will be permanently deleted.</p>
<form method="post" action="<?= e($action) ?>" class="flex gap-4">
<?= \Core\Csrf::field() ?>
<button class="btn bg-red-700 text-white" type="submit">Delete permanently</button>
<a href="/admin/posts" class="btn">Cancel</a>
</form>
</section>
