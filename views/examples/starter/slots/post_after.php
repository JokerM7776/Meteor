<?php // Shown after every post body. ?>
<?php if ($page->type === 'post' && $site->feedUrl !== ''): ?><p class="starter-note text-sm"><?= e(t('starter_more_posts')) ?>: <a class="starter-accent font-semibold" href="<?= e($site->feedUrl) ?>"><?= e(absolute_url($site->feedUrl)) ?></a></p><?php endif ?>
