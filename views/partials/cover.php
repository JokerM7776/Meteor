<?php if (!empty($post['cover_image'])): ?>
<img class="h-full w-full object-cover" src="<?= e(url($post['cover_image'])) ?>" alt="<?= e($post['title']) ?>" loading="lazy">
<?php elseif ($post['slug'] === 'one-url-per-translation'): ?>
<svg class="h-full w-full bg-orange-50 dark:bg-orange-950" viewBox="0 0 400 180" preserveAspectRatio="xMidYMid slice" aria-hidden="true"><rect x="40" y="40" width="90" height="60" rx="8" fill="none" stroke="#fdba74" stroke-width="1.5"/><rect x="160" y="70" width="90" height="60" rx="8" fill="none" stroke="#f97316" stroke-width="1.5"/><rect x="280" y="40" width="90" height="60" rx="8" fill="none" stroke="#fdba74" stroke-width="1.5"/><path d="M130 70 L160 100 M250 100 L280 70" stroke="#f97316" stroke-width="1.5"/></svg>
<?php else: ?>
<svg class="h-full w-full bg-slate-900" viewBox="0 0 400 180" preserveAspectRatio="xMidYMid slice" aria-hidden="true"><path d="M380 10 L150 160" stroke="#f97316" stroke-width="2.5" stroke-linecap="round"/><path d="M360 40 L230 125" stroke="#fdba74" stroke-width="1.5" opacity=".6"/><circle cx="147" cy="162" r="6" fill="#f97316"/><g fill="#94a3b8"><circle cx="60" cy="40" r="1.5"/><circle cx="300" cy="140" r="1.5"/><circle cx="220" cy="30" r="1"/></g></svg>
<?php endif ?>
