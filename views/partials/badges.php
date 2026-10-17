<div class="flex flex-wrap items-center gap-1 font-mono text-xs">
<?php foreach ($post['translations'] as $translation): ?><a class="inline-flex min-h-11 items-center rounded-md px-2 <?= $translation['code'] === $locale ? 'bg-slate-900 text-white dark:bg-slate-200 dark:text-slate-900' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' ?>" href="<?= e('/' . $translation['code'] . '/posts/' . rawurlencode($post['slug'])) ?>" lang="<?= e($translation['code']) ?>"><?= e($translation['native_name']) ?></a><?php endforeach ?>
</div>
