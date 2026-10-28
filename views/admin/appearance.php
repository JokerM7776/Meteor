<?php
use App\Services\Appearance;
$values = Appearance::values($settings);
foreach (['theme_scheme', 'theme_neutral', 'theme_switch'] as $key) { if (isset($old[$key]) && is_string($old[$key])) { $values[$key] = $old[$key]; } }
$accent = $values['theme_accent'];
$custom = Appearance::hex($accent) ? $accent : '#0f766e';
// Literal class names so Tailwind includes them.
$swatches = ['orange' => 'bg-orange-500', 'amber' => 'bg-amber-500', 'red' => 'bg-red-500', 'rose' => 'bg-rose-500', 'pink' => 'bg-pink-500', 'fuchsia' => 'bg-fuchsia-500',
    'purple' => 'bg-purple-500', 'violet' => 'bg-violet-500', 'indigo' => 'bg-indigo-500', 'blue' => 'bg-blue-500', 'sky' => 'bg-sky-500', 'cyan' => 'bg-cyan-500',
    'teal' => 'bg-teal-500', 'emerald' => 'bg-emerald-500', 'green' => 'bg-green-500', 'lime' => 'bg-lime-500'];
$tones = ['slate' => ['bg-slate-100', 'bg-slate-500', 'bg-slate-900'], 'gray' => ['bg-gray-100', 'bg-gray-500', 'bg-gray-900'], 'zinc' => ['bg-zinc-100', 'bg-zinc-500', 'bg-zinc-900'],
    'neutral' => ['bg-neutral-100', 'bg-neutral-500', 'bg-neutral-900'], 'stone' => ['bg-stone-100', 'bg-stone-500', 'bg-stone-900']];
?>
<div class="space-y-6">
<header class="max-w-3xl">
<h1 class="text-[32px] font-bold tracking-tight">Settings</h1>
<p class="text-slate-600 dark:text-slate-400">Light and dark mode, and the colors of your public site. The admin keeps its own colors.</p>
</header>
<?php require __DIR__ . '/settings-tabs.php'; ?>
<form method="post" class="space-y-6">
<?= \Core\Csrf::field() ?>
<div class="grid gap-6 xl:grid-cols-2">
<section class="panel"><fieldset class="space-y-1">
<legend class="mb-3 text-xl font-bold">Light &amp; dark</legend>
<?php foreach (Appearance::SCHEMES as $value => $label): ?>
<label class="flex min-h-11 items-center gap-3"><input type="radio" name="theme_scheme" value="<?= e($value) ?>" class="size-5 accent-orange-700" <?= $values['theme_scheme'] === $value ? 'checked' : '' ?>><?= e($label) ?></label>
<?php endforeach ?>
<div class="mt-3 border-t border-slate-200 pt-3 dark:border-slate-800">
<input type="hidden" name="theme_switch" value="0">
<label class="flex min-h-11 items-center gap-3"><input type="checkbox" name="theme_switch" value="1" <?= $values['theme_switch'] === '1' ? 'checked' : '' ?>>Let visitors choose System, Light or Dark</label>
<p class="text-sm text-slate-600 dark:text-slate-400">Adds a small ◐ menu to the header. It works without JavaScript and remembers the choice with a cookie.</p>
</div>
</fieldset></section>
<section class="panel"><fieldset class="space-y-1">
<legend class="mb-3 text-xl font-bold">Text &amp; background tone</legend>
<?php foreach (Appearance::NEUTRALS as $value => $label): ?>
<label class="flex min-h-11 items-center gap-3"><input type="radio" name="theme_neutral" value="<?= e($value) ?>" class="size-5 accent-orange-700" <?= $values['theme_neutral'] === $value ? 'checked' : '' ?>>
<span class="flex overflow-hidden rounded-md border border-slate-300 dark:border-slate-600" aria-hidden="true"><?php foreach ($tones[$value] as $class): ?><span class="size-6 <?= $class ?>"></span><?php endforeach ?></span><?= e($label) ?></label>
<?php endforeach ?>
</fieldset></section>
</div>
<section class="panel"><fieldset class="space-y-4">
<legend class="mb-1 text-xl font-bold">Accent color</legend>
<p class="text-sm text-slate-600 dark:text-slate-400">Used for links, buttons on the error page, highlights and the default icon. Darker shades are used for text so links stay readable.</p>
<div class="flex flex-wrap gap-2">
<?php foreach ($swatches as $value => $class): ?>
<label class="relative inline-flex size-11 cursor-pointer items-center justify-center" title="<?= e(ucfirst($value)) ?>">
<input type="radio" name="theme_accent" value="<?= e($value) ?>" class="peer sr-only" <?= $accent === $value ? 'checked' : '' ?>>
<span class="size-8 rounded-full <?= $class ?> ring-offset-2 ring-offset-white peer-checked:ring-2 peer-checked:ring-slate-900 peer-focus-visible:outline-2 peer-focus-visible:outline-offset-4 peer-focus-visible:outline-orange-600 dark:ring-offset-slate-900 dark:peer-checked:ring-white" aria-hidden="true"></span>
<span class="sr-only"><?= e(ucfirst($value)) ?></span>
</label>
<?php endforeach ?>
</div>
<div class="flex flex-wrap items-center gap-3">
<label class="flex min-h-11 items-center gap-3"><input type="radio" name="theme_accent" value="custom" class="size-5 accent-orange-700" <?= Appearance::hex($accent) ? 'checked' : '' ?>>Custom color</label>
<label class="sr-only" for="theme-custom">Custom accent color</label>
<input id="theme-custom" type="color" name="theme_custom" value="<?= e($custom) ?>" class="h-11 w-16 cursor-pointer p-1" data-custom-color>
<span class="text-sm text-slate-600 dark:text-slate-400">Pick a mid-tone; very light colors are hard to read as links.</span>
</div>
</fieldset></section>
<div class="flex flex-wrap items-center gap-4">
<button class="btn-primary">Save appearance</button>
<a class="inline-flex min-h-11 items-center text-orange-700 underline dark:text-orange-400" href="/<?= e($i18n->defaultLocale()) ?>/" target="_blank" rel="noopener">View site ↗<span class="sr-only"> (opens in a new tab)</span></a>
</div>
</form>
<?php require __DIR__ . '/themes.php'; ?>
</div>
