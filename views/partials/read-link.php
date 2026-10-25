<?php
// A post's Read link and the other languages it is published in. Expects $post, $postUrl and
// $readButton (true: a button, for cards; false: a text link, for list rows).
$others = array_values(array_filter($post['translations'], fn ($t) => $t['code'] !== $locale));
?>
<div class="flex flex-wrap items-center gap-x-4 gap-y-1">
<a class="<?= $readButton ? 'button-secondary gap-1.5' : 'accent inline-flex min-h-11 items-center gap-1.5 font-semibold' ?>" href="<?= e($postUrl($post)) ?>"><?= e(__('read')) ?><span class="sr-only">: <?= e($post['title']) ?></span><span aria-hidden="true">→</span></a>
<?php if ($others): ?><p class="text-sm text-slate-600 dark:text-slate-400"><?= strtr(e(__('also_available')), ['{languages}' => language_list($others)]) ?></p><?php endif ?>
</div>
