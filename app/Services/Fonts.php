<?php
declare(strict_types=1);
namespace App\Services;
use Core\Database;

/**
 * Where the site's fonts come from. "local" serves the files in public/assets/fonts (made by
 * `php bin/fonts.php`); while they are missing, pages fall back to Google Fonts.
 */
final class Fonts
{
    public const SOURCES = ['local', 'google', 'system'];
    public const DIRECTORY = 'assets/fonts';
    public const GOOGLE = 'https://fonts.googleapis.com/css2?family=Source+Sans+3:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Source+Serif+4:ital,opsz,wght@0,8..60,400;0,8..60,500;0,8..60,600;1,8..60,400&family=JetBrains+Mono:wght@400;500&family=Noto+Sans+SC:wght@400;500;700&family=Noto+Serif+SC:wght@400;600&display=swap';

    public static function source(string $stored): string
    {
        return in_array($stored, self::SOURCES, true) ? $stored : 'local';
    }

    /** The source pages actually use, and the font files to preload when it is "local". */
    public static function resolve(string $stored, ?string $public = null): array
    {
        $source = self::source($stored);
        if ($source !== 'local') { return [$source, []]; }
        $manifest = self::manifest($public);
        return $manifest === null ? ['google', []] : ['local', $manifest['preload']];
    }

    /** fonts.json written by bin/fonts.php, or null when the local font files are not there. */
    public static function manifest(?string $public = null): ?array
    {
        static $cache = [];
        $directory = ($public ?? dirname(__DIR__, 2) . '/public') . '/' . self::DIRECTORY;
        if (array_key_exists($directory, $cache)) { return $cache[$directory]; }
        $data = is_file($directory . '/fonts.json') && is_file($directory . '/fonts.css') ? json_decode((string) file_get_contents($directory . '/fonts.json'), true) : null;
        $preload = is_array($data['preload'] ?? null) ? array_values(array_filter($data['preload'], fn ($file) => is_string($file) && preg_match('/^[a-z0-9.-]+\.woff2$/D', $file) && is_file($directory . '/' . $file))) : [];
        return $cache[$directory] = is_array($data) ? ['preload' => $preload, 'files' => (int) ($data['files'] ?? 0)] : null;
    }

    public static function save(Database $db, array $input): void
    {
        $source = (string) ($input['font_source'] ?? '');
        if (!in_array($source, self::SOURCES, true)) { throw new \InvalidArgumentException('Choose where fonts load from.'); }
        $db->upsert('settings', ['key' => 'font_source', 'locale' => null, 'value' => $source], ['key', 'locale_scope']);
    }
}
