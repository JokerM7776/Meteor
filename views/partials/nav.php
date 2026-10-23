<nav aria-label="<?= e(__('site')) ?>" class="flex flex-wrap gap-1 text-[15px] font-medium">
<?php foreach ($headerLinks as $link): ?>
<a class="inline-flex min-h-11 items-center rounded-lg px-3 hover:bg-slate-100 dark:hover:bg-slate-800" href="<?= e($link['url']) ?>"<?= $link['external'] ? ' rel="noopener"' : '' ?><?= $link['current'] ? ' aria-current="page"' : '' ?>><?= e($link['label']) ?></a>
<?php endforeach ?>
</nav>
