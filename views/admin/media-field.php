<?php
// An image chosen from the media library, stored as its URL in a hidden field.
// Expects $fieldName, $fieldValue, $fieldLabel, $fieldHint, $pickerTitle, $pickerAction, $emptyText.
// The page must also include media-picker.php once, outside its forms.
$fieldId = 'media-' . preg_replace('/[^a-z0-9]+/i', '-', $fieldName);
?>
<div class="field" data-media-field data-picker-title="<?= e($pickerTitle) ?>" data-picker-action="<?= e($pickerAction) ?>">
<span id="<?= e($fieldId) ?>-label"><?= e($fieldLabel) ?></span>
<div class="flex flex-wrap items-center gap-3">
<img data-media-preview src="<?= e($fieldValue) ?>" alt="" class="size-16 rounded-[10px] border border-slate-200 bg-slate-100 object-cover dark:border-slate-700 dark:bg-slate-800" <?= $fieldValue === '' ? 'hidden' : '' ?>>
<span data-media-empty class="flex size-16 items-center justify-center rounded-[10px] border border-dashed border-slate-400 px-1 text-center text-xs font-normal text-slate-500 dark:text-slate-400" <?= $fieldValue !== '' ? 'hidden' : '' ?>><?= e($emptyText) ?></span>
<input type="hidden" name="<?= e($fieldName) ?>" value="<?= e($fieldValue) ?>" data-media-value>
<button type="button" class="btn" data-media-choose aria-describedby="<?= e($fieldId) ?>-label">Choose from library…</button>
<button type="button" class="btn text-red-700 dark:text-red-400" data-media-clear <?= $fieldValue === '' ? 'hidden' : '' ?>>Remove<span class="sr-only"> <?= e(mb_strtolower($fieldLabel)) ?></span></button>
</div>
<span class="text-sm font-normal text-slate-500 dark:text-slate-400"><?= e($fieldHint) ?></span>
</div>
