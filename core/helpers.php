<?php
declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function __(string $key, array $params = []): string
{
    return $GLOBALS['i18n']->translate($key, $params);
}

function url(string $path = ''): string
{
    return '/' . ltrim($path, '/');
}

function redirect(string $path, int $status = 302): never
{
    if (!str_starts_with($path, '/') || str_starts_with($path, '//') || preg_match('/[\r\n\\\\]/', $path)) {
        throw new InvalidArgumentException('Redirects require a local absolute path.');
    }
    header('Location: ' . $path, true, $status);
    exit;
}

function slugify(string $text): string
{
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^\p{L}\p{N}\p{M}]+/u', '-', $text) ?? '';
    return trim($text, '-') ?: 'section';
}

function absolute_url(string $path): string
{
    return rtrim($GLOBALS['config']['app_url'], '/') . url($path);
}

function localized_date(string $date, string $pattern = 'dd MMM yyyy'): string
{
    $formatter = new IntlDateFormatter($GLOBALS['i18n']->locale(), IntlDateFormatter::NONE, IntlDateFormatter::NONE, 'UTC', null, $pattern);
    return $formatter->format(new DateTimeImmutable($date, new DateTimeZone('UTC')));
}

// Public asset URL with a version stamp, so browsers and CDNs fetch the new file after an update.
function asset(string $path): string
{
    $path = ltrim($path, '/');
    $mtime = @filemtime(dirname(__DIR__) . '/public/' . $path);
    return '/' . $path . ($mtime ? '?v=' . $mtime : '');
}

// Content-Security-Policy header value. Public pages pass "'none'" for scripts.
function security_policy(string $scripts, string $frames = ''): string
{
    return "default-src 'self'; script-src $scripts; " . ($frames !== '' ? "frame-src $frames; " : '')
        . "style-src 'self' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' https:; base-uri 'self'; form-action 'self'; frame-ancestors 'none'";
}

/**
 * Language names joined as HTML ("A, B and C"), each escaped and marked with its lang. Past $max
 * names, the rest become one "N more languages" item. $locales: rows with code and native_name.
 */
function language_list(array $locales, int $max = 3): string
{
    $items = array_map(fn (array $l) => '<span lang="' . e($l['code']) . '">' . e($l['native_name']) . '</span>', $locales);
    if (count($items) > $max) {
        $items = [...array_slice($items, 0, $max - 1), e(__('more_languages', ['count' => count($items) - $max + 1]))];
    }
    $list = (string) array_pop($items);
    if ($items) { $list = strtr(e(__('list_two')), ['{first}' => array_pop($items), '{second}' => $list]); }
    while ($items) { $list = strtr(e(__('list_many')), ['{first}' => array_pop($items), '{rest}' => $list]); }
    return $list;
}
