<nav aria-label="<?= e(__('site')) ?>" class="flex flex-wrap gap-1 text-[15px] font-medium">
<?php foreach (['posts' => 'posts', 'tags' => 'tags', 'about' => 'about', 'rss' => 'rss.xml'] as $key => $path): ?>
<?php if ($key === 'rss' && $preview) { continue; } ?>
<a class="inline-flex min-h-11 items-center rounded-lg px-3 hover:bg-slate-100 dark:hover:bg-slate-800" href="<?= e('/' . $locale . '/' . $path) ?>" <?= $active === $key ? 'aria-current="page"' : '' ?>><?= e($settings->get('nav_' . $key, $locale, __($key))) ?></a>
<?php endforeach ?>
</nav>
