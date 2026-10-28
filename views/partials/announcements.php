<?php
// Announcement bar above the header, from $site->announcements (at least one).
// With several, a radio button before each one decides which is shown (CSS, no JavaScript):
// readers click the arrow labels, keyboard and screen reader users move through the radio group.
$count = count($site->announcements);
$arrow = fn (string $path) => '<svg class="rtl:-scale-x-100" width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><path d="' . $path . '" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>';
?>
<section aria-label="<?= e(t('announcements')) ?>" class="announcements relative bg-slate-900 text-slate-300 dark:border-b dark:border-slate-800 dark:bg-slate-950">
<?php foreach ($site->announcements as $index => $note): $number = $index + 1; ?>
<?php if ($count > 1): ?><input type="radio" name="announcement" id="announcement-<?= e($number) ?>" class="sr-only" aria-label="<?= e(t('announcement_position', ['number' => (string) $number, 'total' => (string) $count])) ?>"<?= $index === 0 ? ' checked' : '' ?>><?php endif ?>
<div class="announcement">
<p class="w-full py-1.5 sm:w-auto sm:min-w-0 sm:flex-1"><?php if ($note->title !== ''): ?><strong class="font-semibold text-white"><?= e($note->title) ?></strong> <?php endif ?><?= nl2br(e($note->message), false) ?></p>
<?php if ($note->buttonText !== ''): ?><a class="inline-flex min-h-11 items-center rounded-lg bg-orange-500 px-4 font-semibold text-slate-950 hover:bg-orange-400 focus-visible:outline-white" href="<?= e($note->buttonUrl) ?>"<?= $note->external ? ' rel="noopener"' : '' ?>><?= e($note->buttonText) ?></a><?php endif ?>
<?php if ($count > 1): ?>
<div class="ms-auto flex items-center" aria-hidden="true">
<label for="announcement-<?= e(($index - 1 + $count) % $count + 1) ?>" title="<?= e(t('previous')) ?>" class="flex size-11 cursor-pointer items-center justify-center rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white"><?= $arrow('M10 3.5 5.5 8l4.5 4.5') ?></label>
<span class="min-w-10 text-center font-mono text-xs text-slate-400"><?= e($number) ?>/<?= e($count) ?></span>
<label for="announcement-<?= e(($index + 1) % $count + 1) ?>" title="<?= e(t('next')) ?>" class="flex size-11 cursor-pointer items-center justify-center rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white"><?= $arrow('M6 3.5 10.5 8 6 12.5') ?></label>
</div>
<?php endif ?>
</div>
<?php endforeach ?>
</section>
