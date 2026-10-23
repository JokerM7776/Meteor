<div class="mb-5 flex flex-wrap items-center justify-between gap-4">
<div>
<a class="text-sm text-orange-700 dark:text-orange-400" href="/admin/<?= $page ? 'pages' : 'posts' ?>">← All <?= $page ? 'pages' : 'posts' ?>
</a>
<h1 class="mt-2 text-2xl font-bold">
<?= e($title) ?>
</h1>
</div>
<div class="flex gap-3">
<button form="translation" name="status" value="draft" class="btn">Save draft</button>
<button form="translation" name="status" value="published" class="btn bg-orange-700 text-white">Publish</button>
</div>
</div>
<nav aria-label="Translation language" class="mb-5 flex flex-wrap gap-2">
<?php foreach ($i18n->registry() as $code => $language): if ($language['status'] === 'off') { continue; } ?>
<a lang="<?= e($code) ?>" class="btn <?= $code === $locale ? 'bg-slate-900 text-white' : '' ?>" <?= $code === $locale ? 'aria-current="page"' : '' ?> href="?<?= e(http_build_query(['post' => $id, 'kind' => $page ? 'page' : 'post', 'locale' => $code, 'from' => $item['base_locale']])) ?>">
<?= e($language['native_name']) ?> · <?= e($ts[$code]['status'] ?? 'missing') ?>
<?= $code === $item['base_locale'] ? ' · base' : '' ?>
</a>
<?php endforeach ?>
</nav>
<?php if ($error): ?>
<p id="validation-error" role="alert" class="mb-5 rounded-lg border border-red-200 bg-red-50 p-4 text-red-800">
<?= e($error) ?> Your text has been preserved; reselect any cover upload.</p>
<?php endif ?>
<form id="translation" method="post" action="<?= e($target) ?>" enctype="multipart/form-data">
<?= \Core\Csrf::field() ?>
<details class="panel mb-5" <?= !$id || $error ? 'open' : '' ?>>
<summary class="min-h-11 cursor-pointer font-semibold">Shared settings <span class="font-normal text-slate-500">· applies to all languages</span>
</summary>
<div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
<label class="field">Slug<input name="slug" required maxlength="191" value="<?= e($values['slug'] ?? '') ?>">
</label>
<?php if (!$page): ?>
<label class="field">Category<select name="category_id">
<option value="">None</option>
<?php foreach ($categories as $category): ?>
<option value="<?= e($category['id']) ?>" <?= ($values['category_id'] ?? '') == $category['id'] ? 'selected' : '' ?>>
<?= e($category['slug']) ?>
</option>
<?php endforeach ?>
</select>
</label>
<label class="field">Tags · comma separated<input name="tags" value="<?= e($values['tags'] ?? '') ?>">
</label>
<label class="field">Cover · JPG, PNG or WebP, max 5 MB<input type="file" name="cover" accept="image/jpeg,image/png,image/webp">
</label>
<label class="flex min-h-11 items-center gap-2">
<input type="checkbox" name="is_pinned" <?= !empty($values['is_pinned']) ? 'checked' : '' ?>>Pinned</label>
<?php else: ?>
<label class="field">Navigation label<input name="nav_label" maxlength="191" value="<?= e($values['nav_label'] ?? '') ?>">
</label>
<label class="flex min-h-11 items-center gap-2">
<input type="checkbox" name="show_in_nav" <?= !empty($values['show_in_nav']) ? 'checked' : '' ?>>Show in navigation</label>
<?php endif ?>
<label class="flex min-h-11 items-center gap-2">
<input type="checkbox" name="is_published" <?= !empty($values['is_published']) ? 'checked' : '' ?>>Visible on site (published translations)</label>
</div>
</details>
<div class="grid items-stretch gap-5 xl:grid-cols-2">
<section class="overflow-hidden rounded-[14px] border border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="reference-heading">
<div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 bg-white px-5 py-3 dark:border-slate-800 dark:bg-slate-900">
<h2 id="reference-heading" class="font-semibold">
<?= e(strtoupper($from)) ?> · Original · read only</h2>
<nav aria-label="Reference view" class="flex gap-2">
<a class="btn" href="<?= e($target . '&ref=markdown') ?>">Markdown</a>
<a class="btn" href="<?= e($target . '&ref=preview') ?>">Preview</a>
</nav>
</div>
<div class="space-y-4 p-5" lang="<?= e($from) ?>">
<h3 class="text-lg font-semibold">
<?= e($reference['title'] ?? 'No reference translation yet') ?>
</h3>
<p class="text-slate-600 dark:text-slate-400">
<?= e($reference['summary'] ?? '') ?>
</p>
<?php if (($_GET['ref'] ?? '') === 'preview'): ?>
<div class="prose-meteor min-h-[560px]">
<?= $reference['content_html'] ?? '' ?>
</div>
<?php else: ?>
<pre class="min-h-[560px] whitespace-pre-wrap break-words rounded-[10px] border border-slate-200 bg-white p-4 font-mono text-sm leading-7 dark:border-slate-800 dark:bg-slate-950">
<?= e($reference['content_md'] ?? '') ?>
</pre>
<?php endif ?>
</div>
</section>
<section class="overflow-hidden rounded-[14px] border-2 border-slate-900 bg-white dark:border-slate-500 dark:bg-slate-900" aria-labelledby="translation-heading">
<h2 id="translation-heading" class="border-b border-slate-200 px-5 py-5 font-semibold dark:border-slate-800">
<?= e(strtoupper($locale)) ?> · Translation · <span lang="<?= e($locale) ?>">
<?= e($i18n->registry()[$locale]['native_name']) ?>
</span>
</h2>
<div class="space-y-4 p-5" lang="<?= e($locale) ?>" dir="<?= e($i18n->registry()[$locale]['direction']) ?>">
<label class="field">Title<input name="title" required maxlength="255" value="<?= e($values['title'] ?? '') ?>">
</label>
<label class="field">Summary · also the meta description<textarea name="summary" rows="3">
<?= e($values['summary'] ?? '') ?>
</textarea>
</label>
<div class="field" data-md-editor>
<div class="flex flex-wrap items-end justify-between gap-2">
<label id="content-label" for="content-md" lang="en" dir="ltr">Content</label>
<div class="mode-switch" role="group" aria-label="Editor mode" lang="en" dir="ltr" data-mode-switch hidden>
<button type="button" data-mode="visual" aria-pressed="false">Visual</button>
<button type="button" data-mode="markdown" aria-pressed="false">Markdown</button>
</div>
</div>
<div class="flex flex-col">
<div class="md-toolbar" role="toolbar" aria-label="Formatting" aria-controls="content-md" lang="en" dir="ltr" data-toolbar hidden>
<?php foreach ([['p', '¶', 'Normal text'], ['h2', 'H2', 'Heading 2'], ['h3', 'H3', 'Heading 3'], null, ['bold', 'B', 'Bold (Ctrl+B)'], ['italic', 'I', 'Italic (Ctrl+I)'], ['code', '‹›', 'Inline code'], ['link', 'Link', 'Link (Ctrl+K)'], null,
    ['ul', '• List', 'Bulleted list'], ['ol', '1. List', 'Numbered list'], ['quote', '“ ”', 'Quote'], ['codeblock', '{ }', 'Code block'], ['hr', '—', 'Divider'], null, ['image', 'Image', 'Insert image from the media library']] as $tool): ?>
