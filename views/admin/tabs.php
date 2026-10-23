<?php /** Tab bar of links. Needs $tabs (href => label), $tabCurrent (href) and $tabsLabel; $tabCounts (href => n) is optional. */ ?>
<nav aria-label="<?= e($tabsLabel) ?>" class="flex flex-wrap gap-x-2 border-b border-slate-200 dark:border-slate-800">
<?php foreach ($tabs as $href => $label): ?>
<a href="<?= e($href) ?>" class="-mb-px inline-flex min-h-11 items-center border-b-2 px-3 text-sm font-semibold <?= $tabCurrent === $href ? 'border-orange-600 text-slate-900 dark:text-white' : 'border-transparent text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' ?>" <?= $tabCurrent === $href ? 'aria-current="page"' : '' ?>><?= e($label) ?><?php if (isset($tabCounts[$href])): ?><span class="ml-1.5 font-mono text-xs font-normal text-slate-500"><?= e($tabCounts[$href]) ?></span><?php endif ?></a>
<?php endforeach ?>
</nav>
