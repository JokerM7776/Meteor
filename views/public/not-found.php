<section class="relative overflow-hidden bg-slate-900 text-slate-100">
<svg class="absolute -end-30 -top-15 max-w-[90%]" width="900" height="600" viewBox="0 0 900 600" aria-hidden="true"><path d="M880 20 L380 470" stroke="#f97316" stroke-width="3" stroke-linecap="round"/><path d="M830 30 L520 309" stroke="#fdba74" stroke-width="2" opacity=".5"/><path d="M895 140 L620 388" stroke="#fed7aa" stroke-width="1.5" opacity=".3"/><circle cx="376" cy="474" r="12" fill="#f97316"/><g fill="#475569"><circle cx="120" cy="120" r="2"/><circle cx="640" cy="520" r="2"/><circle cx="260" cy="300" r="1.5"/></g></svg>
<div class="relative mx-auto flex min-h-[650px] max-w-[1120px] flex-col justify-center gap-6 px-4 py-20 md:px-6">
<p class="font-mono text-sm text-slate-400">404 · <?= e(__('not_found')) ?> · <?= e($requestedPath) ?></p>
<h1 class="max-w-[11ch] text-[56px] leading-[.95] font-bold tracking-[-.045em] md:text-[112px]"><?= e(__('not_found_heading')) ?></h1>
<p class="max-w-[48ch] font-serif text-[21px] leading-relaxed text-slate-300"><?= e(__('not_found_description', ['language' => $registry[$locale]['name']])) ?></p>
<div class="flex flex-wrap gap-3 pt-2"><a class="inline-flex min-h-12 items-center rounded-[10px] bg-orange-500 px-5 font-bold text-slate-900" href="<?= e('/' . $locale . '/') ?>"><?= e(__('back_home')) ?></a><a class="inline-flex min-h-12 items-center rounded-[10px] border border-slate-600 px-5 font-semibold" href="<?= e('/' . $locale . '/posts') ?>"><?= e(__('browse_archive')) ?></a></div>
</div></section>