<?php if ($tool === null): ?><span class="md-sep" aria-hidden="true"></span><?php else: [$command, $text, $name] = $tool; ?>
<button type="button" data-cmd="<?= e($command) ?>" title="<?= e($name) ?>" aria-label="<?= e($name) ?>" class="<?= $command === 'bold' ? 'font-bold' : ($command === 'italic' ? 'italic font-serif' : ($command === 'code' || $command === 'codeblock' ? 'font-mono' : '')) ?>"><?= e($text) ?></button>
<?php endif ?>
<?php endforeach ?>
</div>
<div class="md-visual prose-meteor" contenteditable="true" role="textbox" aria-multiline="true" aria-labelledby="content-label" spellcheck="true" data-visual hidden></div>
<textarea id="content-md" name="content_md" class="min-h-[560px] font-mono text-sm leading-7" rows="22" spellcheck="false">
<?= e($values['content_md'] ?? '') ?>
</textarea>
</div>
</div>
</div>
<p class="border-t border-slate-200 px-5 py-3 text-xs text-slate-500 dark:border-slate-800">Visual and Markdown edit the same text. HTML and table of contents are compiled when you save.</p>
</section>
</div>
</form>
<?php if ($id): ?>
<a class="mt-6 inline-flex min-h-11 items-center text-sm text-red-700 dark:text-red-400" href="/admin/<?= $page ? 'pages' : 'posts' ?>/<?= e($id) ?>/delete">Delete <?= $page ? 'page' : 'post' ?>…</a>
<?php endif ?>
<?php require __DIR__ . '/media-picker.php'; ?>
