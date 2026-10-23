<?php
$registry = $i18n->registry();
$kind = $page ? 'page' : 'post';
$base = $item['base_locale'];
$baseTitle = $ts[$base]['title'] ?? '';
$publicUrl = '/' . $locale . ($page ? '/' : '/posts/') . rawurlencode((string) ($item['slug'] ?? ''));
$isPublic = $id && !empty($item['is_published']) && ($ts[$locale]['status'] ?? '') === 'published'
    && ($page || (($item['published_at'] ?? null) !== null && $item['published_at'] <= gmdate('Y-m-d H:i:s')));
?>
<div class="mb-5 flex flex-wrap items-end justify-between gap-x-6 gap-y-4">
<div>
<a class="inline-flex min-h-11 items-center text-sm text-orange-700 dark:text-orange-400" href="/admin/<?= $page ? 'pages' : 'posts' ?>">← All <?= $page ? 'pages' : 'posts' ?></a>
<h1 class="text-2xl font-bold"><?= e($title) ?></h1>
</div>
<div class="flex w-full flex-wrap items-center gap-3 sm:w-auto sm:justify-end">
<form method="get" action="/admin/editor" class="flex w-full min-w-0 items-center gap-2 sm:w-auto" data-language-switch>
<?php if ($id): ?><input type="hidden" name="post" value="<?= e($id) ?>"><?php endif ?>
<input type="hidden" name="kind" value="<?= e($kind) ?>">
<input type="hidden" name="from" value="<?= e($base) ?>">
<label class="sr-only" for="editor-language">Language</label>
<select id="editor-language" name="locale" class="w-full min-w-0 sm:w-auto sm:min-w-56">
<?php foreach ($registry as $code => $language): if ($language['status'] === 'off') { continue; } $state = $ts[$code]['status'] ?? 'missing'; ?>
<option value="<?= e($code) ?>" lang="<?= e($code) ?>" <?= $code === $locale ? 'selected' : '' ?>><?= e($language['native_name'] . ' · ' . ucfirst($state) . ($code === $base ? ' · base' : '')) ?></option>
<?php endforeach ?>
</select>
<button class="btn" data-language-go>Open</button>
</form>
<?php if ($isPublic): ?>
<a class="btn" href="<?= e($publicUrl) ?>" target="_blank" rel="noopener">View ↗<span class="sr-only"> (opens in a new tab)</span></a>
<?php endif ?>
<button form="translation" name="status" value="draft" class="btn">Save draft</button>
<button form="translation" name="status" value="published" class="btn bg-orange-700 text-white hover:border-orange-800 hover:bg-orange-800">Publish</button>
</div>
</div>
<?php if ($error): ?>
<p id="validation-error" role="alert" class="mb-5 rounded-lg border border-red-200 bg-red-50 p-4 text-red-800">
<?= e($error) ?> Your text has been preserved.</p>
<?php endif ?>
<form id="translation" method="post" action="<?= e($target) ?>">
<?= \Core\Csrf::field() ?>
<details class="panel mb-5" <?= !$id || $error ? 'open' : '' ?>>
<summary class="min-h-11 cursor-pointer font-semibold">Shared settings <span class="font-normal text-slate-500">· applies to all languages</span>
</summary>
<div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
<div class="field" data-slug-field data-base-title="<?= e($baseTitle) ?>" data-is-base="<?= $locale === $base ? '1' : '0' ?>">
<label for="slug">Slug</label>
<div class="flex gap-2">
<input id="slug" name="slug" maxlength="191" class="min-w-0 flex-1" value="<?= e($values['slug'] ?? '') ?>" placeholder="Generated from the title" aria-describedby="slug-hint" spellcheck="false" autocomplete="off">
<button type="button" class="btn shrink-0" data-slug-generate hidden>From title</button>
</div>
<span id="slug-hint" class="text-sm font-normal text-slate-500 dark:text-slate-400">Leave empty to generate it from the <?= e($registry[$base]['name'] ?? $base) ?> title.</span>
</div>
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
<?php $fieldName = 'cover_image'; $fieldValue = (string) ($values['cover_image'] ?? ''); $fieldLabel = 'Cover'; $emptyText = 'No cover';
$fieldHint = 'Shown on cards and at the top of the post.'; $pickerTitle = 'Choose cover'; $pickerAction = 'Use as cover'; require __DIR__ . '/media-field.php'; ?>
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
<section class="order-last overflow-hidden rounded-[14px] border border-slate-200 bg-slate-50 xl:order-none dark:border-slate-800 dark:bg-slate-900" aria-labelledby="reference-heading">
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
<?php require __DIR__ . '/media-picker.php'; require __DIR__ . '/link-dialog.php'; ?>
