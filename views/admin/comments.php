<?php
$tabs = ['/admin/comments' => 'Pending', '/admin/comments?status=approved' => 'Approved', '/admin/comments?status=spam' => 'Spam', '/admin/comments?status=all' => 'All', '/admin/comments?tab=settings' => 'Settings'];
$tabCurrent = $tab === 'settings' ? '/admin/comments?tab=settings' : ($tab === 'pending' ? '/admin/comments' : '/admin/comments?status=' . $tab);
$tabsLabel = 'Comment sections';
$tabCounts = $tab === 'settings' ? [] : ['/admin/comments' => (int) ($counts['pending'] ?? 0), '/admin/comments?status=approved' => (int) ($counts['approved'] ?? 0), '/admin/comments?status=spam' => (int) ($counts['spam'] ?? 0), '/admin/comments?status=all' => (int) $counts['all']];
$chip = ['pending' => 'bg-amber-100 text-amber-950', 'approved' => 'bg-slate-900 text-white dark:bg-slate-700', 'spam' => 'border border-dashed border-slate-500 text-slate-700 dark:text-slate-300'];
?>
<div class="space-y-6">
<header class="max-w-3xl">
<h1 class="text-[32px] font-bold tracking-tight">Comments</h1>
<p class="text-slate-600 dark:text-slate-400">Comments and webmentions on posts. Each language of a post has its own discussion.</p>
</header>
<?php require __DIR__ . '/tabs.php'; ?>
<?php if ($tab !== 'settings'): ?>
<?php if (!$enabled): ?><p class="rounded-[10px] border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">Comments are off, so visitors cannot send new ones. <a class="font-semibold underline" href="/admin/comments?tab=settings">Turn them on in Settings</a>.</p><?php endif ?>
<?php if (!$rows): ?>
<section class="panel"><p class="text-slate-600 dark:text-slate-400"><?= $tab === 'pending' ? 'Nothing waits for approval.' : 'No comments here.' ?></p></section>
<?php else: ?>
<form method="post" action="<?= e('/admin/comments?' . http_build_query(['status' => $tab, 'page' => $page])) ?>" class="space-y-4">
<?= \Core\Csrf::field() ?>
<div class="flex flex-wrap items-center gap-2">
<label for="bulk-action" class="text-sm font-semibold">With selected</label>
<select id="bulk-action" name="action"><option value="approve">Approve</option><option value="pending">Move to pending</option><option value="spam">Mark as spam</option><option value="delete">Delete permanently</option></select>
<button class="btn">Apply</button>
</div>
<div class="sheet">
<table>
<caption class="sr-only">Comments, newest first</caption>
<thead><tr><th scope="col" class="w-px"><span class="sr-only">Select</span></th><th scope="col">Author</th><th scope="col" class="min-w-80">Comment</th><th scope="col">Post</th><th scope="col">Status</th><th scope="col">Date (UTC)</th><th scope="col" class="sheet-end">Actions</th></tr></thead>
<tbody>
<?php foreach ($rows as $row): $postUrl = '/' . $row['locale'] . '/posts/' . rawurlencode((string) $row['post_slug']); $name = $row['author_name'] !== '' ? $row['author_name'] : (string) parse_url($row['source_url'], PHP_URL_HOST); ?>
<tr>
<td><input type="checkbox" name="ids[]" value="<?= e($row['id']) ?>" aria-label="Select comment by <?= e($name) ?>"></td>
<td class="max-w-56">
<span class="block font-semibold"><?= e($name) ?><?php if ($row['is_owner']): ?> <span class="font-mono text-xs font-normal text-orange-800 dark:text-orange-300">you</span><?php endif ?></span>
<?php if ($row['author_email'] !== ''): ?><span class="block truncate text-xs text-slate-500 dark:text-slate-400"><?= e($row['author_email']) ?></span><?php endif ?>
<?php if ($row['author_url'] !== ''): ?><a class="block truncate text-xs text-orange-700 underline dark:text-orange-400" href="<?= e($row['author_url']) ?>" rel="noopener noreferrer" target="_blank"><?= e(preg_replace('~^https?://~', '', $row['author_url'])) ?></a><?php endif ?>
</td>
<td>
<?php if ($row['kind'] === 'webmention'): ?><span class="mb-1 inline-block rounded-md bg-slate-100 px-2 py-0.5 font-mono text-xs dark:bg-slate-800">Webmention · <?= e($row['mention_type']) ?></span> <a class="text-xs text-orange-700 underline dark:text-orange-400" href="<?= e($row['source_url']) ?>" target="_blank" rel="noopener noreferrer">source ↗</a><?php endif ?>
<?php if ($row['parent_name'] !== null): ?><span class="block text-xs text-slate-500 dark:text-slate-400">Reply to <?= e($row['parent_name']) ?></span><?php endif ?>
<p class="line-clamp-3 whitespace-pre-line"><?= e(mb_substr($row['body'], 0, 400)) ?></p>
</td>
<td class="max-w-56"><a class="text-orange-700 underline dark:text-orange-400" href="<?= e($postUrl . '#comment-' . $row['id']) ?>" target="_blank" rel="noopener"><?= e($row['post_title'] ?? $row['post_slug']) ?></a> <span class="font-mono text-xs text-slate-500 dark:text-slate-400" lang="<?= e($row['locale']) ?>"><?= e($row['locale']) ?></span></td>
<td><span class="status-chip <?= $chip[$row['status']] ?>"><?= e(ucfirst($row['status'])) ?></span></td>
<td class="whitespace-nowrap font-mono text-xs"><?= e(substr((string) $row['created_at'], 0, 16)) ?></td>
<td class="sheet-end"><div class="flex justify-end gap-1">
<?php if ($row['status'] !== 'approved'): ?><button class="btn" name="row" value="approve:<?= e($row['id']) ?>">Approve</button><?php endif ?>
<?php if (!\App\Repositories\Comments::isReaction($row)): ?><a class="btn" href="/admin/comments/<?= e($row['id']) ?>/reply">Reply</a><?php endif ?>
<?php if ($row['status'] !== 'spam'): ?><button class="btn" name="row" value="spam:<?= e($row['id']) ?>">Spam</button><?php endif ?>
<button class="btn-danger-outline" name="row" value="delete:<?= e($row['id']) ?>" aria-label="Delete comment by <?= e($name) ?>">Delete</button>
</div></td>
</tr>
<?php endforeach ?>
</tbody>
</table>
</div>
</form>
<?php $pages = (int) ceil($total / \App\Controllers\Admin\CommentsController::PER_PAGE); if ($pages > 1): ?>
<nav aria-label="Pages" class="flex flex-wrap gap-1"><?php for ($n = 1; $n <= $pages; $n++): ?><a class="btn" <?= $n === $page ? 'aria-current="page"' : '' ?> href="<?= e('/admin/comments?' . http_build_query(['status' => $tab, 'page' => $n])) ?>"><?= $n ?></a><?php endfor ?></nav>
<?php endif ?>
<?php endif ?>
<?php else: $v = $values; $check = fn (string $key) => ($v[$key] ?? '0') === '1' ? 'checked' : ''; ?>
<form method="post" action="/admin/comments/settings" class="space-y-6">
<?= \Core\Csrf::field() ?>
<section class="panel space-y-4" aria-labelledby="comments-heading">
<h2 id="comments-heading" class="text-xl font-bold">Comments</h2>
<input type="hidden" name="comments_enabled" value="0">
<label class="flex min-h-11 items-center gap-3"><input type="checkbox" name="comments_enabled" value="1" <?= $check('comments_enabled') ?>>Allow comments on posts</label>
<p class="text-sm text-slate-600 dark:text-slate-400">Each post can also turn its comments off in the editor (Shared settings → Allow comments). Pages never have comments.</p>
<fieldset class="space-y-1"><legend class="mb-1 font-semibold">New comments</legend>
<?php foreach (\App\Services\CommentSettings::MODES as $mode => $label): ?>
<label class="flex min-h-11 items-center gap-3"><input type="radio" name="comments_moderation" value="<?= e($mode) ?>" <?= ($v['comments_moderation'] ?? 'hold') === $mode ? 'checked' : '' ?>><?= e($label) ?></label>
<?php endforeach ?>
<p class="text-sm text-slate-600 dark:text-slate-400">Comments with more than two links always wait for approval.</p>
</fieldset>
</section>
<section class="panel space-y-4" aria-labelledby="captcha-heading">
<h2 id="captcha-heading" class="text-xl font-bold">CAPTCHA</h2>
<input type="hidden" name="comments_captcha" value="0">
<label class="flex min-h-11 items-center gap-3"><input type="checkbox" name="comments_captcha" value="1" <?= $check('comments_captcha') ?>>Ask commenters to pass a CAPTCHA</label>
<p class="max-w-3xl text-sm text-slate-600 dark:text-slate-400">The check opens on its own page after the visitor presses Send, so post pages stay free of JavaScript. Without a configured provider, comments are still protected by a hidden trap field, a form timer, a rate limit and moderation.</p>
<p class="text-sm <?= $captchaReady ? 'text-green-800 dark:text-green-300' : 'text-amber-800 dark:text-amber-300' ?>">Site CAPTCHA: <?= e($captchaName) ?> <?= $captchaReady ? '· keys saved ✓' : '· not set up: add its keys on' ?> <a class="underline" href="/admin/security#captcha-heading"><?= $captchaReady ? 'Change on Security' : 'Security' ?></a></p>
</section>
<section class="panel space-y-4" aria-labelledby="webmention-heading">
<h2 id="webmention-heading" class="text-xl font-bold">Webmentions</h2>
<p class="max-w-3xl text-sm text-slate-600 dark:text-slate-400"><a class="underline" href="https://www.w3.org/TR/webmention/" target="_blank" rel="noopener">Webmention ↗</a> lets other sites tell yours when they link to a post. Replies, likes, reposts and mentions follow the moderation setting above.</p>
<input type="hidden" name="webmention_receive" value="0"><input type="hidden" name="webmention_send" value="0">
<label class="flex min-h-11 items-center gap-3"><input type="checkbox" name="webmention_receive" value="1" <?= $check('webmention_receive') ?>>Accept webmentions (while comments are on)</label>
<label class="flex min-h-11 items-center gap-3"><input type="checkbox" name="webmention_send" value="1" <?= $check('webmention_send') ?>>Send webmentions to sites that published posts link to</label>
</section>
<button class="btn-primary">Save settings</button>
</form>
<section class="panel space-y-3" aria-labelledby="queue-heading">
<h2 id="queue-heading" class="text-xl font-bold">Background tasks</h2>
<p class="max-w-3xl text-sm text-slate-600 dark:text-slate-400">Webmentions are checked and sent right after the page that queued them has been delivered, when the server supports it (PHP-FPM, LiteSpeed). For retries and scheduled posts, add a cron job that runs every 10 minutes:</p>
<pre class="overflow-x-auto rounded-[10px] bg-slate-900 p-3 font-mono text-sm text-slate-100">*/10 * * * * php <?= e($root) ?>/bin/cron.php</pre>
<p class="text-sm">Waiting: <?= (int) ($queue['in'] ?? 0) ?> received, <?= (int) ($queue['out'] ?? 0) ?> to send<?= $lastRun !== '' ? ' · last run ' . e($lastRun) . ' UTC' : ' · not run yet' ?></p>
<form method="post" action="/admin/comments/process"><?= \Core\Csrf::field() ?><button class="btn">Process now</button></form>
</section>
<?php endif ?>
</div>
