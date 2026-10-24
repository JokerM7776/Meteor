<section class="panel max-w-xl space-y-5">
<h1 class="text-3xl font-bold">Delete <?= e($kind) ?> “<?= e($term['names'][$i18n->defaultLocale()] ?? $term['slug']) ?>”?</h1>
<p>The slug <code class="font-mono"><?= e($term['slug']) ?></code> and its names in every language will be deleted.
<?= (int) $term['post_count'] === 1 ? '1 post' : e($term['post_count']) . ' posts' ?> will be detached from it; the posts themselves stay.</p>
<form method="post" action="<?= e($action) ?>" class="flex gap-4">
<?= \Core\Csrf::field() ?>
<button class="btn-danger" type="submit">Delete <?= e($kind) ?></button>
<a href="/admin/taxonomy<?= $kind === 'tag' ? '?tab=tags' : '' ?>" class="btn">Cancel</a>
</form>
</section>
