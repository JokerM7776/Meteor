<?php $iconPath = match ($key) {
    'posts' => 'M4 2.75h7l3 3v9.5H4z M11 2.75v3h3 M6.5 9h5 M6.5 12h5',
    'pages' => 'M3 3h12v12H3z M3 7h12',
    'media' => 'M2.75 3.25h12.5v11.5H2.75z M2.75 12.25l3.75-3.75 3 3 2-2 3.75 3.75',
    'taxonomy' => 'M2.75 9.5V3h6.5l6 6-6.5 6.5z M5 6h2',
    'languages' => 'M1.75 9h14.5 M9 1.75c2 2.2 2.9 4.6 2.9 7.25S11 14.05 9 16.25 M9 1.75C7 3.95 6.1 6.35 6.1 9S7 14.05 9 16.25',
    'menus' => 'M2.75 3.25h12.5v11.5H2.75z M2.75 6.25h12.5 M2.75 11.75h12.5 M5 9h8',
    'announcements' => 'M2.75 7h2.75l7-3.75v11.5l-7-3.75H2.75z M5.5 11l1.25 4 M15.25 7.25v3.5',
    'settings' => 'M9 1.75v2.5 M9 13.75v2.5 M1.75 9h2.5 M13.75 9h2.5 M3.9 3.9l1.75 1.75 M12.35 12.35l1.75 1.75 M3.9 14.1l1.75-1.75 M12.35 5.65l1.75-1.75',
    'updates' => 'M14.75 9a5.75 5.75 0 1 1-1.7-4.1 M14.75 2.75v3.5h-3.5 M9 6v3.25l2 1.25',
    default => 'M9 1.75l5.75 2.25v4.5c0 3.6-2.4 6.4-5.75 7.75C5.65 14.9 3.25 12.1 3.25 8.5V4z M6.5 9l1.75 1.75L11.75 7',
}; ?>
<svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true" class="shrink-0">
<path d="<?= e($iconPath) ?>" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linejoin="round" stroke-linecap="round"/>
<?php if ($key === 'languages'): ?>
<circle cx="9" cy="9" r="7.25" fill="none" stroke="currentColor" stroke-width="1.5"/>
<?php elseif ($key === 'media'): ?>
<circle cx="11.75" cy="6.5" r="1.25" fill="none" stroke="currentColor" stroke-width="1.5"/>
<?php elseif ($key === 'settings'): ?>
<circle cx="9" cy="9" r="2.5" fill="none" stroke="currentColor" stroke-width="1.5"/>
<?php endif ?>
</svg>
