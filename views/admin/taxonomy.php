<div class="max-w-5xl space-y-6">
<header>
<h1 class="text-[32px] font-bold tracking-tight">Categories &amp; tags</h1>
<p class="text-slate-600 dark:text-slate-400">Shared slugs, names in every language. Deleting a term detaches it from posts.</p>
</header>
<?php foreach (['category' => ['categories', 'category_translations', 'category_id', 'Categories'], 'tag' => ['tags', 'tag_translations', 'tag_id', 'Tags']] as $kind => [$table, $translationTable, $foreign, $heading]): ?>
<section class="panel space-y-4">
<h2 class="text-xl font-bold">
<?= e($heading) ?>
</h2>
<?php $terms = $db->all("SELECT * FROM $table ORDER BY slug"); $terms[] = ['id' => 0, 'slug' => '']; foreach ($terms as $term): $names = $term['id'] ? array_column($db->all("SELECT locale, name FROM $translationTable WHERE $foreign = ?", [$term['id']]), 'name', 'locale') : []; ?>
<form method="post" class="space-y-3 border-t border-slate-200 pt-4 dark:border-slate-800">
<?= \Core\Csrf::field() ?>
<input type="hidden" name="kind" value="<?= e($kind) ?>">
<input type="hidden" name="id" value="<?= e($term['id']) ?>">
<h3 class="font-semibold">
<?= $term['id'] ? e($term['slug']) : 'Add ' . e($kind) ?>
</h3>
<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
<label class="field">Slug<input name="slug" value="<?= e($term['slug']) ?>" maxlength="191" required>
</label>
<?php foreach ($i18n->registry() as $code => $language): ?>
<label class="field">
<?= e($language['name']) ?>
<input name="names[<?= e($code) ?>]" lang="<?= e($code) ?>" value="<?= e($names[$code] ?? '') ?>" maxlength="191">
</label>
<?php endforeach ?>
</div>
<button class="btn" name="action" value="save">
<?= $term['id'] ? 'Save names' : 'Create' ?>
</button>
<?php if ($term['id']): ?> <button class="btn text-red-700 dark:text-red-400" name="action" value="delete" formnovalidate>Delete and detach</button>
<?php endif ?>
</form>
<?php endforeach ?>
</section>
<?php endforeach ?>
</div>
