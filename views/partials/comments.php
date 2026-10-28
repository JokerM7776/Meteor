<?php
// Comments, received webmentions and the zero-JS comment form below a post, from $page->comments.
$d = $page->comments;
$field = function (string $name, string $label, string $type = 'text', array $attributes = []) use ($d): void {
    $id = 'comment-' . $name; $invalid = $d->field === $name;
    $common = 'id="' . $id . '" name="' . $name . '"' . ($invalid ? ' aria-invalid="true" aria-describedby="comment-error"' : '');
    foreach ($attributes as $key => $value) { $common .= ' ' . $key . ($value === true ? '' : '="' . e($value) . '"'); } ?>
<div class="flex flex-col gap-1.5<?= $type === 'textarea' ? ' sm:col-span-3' : '' ?>"><label for="<?= e($id) ?>" class="text-sm font-semibold"><?= e($label) ?></label>
<?php if ($type === 'textarea'): ?><textarea <?= $common ?> rows="5" class="comment-input"><?= e($d->values[$name]) ?></textarea>
<?php else: ?><input <?= $common ?> type="<?= e($type) ?>" value="<?= e($d->values[$name]) ?>" class="comment-input"><?php endif ?></div>
<?php };
$person = function (\App\Theme\Comment $row): void { ?>
<header class="flex flex-wrap items-center gap-x-3 gap-y-1">
<span aria-hidden="true" class="inline-flex size-9 shrink-0 items-center justify-center rounded-full <?= $row->owner ? 'bg-orange-700 text-white' : 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300' ?> font-semibold"><?= e(mb_strtoupper(mb_substr($row->name, 0, 1))) ?></span>
<span class="p-author h-card font-semibold"><?php if ($row->url !== ''): ?><a class="u-url p-name accent" href="<?= e($row->url) ?>" rel="nofollow ugc noopener"><?= e($row->name) ?></a><?php else: ?><span class="p-name"><?= e($row->name) ?></span><?php endif ?></span>
<?php if ($row->owner): ?><span class="rounded-md bg-orange-100 px-2 py-0.5 font-mono text-xs text-orange-900 dark:bg-orange-950 dark:text-orange-200"><?= e(t('comment_owner')) ?></span><?php endif ?>
<a class="inline-flex min-h-11 items-center font-mono text-xs text-slate-600 dark:text-slate-400" href="#comment-<?= e($row->id) ?>"><time class="dt-published" datetime="<?= e($row->created->iso) ?>"><?= e($row->created->long . ', ' . $row->created->time) ?></time></a>
<?php if ($row->kind === 'webmention' && $row->sourceUrl !== ''): ?><a class="u-url inline-flex min-h-11 items-center text-xs text-slate-600 underline dark:text-slate-400" href="<?= e($row->sourceUrl) ?>" rel="nofollow ugc noopener"><?= e(t('comment_via', ['site' => (string) parse_url($row->sourceUrl, PHP_URL_HOST)])) ?></a><?php endif ?>
</header>
<?php };
?>
<section id="comments" aria-labelledby="comments-heading" class="flex max-w-[700px] flex-col gap-6 border-t border-slate-200 pt-6 dark:border-slate-800">
<h2 id="comments-heading" class="eyebrow"><?= e(t('comments')) ?><?= $d->count ? ' · ' . e($d->count) : '' ?></h2>
<?php if ($d->notice !== ''): ?><p role="status" class="rounded-xl border border-green-200 bg-green-50 p-4 text-green-900 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200"><?= e(t('comment_' . $d->notice)) ?></p><?php endif ?>
<?php if ($d->threads): ?>
<ol class="flex flex-col gap-6">
<?php foreach ($d->threads as $thread): ?>
<li>
<article id="comment-<?= e($thread->id) ?>" class="p-comment h-cite flex scroll-mt-6 flex-col gap-2">
<?php $person($thread) ?>
<div class="e-content comment-body ps-12"><?= $thread->html ?></div>
<?php if ($d->open): ?><a class="ms-12 inline-flex min-h-11 items-center self-start text-sm font-semibold accent" href="<?= e($d->url . '?reply=' . $thread->id . '#comment-form') ?>"><?= e(t('comment_reply')) ?><span class="sr-only">: <?= e($thread->name) ?></span></a><?php endif ?>
</article>
<?php if ($thread->replies): ?>
<ol class="ms-4 mt-4 flex flex-col gap-5 border-s-2 border-slate-200 ps-4 md:ms-12 dark:border-slate-800">
<?php foreach ($thread->replies as $reply): ?>
<li><article id="comment-<?= e($reply->id) ?>" class="p-comment h-cite flex scroll-mt-6 flex-col gap-2"><?php $person($reply) ?><div class="e-content comment-body ps-12"><?= $reply->html ?></div></article></li>
<?php endforeach ?>
</ol>
<?php endif ?>
</li>
<?php endforeach ?>
</ol>
<?php endif ?>
<?php if ($d->reactions): ?>
<div><h3 class="eyebrow mb-2"><?= e(t('mentions')) ?></h3>
<ul class="flex flex-col text-sm">
<?php foreach ($d->reactions as $reaction): ?>
<li id="comment-<?= e($reaction->id) ?>"><a class="inline-flex min-h-11 items-center gap-2 hover:underline" href="<?= e($reaction->sourceUrl) ?>" rel="nofollow ugc noopener"><?= e(t('mention_' . $reaction->mentionType, ['name' => $reaction->name])) ?></a></li>
<?php endforeach ?>
</ul></div>
<?php endif ?>
<?php if (!$d->open): ?><p class="text-slate-600 dark:text-slate-400"><?= e(t('comments_closed')) ?></p>
<?php else: ?>
<form id="comment-form" method="post" action="<?= e($d->action) ?>" class="flex scroll-mt-6 flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 md:p-7 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="comment-form-heading">
<h3 id="comment-form-heading" class="text-xl font-bold tracking-tight"><?= e(t('comment_leave')) ?></h3>
<?php if ($d->error !== ''): ?><p id="comment-error" role="alert" class="rounded-lg border border-red-200 bg-red-50 p-3 text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200"><?= e(t($d->error)) ?></p><?php endif ?>
<?php if ($d->replyTo): ?>
<p class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-slate-100 px-3 text-sm dark:bg-slate-800"><span><?= e(t('comment_replying', ['name' => $d->replyTo->name])) ?></span><a class="inline-flex min-h-11 items-center accent" href="<?= e($d->url . '#comment-form') ?>"><?= e(t('comment_cancel_reply')) ?></a></p>
<input type="hidden" name="parent_id" value="<?= e($d->replyTo->id) ?>">
<?php endif ?>
<div class="grid gap-4 sm:grid-cols-3">
<?php $field('name', t('comment_name'), 'text', ['required' => true, 'maxlength' => 100, 'autocomplete' => 'name']);
$field('email', t('comment_email'), 'email', ['maxlength' => 191, 'autocomplete' => 'email']);
$field('url', t('comment_website'), 'url', ['maxlength' => 2048, 'autocomplete' => 'url', 'placeholder' => 'https://']);
$field('body', t('comment_text'), 'textarea', ['required' => true, 'maxlength' => $d->maxLength]); ?>
</div>
<div class="comment-trap" aria-hidden="true"><label>Leave this empty <input name="homepage" tabindex="-1" autocomplete="off"></label></div>
<input type="hidden" name="token" value="<?= e($d->values['token']) ?>">
<button class="button-primary self-start"><?= e(t('comment_submit')) ?></button>
</form>
<?php endif ?>
</section>
