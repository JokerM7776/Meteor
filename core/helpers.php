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
