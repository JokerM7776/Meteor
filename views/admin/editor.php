<?php
$registry = $i18n->registry();
$kind = $page ? 'page' : 'post';
$base = $item['base_locale'];
$baseTitle = $ts[$base]['title'] ?? '';
$publicUrl = '/' . $locale . ($page ? '/' : '/posts/') . rawurlencode((string) ($item['slug'] ?? ''));
$isPublic = $id && !empty($item['is_published']) && ($ts[$locale]['status'] ?? '') === 'published'
    && ($page || (($item['published_at'] ?? null) !== null && $item['published_at'] <= gmdate('Y-m-d H:i:s')));
?>
<?php
$state = $ts[$locale]['status'] ?? 'missing';
$savedAt = $ts[$locale]['updated_at'] ?? null;
// One pair of save buttons per breakpoint: in the top bar from md up, in the bottom bar on phones.
$saveButtons = function (string $extra) use ($state): string {
    $live = $state === 'published';
    // Ctrl+S keeps the current state: it updates a published translation and saves anything else as a draft.
    return '<button form="translation" name="status" value="draft" class="btn ' . $extra . '" title="' . ($live ? 'Save and take this translation off the site' : 'Save without publishing (Ctrl+S)') . '"' . ($live ? '' : ' data-save-default') . '>' . ($live ? 'Unpublish' : 'Save draft') . '</button>'
        . '<button form="translation" name="status" value="published" class="btn-accent ' . $extra . '" title="' . ($live ? 'Save the published translation (Ctrl+S)' : 'Save and publish') . '"' . ($live ? ' data-save-default' : '') . '>' . ($live ? 'Update' : 'Publish') . '</button>';
};
?>
<div class="editor-top flex flex-wrap items-end justify-between gap-x-6 gap-y-4 md:sticky md:top-0 md:z-30">
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
<select id="editor-language" name="locale" class="w-full min-w-0 sm:w-auto sm:min-w-56" aria-describedby="editor-language-hint">
<?php foreach ($registry as $code => $language): if ($language['status'] === 'off') { continue; } $optionState = $ts[$code]['status'] ?? 'missing'; ?>
<option value="<?= e($code) ?>" lang="<?= e($code) ?>" <?= $code === $locale ? 'selected' : '' ?>><?= e($language['native_name'] . ' · ' . ucfirst($optionState) . ($code === $base ? ' · base' : '')) ?></option>
<?php endforeach ?>
</select>
<span id="editor-language-hint" class="sr-only">Choosing a language opens it.</span>
<button class="btn" data-language-go>Open</button>
</form>
<?php if ($isPublic): ?>
<a class="btn" href="<?= e($publicUrl) ?>" target="_blank" rel="noopener">View ↗<span class="sr-only"> (opens in a new tab)</span></a>
<?php endif ?>
<?= $saveButtons('hidden md:inline-flex') ?>
</div>
</div>
<?php if ($error): ?>
<p id="validation-error" role="alert" class="mb-5 rounded-lg border border-red-200 bg-red-50 p-4 text-red-800">
<?= e($error) ?> Your text has been preserved.</p>
<?php endif ?>
<form id="translation" method="post" action="<?= e($target) ?>">
<?= \Core\Csrf::field() ?>
<details class="panel mb-5" <?= !$id || $error ? 'open' : '' ?>>
<summary class="min-h-11 cursor-pointer font-semibold">Shared settings <span class="font-normal text-slate-500 dark:text-slate-400">· applies to all languages</span>
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
<p class="text-sm text-slate-600 sm:col-span-2 dark:text-slate-400">To link this page from the header or footer, add it under <a class="underline" href="/admin/menus">Header &amp; footer</a>, where you can order it and give it a menu label per language.</p>
<?php endif ?>
<?php if (!$page): ?>
<label class="flex min-h-11 items-center gap-2">
<input type="checkbox" name="comments_enabled" <?= !empty($values['comments_enabled']) ? 'checked' : '' ?>>Allow comments<?php if (!$commentsOn): ?> <span class="font-normal text-slate-500 dark:text-slate-400">· comments are off site-wide (<a class="underline" href="/admin/comments?tab=settings">turn on</a>)</span><?php endif ?></label>
<?php endif ?>
<label class="flex min-h-11 items-center gap-2">
<input type="checkbox" name="is_published" <?= !empty($values['is_published']) ? 'checked' : '' ?>>Visible on site (published translations)</label>
<div class="sm:col-span-2 lg:col-span-3">
<label class="flex min-h-11 items-center gap-2">
<input type="checkbox" name="markdown_extended" aria-describedby="extended-hint" <?= !empty($values['markdown_extended']) ? 'checked' : '' ?>>Extended Markdown</label>
<p id="extended-hint" class="ms-7 text-sm text-slate-500 dark:text-slate-400">Adds tables, footnotes <code class="font-mono">[^1]</code>, task lists <code class="font-mono">- [x]</code>, <code class="font-mono">~~strikethrough~~</code>, <code class="font-mono">==highlight==</code>, H<code class="font-mono">~2~</code>O, X<code class="font-mono">^2^</code>, <code class="font-mono">:emoji:</code>, heading ids <code class="font-mono">{#id}</code>, definition lists, automatic links and <code class="font-mono">\</code> escapes. Visual mode shows this syntax as plain text. <a class="underline" href="https://www.markdownguide.org/extended-syntax/" target="_blank" rel="noopener">Syntax guide ↗<span class="sr-only"> (opens in a new tab)</span></a></p>
</div>
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
<p class="border-t border-slate-200 px-5 py-3 text-xs text-slate-500 dark:text-slate-400 dark:border-slate-800">Visual and Markdown edit the same text. HTML and table of contents are compiled when you save.</p>
</section>
</div>
</form>
<?php $deleteUrl = '/admin/' . ($page ? 'pages' : 'posts') . '/' . $id . '/delete'; $deleteLabel = 'Delete ' . ($page ? 'page' : 'post') . '…'; ?>
<?php if ($id): ?>
<a class="mt-6 inline-flex min-h-11 items-center text-sm text-red-700 md:hidden dark:text-red-400" href="<?= e($deleteUrl) ?>"><?= e($deleteLabel) ?></a>
<?php endif ?>
<div class="editor-bottom sticky bottom-0 z-30 flex flex-wrap items-center justify-between gap-x-6 gap-y-2" role="region" aria-label="Save status">
<p class="flex min-h-11 flex-wrap items-center gap-x-3 gap-y-1 text-sm">
<span class="status-chip <?= $state === 'published' ? 'bg-slate-900 text-white dark:bg-slate-700' : ($state === 'draft' ? 'bg-amber-100 text-amber-950' : 'border border-dashed border-slate-500 text-slate-700 dark:text-slate-300') ?>"><?= e($state === 'published' ? '✓ Published' : ($state === 'draft' ? '◐ Draft' : 'Not saved yet')) ?><span class="sr-only"> · <?= e($registry[$locale]['name']) ?></span></span>
<span class="text-slate-600 dark:text-slate-400" data-save-state data-saved="<?= $savedAt && !$error ? '1' : '0' ?>" aria-live="polite"><?= $error ? 'Not saved: fix the problem above.' : ($savedAt ? 'Saved ' . e(substr((string) $savedAt, 0, 16)) . ' UTC' : 'Not saved yet') ?></span>
<span class="hidden text-xs text-slate-600 lg:inline dark:text-slate-400"><kbd class="font-mono">Ctrl</kbd>+<kbd class="font-mono">S</kbd> <?= $state === 'published' ? 'updates' : 'saves a draft' ?></span>
</p>
<div class="flex items-center gap-3">
<?php if ($id): ?><a class="hidden min-h-11 items-center text-sm text-red-700 md:inline-flex dark:text-red-400" href="<?= e($deleteUrl) ?>"><?= e($deleteLabel) ?></a><?php endif ?>
<?= $saveButtons('md:hidden') ?>
</div>
</div>
<?php require __DIR__ . '/media-picker.php'; require __DIR__ . '/link-dialog.php'; ?>
