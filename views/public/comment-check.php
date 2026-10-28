<?php $check = $page->commentCheck; ?>
<div class="mx-auto flex max-w-[640px] flex-col gap-6 px-4 py-10 md:px-6 md:py-16">
<h1 class="text-[32px] leading-tight font-bold tracking-tight md:text-5xl"><?= e(t('comment_check_title')) ?></h1>
<p class="font-serif text-lg text-slate-700 dark:text-slate-300"><?= e(t('comment_check_text')) ?></p>
<?php if ($check->error !== ''): ?><p role="alert" class="rounded-lg border border-red-200 bg-red-50 p-3 text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200"><?= e(t($check->error)) ?></p><?php endif ?>
<form method="post" action="<?= e($check->action) ?>" class="flex flex-col gap-5 rounded-2xl border border-slate-200 bg-white p-5 md:p-7 dark:border-slate-800 dark:bg-slate-900">
<?php foreach (['name', 'email', 'url', 'body', 'token', 'parent_id'] as $key): ?><input type="hidden" name="<?= e($key) ?>" value="<?= e($check->values[$key]) ?>"><?php endforeach ?>
<blockquote class="border-s-4 border-slate-200 ps-4 text-slate-700 dark:border-slate-700 dark:text-slate-300"><p class="mb-1 font-semibold"><?= e($check->values['name']) ?></p><div class="comment-body"><?= $check->html ?></div></blockquote>
<div class="<?= e($check->widget) ?>" data-sitekey="<?= e($check->siteKey) ?>"></div>
<noscript><p class="rounded-lg bg-amber-100 p-3 text-amber-950"><?= e(t('comment_check_noscript')) ?></p></noscript>
<div class="flex flex-wrap items-center gap-4"><button class="button-primary"><?= e(t('comment_submit')) ?></button><a class="inline-flex min-h-11 items-center accent" href="<?= e($check->postUrl . '#comments') ?>"><?= e(t('back_to_post')) ?></a></div>
</form>
<script src="<?= e($check->script) ?>" async defer></script>
</div>
