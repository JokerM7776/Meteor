<?php $stacked = !empty($navStacked); // The phone menu lists one link per line. ?>
<nav aria-label="<?= e(__('site')) ?>" class="<?= $stacked ? 'flex flex-col' : 'flex flex-wrap gap-1' ?> text-[15px] font-medium">
<?php $newTabIcon = $settings->enabled('header_new_tab_icon'); foreach ($headerLinks as $link): ?>
<a class="<?= $stacked ? 'flex min-h-11 items-center rounded-lg px-3 aria-[current=page]:font-semibold' : 'inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg px-3' ?> hover:bg-slate-100 dark:hover:bg-slate-800" href="<?= e($link['url']) ?>"<?= $link['new_tab'] ? ' target="_blank" rel="noopener"' : ($link['external'] ? ' rel="noopener"' : '') ?><?= $link['current'] ? ' aria-current="page"' : '' ?>><?= e($link['label']) ?><?php if ($link['new_tab']) { require __DIR__ . '/new-tab.php'; } ?></a>
<?php endforeach ?>
</nav>
