<?php
declare(strict_types=1);
namespace Core;

/**
 * The index of language files in lang/ (lang/index.json). Sites read it from GitHub to offer
 * languages that are not bundled yet, and newer text for ones that are.
 *
 * {"meteor_language_index": 1, "languages": {"ms": {"name": "Malay", "native_name": "Bahasa Melayu",
 *  "direction": "ltr", "texts": 46, "sha256": "…", "updated": "2026-10-24T08:00:00Z"}}}
 */
final class LanguageCatalog
{
    public const FORMAT = 1;
    public const FILE = 'index.json';
    public const MAX_BYTES = 262144;

    /**
     * Indexes every valid language file in $directory. `updated` changes only when a file's
     * contents change, so rebuilding an unchanged directory gives the same index.
     */
    public static function build(string $directory, array $builtins, array $previous = [], ?string $now = null): array
    {
        $now ??= gmdate('Y-m-d\TH:i:s\Z');
        $languages = [];
        foreach (LanguagePack::all($builtins, $directory) as $code => $pack) {
            $hash = hash_file('sha256', $directory . '/' . $code . '.json');
            $old = $previous[$code] ?? null;
            $languages[$code] = ['name' => $pack['name'], 'native_name' => $pack['native_name'], 'direction' => $pack['direction'],
                'texts' => count($pack['strings']), 'sha256' => $hash,
                'updated' => is_array($old) && ($old['sha256'] ?? '') === $hash && is_string($old['updated'] ?? null) ? $old['updated'] : $now];
        }
        return $languages;
    }

    public static function encode(array $languages): string
    {
        ksort($languages);
        return json_encode(['meteor_language_index' => self::FORMAT, 'languages' => (object) $languages],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    }

    /** Languages by code from an index file. Throws RuntimeException for a bad file; drops bad entries. */
    public static function parse(string $json): array
    {
        if (strlen($json) > self::MAX_BYTES) { throw new \RuntimeException('The language catalog is too large.'); }
        try { $data = json_decode($json, true, 8, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { throw new \RuntimeException('The language catalog could not be read.'); }
        if (!is_array($data) || ($data['meteor_language_index'] ?? null) !== self::FORMAT || !is_array($data['languages'] ?? null)) {
            throw new \RuntimeException('The language catalog has an unknown format. Update Meteor first.');
        }
        $languages = [];
        foreach ($data['languages'] as $code => $entry) {
            if (!is_string($code) || !LanguagePack::validCode($code) || !is_array($entry)) { continue; }
            $name = $entry['name'] ?? null; $native = $entry['native_name'] ?? null;
            if (!is_string($name) || !is_string($native) || trim($name) === '' || trim($native) === ''
                || mb_strlen($name) > 191 || mb_strlen($native) > 191 || !mb_check_encoding($name . $native, 'UTF-8')
                || !in_array($entry['direction'] ?? null, ['ltr', 'rtl'], true) || !is_int($entry['texts'] ?? null)
                || !is_string($entry['sha256'] ?? null) || !preg_match('/^[a-f0-9]{64}$/D', $entry['sha256'])
                || !is_string($entry['updated'] ?? null) || !preg_match('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/D', $entry['updated'])) { continue; }
            $languages[$code] = ['name' => trim($name), 'native_name' => trim($native), 'direction' => $entry['direction'],
                'texts' => max(0, $entry['texts']), 'sha256' => $entry['sha256'], 'updated' => $entry['updated']];
        }
        ksort($languages);
        return $languages;
    }

    /** The index that ships in $directory, or [] when it is missing or broken. */
    public static function bundled(?string $directory = null): array
    {
        $file = ($directory ?? LanguagePack::directory()) . '/' . self::FILE;
        if (!is_file($file)) { return []; }
        try { return self::parse((string) file_get_contents($file)); } catch (\RuntimeException) { return []; }
    }
}
