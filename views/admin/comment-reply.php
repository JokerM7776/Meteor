<div class="max-w-3xl space-y-6">
<a class="inline-flex min-h-11 items-center text-sm text-orange-700 dark:text-orange-400" href="/admin/comments">← All comments</a>
<h1 class="text-[32px] font-bold tracking-tight">Reply to <?= e($comment['author_name'] !== '' ? $comment['author_name'] : 'webmention') ?></h1>
<section class="panel space-y-2">
<p class="text-sm text-slate-600 dark:text-slate-400">On <a class="underline" href="<?= e('/' . $comment['locale'] . '/posts/' . rawurlencode((string) ($post['slug'] ?? '')) . '#comment-' . $comment['id']) ?>" target="_blank" rel="noopener"><?= e($post['title'] ?? $post['slug'] ?? '') ?></a> · <span lang="<?= e($comment['locale']) ?>"><?= e($comment['locale']) ?></span> · <?= e(ucfirst($comment['status'])) ?></p>
<div class="comment-body" lang="<?= e($comment['locale']) ?>"><?= \App\Repositories\Comments::html($comment['body']) ?></div>
</section>
<form method="post" action="<?= e('/admin/comments/' . $comment['id'] . '/reply') ?>" class="space-y-4">
<?= \Core\Csrf::field() ?>
<?php if ($error !== ''): ?><p role="alert" class="rounded-lg border border-red-200 bg-red-50 p-3 text-red-800"><?= e($error) ?></p><?php endif ?>
<label class="field">Your reply · plain text, shown with an “Author” badge<textarea name="body" rows="6" required maxlength="5000" lang="<?= e($comment['locale']) ?>"><?= e($body) ?></textarea></label>
<p class="text-sm text-slate-600 dark:text-slate-400"><?= $comment['status'] === 'pending' ? 'Sending the reply also approves this comment.' : '' ?></p>
<button class="btn-primary">Publish reply</button>
</form>
</div>
