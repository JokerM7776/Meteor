<?php
use App\Services\MediaLibrary;
use Core\ImageOptimizer;
$value = fn (string $key, mixed $saved) => $old[$key] ?? $saved;
$enabled = (string) $value('media_optimize', $options['optimize'] ? '1' : '0') === '1';
$format = (string) $value('media_format', $options['format']);
$size = (int) $value('media_max_size', $options['max_size']);
$status = fn (bool $ok) => $ok ? '<span class="font-semibold text-green-800 dark:text-green-400">available</span>' : '<span class="font-semibold text-red-700 dark:text-red-400">missing</span>';
?>
<div class="space-y-6">
<header class="max-w-3xl">
<h1 class="text-[32px] font-bold tracking-tight">Settings</h1>
<p class="text-slate-600 dark:text-slate-400">How uploaded images are prepared for the web. Smaller files load faster for your readers.</p>
</header>
<?php require __DIR__ . '/settings-tabs.php'; ?>
<?php if (!ImageOptimizer::available('webp')): ?>
<p role="alert" class="rounded-[10px] border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">This server’s PHP has no GD extension with WebP support, so images are stored as uploaded. Ask your host to enable GD.</p>
<?php endif ?>
<form method="post" action="/admin/optimization" class="panel space-y-5" aria-labelledby="optimize-heading">
<?= \Core\Csrf::field() ?>
<h2 id="optimize-heading" class="text-xl font-bold">Image optimization · all uploads</h2>
<input type="hidden" name="media_optimize" value="0">
<label class="flex min-h-11 items-center gap-3 font-semibold"><input type="checkbox" name="media_optimize" value="1" <?= $enabled ? 'checked' : '' ?>>Optimize uploaded images</label>
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
<label class="field">Format<select name="media_format">
<option value="webp" <?= $format === 'webp' ? 'selected' : '' ?>>WebP · recommended, works everywhere</option>
<option value="avif" <?= $format === 'avif' ? 'selected' : '' ?> <?= ImageOptimizer::available('avif') ? '' : 'disabled' ?>>AVIF · smaller files, slower to create<?= ImageOptimizer::available('avif') ? '' : ' (not available)' ?></option>
</select></label>
<label class="field">Quality · 40 to 95<input type="number" name="media_quality" min="40" max="95" step="1" required value="<?= e($value('media_quality', $options['quality'])) ?>"></label>
<label class="field">Longest side<select name="media_max_size">
<?php foreach (MediaLibrary::SIZES as $px => $label): ?><option value="<?= e($px) ?>" <?= $px === $size ? 'selected' : '' ?>><?= e($px ? 'At most ' . $label : $label) ?></option><?php endforeach ?>
</select></label>
</div>
<ul class="list-disc space-y-1 ps-5 text-sm text-slate-600 dark:text-slate-400">
<li>Images are converted to the chosen format, turned the right way up, and shrunk when larger than the longest side.</li>
<li>Camera details such as GPS location are removed. With optimization off, files are stored exactly as uploaded, details included.</li>
<li>Animated GIFs are kept as they are. Images already in the library are not changed.</li>
</ul>
<button class="btn-primary">Save optimization</button>
</form>
<section class="panel space-y-3" aria-labelledby="server-heading">
<h2 id="server-heading" class="text-xl font-bold">This server</h2>
<dl class="grid gap-x-8 gap-y-2 text-sm sm:grid-cols-2 xl:grid-cols-4">
<div><dt class="text-slate-500 dark:text-slate-400">WebP</dt><dd><?= $status(ImageOptimizer::available('webp')) ?></dd></div>
<div><dt class="text-slate-500 dark:text-slate-400">AVIF</dt><dd><?= $status(ImageOptimizer::available('avif')) ?></dd></div>
<div><dt class="text-slate-500 dark:text-slate-400">Largest upload</dt><dd class="font-mono"><?= e(MediaLibrary::size(MediaLibrary::uploadLimit())) ?> <span class="font-display text-slate-500 dark:text-slate-400">· PHP upload_max_filesize <?= e(ini_get('upload_max_filesize')) ?></span></dd></div>
<div><dt class="text-slate-500 dark:text-slate-400">Images in the library</dt><dd class="font-mono"><a class="text-orange-700 underline dark:text-orange-400" href="/admin/media"><?= e($mediaCount) ?></a></dd></div>
</dl>
</section>
</div>
