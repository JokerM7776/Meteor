<?php // One <th> per language. Expects $code, $language and optionally $missing[$code]. ?>
<th scope="col" class="sheet-lang">
<span lang="<?= e($code) ?>" class="text-sm text-slate-900 dark:text-slate-100"><?= e($language['native_name']) ?></span>
<span class="block font-mono font-normal"><?= e($code) ?> · <?= e($language['status']) ?></span>
<?php if (!empty($missing[$code])): ?><span class="block font-normal text-amber-800 dark:text-amber-300"><?= e($missing[$code]) ?> missing</span><?php endif ?>
</th>
