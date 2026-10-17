<nav aria-label="<?= e(__('site')) ?>" class="flex flex-wrap gap-1 text-[15px] font-medium">
<?php foreach (['posts' => 'posts', 'tags' => 'tags', 'about' => 'about', 'rss' => 'rss.xml'] as $key => $path): ?>
<?php if ($key === 'about' && in_array('about', array_column($navPages ?? [], 'slug'), true)) { continue; } ?>
<?php if ($key === 'rss' && $preview) { continue; } ?>
<a class="inline-flex min-h-11 items-center rounded-lg px-3 hover:bg-slate-100 dark:hover:bg-slate-800" href="<?= e('/' . $locale . '/' . $path) ?>" <?= $active === $key ? 'aria-current="page"' : '' ?>><?= e($settings->get('nav_' . $key, $locale, __($key))) ?></a>
<?php endforeach ?>
<?php foreach ($navPages ?? [] as $navPage): ?>
<a class="inline-flex min-h-11 items-center rounded-lg px-3 hover:bg-slate-100 dark:hover:bg-slate-800" href="<?= e('/' . $locale . '/' . rawurlencode($navPage['slug'])) ?>" <?= isset($item) && !empty($isPage) && $item['id'] === $navPage['id'] ? 'aria-current="page"' : '' ?>><?= e($navPage['nav_label'] ?: $navPage['title']) ?></a>
<?php endforeach ?>
</nav>
