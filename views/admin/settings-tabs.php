<nav aria-label="Settings sections" class="flex flex-wrap gap-2 border-b border-slate-200 dark:border-slate-800">
<?php foreach (['settings' => 'Site', 'strings' => 'Interface text'] as $key => $label): ?>
<a href="/admin/<?= e($key) ?>" class="-mb-px inline-flex min-h-11 items-center border-b-2 px-3 text-sm font-semibold <?= $active === $key ? 'border-orange-600 text-slate-900 dark:text-white' : 'border-transparent text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' ?>" <?= $active === $key ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
<?php endforeach ?>
</nav>
