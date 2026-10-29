<?php
declare(strict_types=1);
namespace App\Services;
use Core\{Database, ReleasePackage};

/** Static archive checks only: inspecting a catalog download never executes theme PHP. */
final class ThemeCatalogPackage
{
    public const MISMATCH = 'The theme file does not match the catalog. Check for themes again, then retry.';

    public static function verify(string $bytes, array $entry): void
    {
        if (strlen($bytes) !== $entry['size'] || !hash_equals($entry['sha256'], hash('sha256', $bytes))) {
            throw new \RuntimeException(self::MISMATCH);
        }
    }

    public static function inspect(string $file, Database $db, ?string $slug = null, ?array $entry = null): array
    {
        $temp = dirname(__DIR__, 2) . '/storage/theme-inspect-' . bin2hex(random_bytes(12));
        try {
            $actual = ThemePackage::extract($file, $temp);
            $manifest = json_decode((string) file_get_contents($temp . '/' . $actual . '/theme.json'), true);
            if ($slug !== null && $actual !== $slug) { throw new \RuntimeException('The theme.json slug does not match the catalog.'); }
            foreach (['version', 'api', 'requires'] as $field) {
                if ($entry !== null && ($manifest[$field] ?? null) !== $entry[$field]) { throw new \RuntimeException('The theme.json ' . $field . ' does not match the catalog.'); }
            }
            $theme = (new Themes($db, $temp))->usable($actual);
            $errors = Themes::syntaxErrors($theme['path']);
            if ($errors) { throw new \InvalidArgumentException(implode("\n", $errors)); }
            return $manifest;
        } finally { ReleasePackage::removeTree($temp); }
    }
}
