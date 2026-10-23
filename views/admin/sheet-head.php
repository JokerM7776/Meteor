<?php // One <th> per language. Expects $code, $language and optionally $missing[$code]. ?>
<th scope="col" class="sheet-lang">
<span lang="<?= e($code) ?>" class="block text-sm text-slate-900 dark:text-slate-100"><?= e($language['native_name']) ?></span>
<span class="mt-1 flex items-center gap-1.5 font-mono font-normal">
<span class="status-dot <?= $language['status'] === 'live' ? 'bg-slate-900 dark:bg-slate-100' : ($language['status'] === 'preview' ? 'bg-amber-400' : 'border border-dashed border-slate-400') ?>" aria-hidden="true"></span>
<?= e($code) ?> · <?= e($language['status']) ?>
</span>
<?php if (!empty($missing[$code])): ?><span class="mt-0.5 block font-normal text-amber-800 dark:text-amber-300"><?= e($missing[$code]) ?> missing</span><?php endif ?>
</th>
