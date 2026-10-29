<?php
declare(strict_types=1);
namespace Core;
use App\Services\{Themes, ThemePackage};

/** Reviewed release assets; one invalid entry rejects the entire index. */
final class ThemeCatalog
{
    public const FORMAT = 1;
    public const MAX_BYTES = 262144;

    public static function repository(string $repo): bool
    {
        return (bool) preg_match('~^[A-Za-z0-9](?:[A-Za-z0-9-]{0,37}[A-Za-z0-9])?/[A-Za-z0-9_.-]{1,100}$~D', $repo)
            && !str_contains(explode('/', $repo)[0], '--') && !in_array(explode('/', $repo)[1], ['.', '..'], true);
    }

    public static function zipUrl(string $url, ?string $repo = null): bool
    {
        if (!preg_match('~^https://github\.com/([^/]+/[^/]+)/releases/download/([^/]+)/([^/]+\.zip)$~D', $url, $m)
            || !self::repository($m[1]) || ($repo !== null && $m[1] !== $repo)
            || preg_match('/[?#\\\\\x00-\x20\x7f]/', $url) || str_contains($url, '..')) { return false; }
        // Decode once and forbid nested encodings, separators and traversal in tag/file components.
        foreach ([$m[2], $m[3]] as $part) {
            if (preg_match('/%(?![a-fA-F0-9]{2})/', $part)) { return false; }
            $decoded = rawurldecode($part);
            if (preg_match('/[%\/\\\\?#\x00-\x20\x7f]/', $decoded) || str_contains($decoded, '..')) { return false; }
        }
        return true;
    }

    public static function utc(mixed $value): bool
    {
        if (!is_string($value)) { return false; }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $value, new \DateTimeZone('UTC'));
        return $date !== false && $date->format('Y-m-d\TH:i:s\Z') === $value;
    }

    public static function parse(string $json): array
    {
        if (strlen($json) > self::MAX_BYTES) { throw new \RuntimeException('The theme catalog is too large.'); }
        try { $data = json_decode($json, false, 32, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { throw new \RuntimeException('The theme catalog could not be read.'); }
        if (!$data instanceof \stdClass || ($data->meteor_theme_index ?? null) !== self::FORMAT || !($data->themes ?? null) instanceof \stdClass
            || count((array) $data->themes) > 500) { throw new \RuntimeException('The theme catalog has an invalid format or too many themes.'); }
        $themes = [];
        foreach ($data->themes as $slug => $raw) {
            $fail = static function (string $field) use ($slug): never { throw new \RuntimeException('Theme ' . $slug . ': invalid ' . $field . '.'); };
            if (!preg_match(Themes::SLUG, (string) $slug)) { $fail('slug'); }
            if (!$raw instanceof \stdClass) { $fail('entry'); }
            $entry = (array) $raw; $clean = [];
            foreach (['name' => 100, 'description' => 500, 'author' => 191, 'license' => 64] as $field => $max) {
                $value = $entry[$field] ?? null;
                if (!is_string($value) || mb_strlen($value) > $max || preg_match('/\p{Cc}/u', $value)
                    || (in_array($field, ['name', 'license'], true) && trim($value) === '')) { $fail($field); }
                $clean[$field] = $value;
            }
            foreach (['repo', 'version', 'requires', 'zip', 'sha256'] as $field) { if (!is_string($entry[$field] ?? null)) { $fail($field); } }
            if (!self::repository($entry['repo'])) { $fail('repo'); }
            if (!preg_match('/^\d+\.\d+\.\d+$/D', $entry['version'])) { $fail('version'); }
            if (!is_int($entry['api'] ?? null)) { $fail('api'); }
            if (!preg_match('/^\d+\.\d+(?:\.\d+)?$/D', $entry['requires'])) { $fail('requires'); }
            if (!self::zipUrl($entry['zip'], $entry['repo'])) { $fail('zip'); }
            if (!preg_match('/^[a-f0-9]{64}$/D', $entry['sha256'])) { $fail('sha256'); }
            if (!is_int($entry['size'] ?? null) || $entry['size'] < 1 || $entry['size'] > ThemePackage::LIMIT) { $fail('size'); }
            if (!self::utc($entry['updated'] ?? null)) { $fail('updated'); }
            foreach (['repo', 'version', 'api', 'requires', 'zip', 'sha256', 'size', 'updated'] as $field) { $clean[$field] = $entry[$field]; }
            $themes[$slug] = $clean;
        }
        ksort($themes);
        return $themes;
    }

    public static function encode(array $themes): string
    {
        ksort($themes);
        return json_encode(['meteor_theme_index' => self::FORMAT, 'themes' => (object) $themes],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    }
}
