<?php
// Starter home page: a heading, the latest posts and (optionally) the categories beside them.
// Uses only the Theme API: $site, $page, $theme and the helpers. See docs/THEMES.md.
$home = $page->home;
$tagline = $theme->setting('tagline') ?: $site->tagline;
$sidebar = $theme->setting('sidebar') && $page->categories;
?>
<section class="starter-hero<?= $theme->setting('hero') === 'compact' ? ' starter-hero--compact' : '' ?>">
<div class="starter-wrap">
<h1><?= e($home->heading) ?></h1>
<?php if ($tagline !== ''): ?><p class="text-slate-700 dark:text-slate-300"><?= e($tagline) ?></p><?php endif ?>
</div>
</section>
<?= slot('home_before') ?>
<div class="starter-wrap starter-grid<?= $sidebar ? ' starter-grid--sidebar' : '' ?>">
<section aria-labelledby="latest">
<h2 id="latest" class="eyebrow mb-4"><?= e(t('latest')) ?></h2>
<?php foreach ([...$page->pinned, ...$page->posts] as $post): ?>
<article class="starter-post">
<p class="font-mono text-xs text-slate-600 dark:text-slate-400"><time datetime="<?= e($post->published->iso) ?>"><?= e($post->published->long) ?></time><?php if ($post->category): ?> · <a class="starter-accent" href="<?= e($post->category->url) ?>"><?= e($post->category->name) ?></a><?php endif ?></p>
<h2><a href="<?= e($post->url) ?>"><?= e($post->title) ?></a></h2>
<?php if ($post->summary !== ''): ?><p class="mt-1 font-serif text-slate-700 dark:text-slate-300"><?= e($post->summary) ?></p><?php endif ?>
<?= partial('read-link', ['post' => $post, 'button' => false]) ?>
</article>
<?php endforeach ?>
<?php if (!$page->pinned && !$page->posts): ?><p><?= e(t('empty_posts')) ?></p><?php endif ?>
<?php if ($home->hasPosts): ?><p class="mt-6"><a class="starter-accent font-semibold" href="<?= e($site->postsUrl) ?>"><?= e(t('view_all_posts')) ?> →</a></p><?php endif ?>
</section>
<?php if ($sidebar): ?>
<aside aria-labelledby="categories" class="starter-card starter-rule self-start">
<h2 id="categories" class="eyebrow mb-3"><?= e(t('browse_categories')) ?></h2>
<ul>
<?php foreach ($page->categories as $term): ?><li><a class="flex min-h-11 items-center justify-between gap-3" href="<?= e($term->url) ?>"><?= e($term->name) ?><span class="font-mono text-xs text-slate-500"><?= e($term->count) ?></span></a></li><?php endforeach ?>
</ul>
</aside>
<?php endif ?>
</div>
<?= slot('home_after') ?>
