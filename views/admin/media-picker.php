<?php // The media picker dialog (editor Image button and image fields). Include it once, outside other forms: it holds its own upload form. ?>
<dialog id="media-picker" class="admin-dialog" aria-labelledby="picker-heading">
<div class="flex items-center justify-between gap-4 border-b border-slate-200 px-5 py-3 dark:border-slate-800">
<h2 id="picker-heading" class="text-lg font-bold">Insert image</h2>
<button type="button" class="btn" data-close>Close</button>
</div>
<div role="tablist" aria-label="Image source" class="flex gap-2 border-b border-slate-200 px-5 dark:border-slate-800">
<button type="button" role="tab" id="picker-tab-library" aria-controls="picker-library" aria-selected="true" class="dialog-tab">Media library</button>
<button type="button" role="tab" id="picker-tab-upload" aria-controls="picker-upload" aria-selected="false" tabindex="-1" class="dialog-tab">Upload</button>
</div>
<div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
<section id="picker-library" role="tabpanel" aria-labelledby="picker-tab-library" class="space-y-4">
<form class="flex flex-wrap items-center gap-2" role="search" data-picker-search>
<label class="sr-only" for="picker-q">Search images</label>
<input id="picker-q" type="search" name="q" placeholder="Search names, alt text and tags" class="w-64 flex-1 sm:flex-none">
<label class="sr-only" for="picker-category">Category</label>
<select id="picker-category" name="category"><option value="">All categories</option></select>
<button class="btn">Search</button>
</form>
<p class="text-sm text-slate-600 dark:text-slate-400" role="status" data-picker-status>Loading…</p>
<ul class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6" data-picker-grid></ul>
<nav aria-label="Image pages" class="flex items-center justify-between gap-2 text-sm">
<button type="button" class="btn" data-picker-prev>← Previous</button>
<span class="font-mono text-xs text-slate-500" data-picker-page></span>
<button type="button" class="btn" data-picker-next>Next →</button>
</nav>
</section>
<section id="picker-upload" role="tabpanel" aria-labelledby="picker-tab-upload" hidden>
<form class="grid gap-4 sm:grid-cols-2" enctype="multipart/form-data" data-picker-upload>
<?= \Core\Csrf::field() ?>
<label class="field sm:col-span-2">Image<input type="file" name="media" accept="image/jpeg,image/png,image/webp,image/gif,image/avif" required>
<span class="text-sm font-normal text-slate-500">JPG, PNG, WebP, GIF or AVIF, up to <?= e(\App\Services\MediaLibrary::size(\App\Services\MediaLibrary::uploadLimit())) ?>.</span></label>
<label class="field">Category<input name="category" list="picker-categories" maxlength="191"></label>
<label class="field">Tags · comma separated<input name="tags" maxlength="1500"></label>
<label class="field sm:col-span-2">Alt text · describes the image<input name="alt" maxlength="255"></label>
<div class="flex flex-wrap items-center gap-4 sm:col-span-2">
<button class="btn-primary">Upload and select</button>
<p class="text-sm text-orange-700 dark:text-orange-400" role="status" data-upload-status></p>
</div>
</form>
<datalist id="picker-categories"></datalist>
</section>
</div>
<div class="flex flex-wrap items-end justify-end gap-3 border-t border-slate-200 px-5 py-3 dark:border-slate-800">
<img alt="" class="hidden size-11 rounded-md bg-slate-100 object-cover dark:bg-slate-800" data-picked-thumb>
<label class="field min-w-56 flex-1" data-picked-alt-field>Alt text in this post<input data-picked-alt maxlength="255" placeholder="Describe the image for screen readers"></label>
<button type="button" class="btn" data-close>Cancel</button>
<button type="button" class="btn-primary" data-picker-insert disabled>Insert image</button>
</div>
</dialog>
