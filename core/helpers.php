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

/** A stored UTC date in the page language, the site's time zone and date format (or an ICU $pattern). */
function localized_date(string $date, ?string $pattern = null): string
{
    return Core\Dates::date($date, $GLOBALS['i18n']->locale(), $pattern);
}

/** A stored UTC time for admin pages: the site's time zone, date format and clock; empty → "—". */
function site_time(?string $utc, bool $withTime = true): string
{
    if ($utc === null || trim($utc) === '') { return '—'; }
    return $withTime ? Core\Dates::dateTime($utc, 'en') : Core\Dates::date($utc, 'en');
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

/**
 * Gzips a text response (HTML, RSS, sitemap, JSON) when the browser accepts it, so pages are about
 * a fifth of their size even on hosts that do not compress. Skipped when PHP or a header already does.
 */
function compressed(string $body): string
{
    if (strlen($body) < 1024 || headers_sent() || !function_exists('gzencode') || ini_get('zlib.output_compression')
        || !preg_match('/\bgzip\b/i', (string) ($_SERVER['HTTP_ACCEPT_ENCODING'] ?? ''))) { return $body; }
    $type = '';
    foreach (headers_list() as $header) {
        if (stripos($header, 'content-encoding:') === 0) { return $body; }
        if (stripos($header, 'content-type:') === 0) { $type = strtolower($header); }
    }
    if (!preg_match('~text/|xml|json~', $type)) { return $body; }
    $gzip = gzencode($body, 6);
    if ($gzip === false) { return $body; }
    header('Content-Encoding: gzip');
    header('Vary: Accept-Encoding', false);
    return $gzip;
}
