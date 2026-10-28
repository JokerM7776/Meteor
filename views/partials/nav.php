<?php $stacked = !empty($stacked); // The phone menu lists one link per line. ?>
<nav aria-label="<?= e(t('site')) ?>" class="<?= $stacked ? 'flex flex-col' : 'flex flex-wrap gap-1' ?> text-[15px] font-medium">
<?php foreach ($site->header->menu as $link): ?>
<a class="<?= $stacked ? 'flex min-h-11 items-center rounded-lg px-3 aria-[current=page]:font-semibold' : 'inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg px-3' ?> hover:bg-slate-100 dark:hover:bg-slate-800" href="<?= e($link->url) ?>"<?= $link->newTab ? ' target="_blank" rel="noopener"' : ($link->external ? ' rel="noopener"' : '') ?><?= $link->current ? ' aria-current="page"' : '' ?>><?= e($link->label) ?><?php if ($link->newTab) { echo partial('new-tab', ['icon' => $site->header->newTabIcon]); } ?></a>
<?php endforeach ?>
</nav>
