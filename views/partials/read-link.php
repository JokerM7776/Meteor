<?php
// A post's Read link and the other languages it is published in. $post (Post) and
// $button (true: a button, for cards; false: a text link, for list rows).
$others = array_values(array_filter($post->translations, fn ($t) => !$t->current));
?>
<div class="flex flex-wrap items-center gap-x-4 gap-y-1">
<a class="<?= !empty($button) ? 'button-secondary gap-1.5' : 'accent inline-flex min-h-11 items-center gap-1.5 font-semibold' ?>" href="<?= e($post->url) ?>"><?= e(t('read')) ?><span class="sr-only">: <?= e($post->title) ?></span><span aria-hidden="true">→</span></a>
<?php if ($others): ?><p class="text-sm text-slate-600 dark:text-slate-400"><?= strtr(e(t('also_available')), ['{languages}' => language_names($others)]) ?></p><?php endif ?>
</div>
