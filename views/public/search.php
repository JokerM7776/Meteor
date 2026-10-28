<div class="mx-auto flex max-w-[820px] flex-col gap-8 px-4 py-10 md:gap-10 md:px-6 md:py-16">
<header class="flex flex-col gap-4">
<h1 class="text-[40px] leading-tight font-bold tracking-tighter md:text-6xl"><?= e(t('search')) ?></h1>
<form method="get" action="<?= e($site->searchUrl) ?>" role="search" class="flex flex-wrap gap-2">
<label for="search-query" class="sr-only"><?= e(t('search_label')) ?></label>
<input id="search-query" type="search" name="q" value="<?= e($page->query) ?>" maxlength="<?= e($page->queryMaxLength) ?>" <?= $page->query === '' ? 'autofocus' : '' ?> aria-describedby="search-hint" class="min-h-12 min-w-0 flex-[999_1_260px] rounded-[10px] border border-slate-400 bg-white px-4 text-lg focus:outline-2 focus:outline-orange-500 dark:border-slate-600 dark:bg-slate-900">
<button class="button-primary flex-1"><?= e(t('search_button')) ?></button>
</form>
<p id="search-hint" class="text-sm text-slate-600 dark:text-slate-400"><?= e($page->description) ?></p>
</header>
<?php if ($page->query !== ''): ?>
<section aria-labelledby="results-heading" class="flex flex-col">
<h2 id="results-heading" class="eyebrow mb-2" aria-live="polite"><?= e(t('search_results', ['query' => $page->query])) ?> · <?= count($page->results) ?></h2>
<?php if (!$page->results): ?><p class="border-t border-slate-200 py-6 text-lg dark:border-slate-800"><?= e(t('search_empty', ['query' => $page->query])) ?></p><?php endif ?>
<?php foreach ($page->results as $result): ?>
<article class="flex flex-col gap-1.5 border-t border-slate-200 py-5 dark:border-slate-800">
<h3 class="text-xl font-semibold tracking-tight"><a class="accent" href="<?= e($result->url) ?>"><?= e($result->title) ?></a></h3>
<?php if ($result->published !== null): ?><p class="font-mono text-xs text-slate-600 dark:text-slate-400"><time datetime="<?= e($result->published->iso) ?>"><?= e($result->published->long) ?></time></p><?php endif ?>
<?php if ($result->html !== ''): ?><p class="search-snippet font-serif text-slate-700 dark:text-slate-300"><?= $result->html ?></p><?php endif ?>
</article>
<?php endforeach ?>
</section>
<?php endif ?>
</div>
