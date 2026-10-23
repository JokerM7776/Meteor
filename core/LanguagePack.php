<?php
declare(strict_types=1);
namespace Core;

/**
 * A language file: interface text for one language as JSON, so it can ship with Meteor
 * (lang/<code>.json) or move between sites. JSON, never PHP, so importing one cannot run code.
 *
 * {"meteor_language_pack": 1, "code": "zh", "name": "Chinese (Simplified)", "native_name": "简体中文",
 *  "direction": "ltr", "strings": {"posts": "文章", ...}}
 */
final class LanguagePack
{
    public const FORMAT = 1;
    public const MAX_BYTES = 262144;
    public const MAX_TEXT = 2000;

    public static function directory(): string { return dirname(__DIR__) . '/lang'; }

    public static function validCode(string $code): bool
    {
        return strlen($code) <= 35 && preg_match('/^[a-z]{2,3}(-[a-z0-9]{2,8})*$/D', $code) === 1;
    }

    /** The {name} placeholders in a text, sorted. */
    public static function placeholders(string $text): array
    {
        preg_match_all('/\{[a-z_]+\}/', $text, $m);
        $names = array_unique($m[0]); sort($names);
        return $names;
    }

    /** True when $text may stand in for the English $english: same placeholders, sane length. */
    public static function validText(string $text, string $english): bool
    {
        return $text !== '' && mb_check_encoding($text, 'UTF-8') && mb_strlen($text) <= self::MAX_TEXT
            && !preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $text)
            && self::placeholders($text) === self::placeholders($english);
    }

    /**
     * Parses and validates a language file. Unknown keys and texts that drop or add placeholders
     * are left out and counted in 'skipped'. Throws InvalidArgumentException for a bad file.
     */
    public static function parse(string $json, array $builtins): array
    {
        if (strlen($json) > self::MAX_BYTES) { throw new \InvalidArgumentException('Language files must be up to 256 KB.'); }
        try { $data = json_decode($json, true, 8, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { throw new \InvalidArgumentException('This is not a valid Meteor language file (JSON could not be read).'); }
        if (!is_array($data) || ($data['meteor_language_pack'] ?? null) !== self::FORMAT) { throw new \InvalidArgumentException('This is not a Meteor language file.'); }
        $code = $data['code'] ?? null; $name = $data['name'] ?? null; $native = $data['native_name'] ?? null; $direction = $data['direction'] ?? 'ltr';
        if (!is_string($code) || !self::validCode($code)) { throw new \InvalidArgumentException('The language file has an invalid language code.'); }
        foreach ([$name, $native] as $text) {
            if (!is_string($text) || trim($text) === '' || mb_strlen($text) > 191 || !mb_check_encoding($text, 'UTF-8')) { throw new \InvalidArgumentException('The language file needs both language names (up to 191 characters).'); }
        }
        if (!in_array($direction, ['ltr', 'rtl'], true)) { throw new \InvalidArgumentException('The language file has an invalid text direction.'); }
        if (!is_array($data['strings'] ?? null)) { throw new \InvalidArgumentException('The language file has no strings.'); }
        $strings = []; $skipped = 0;
        foreach ($data['strings'] as $key => $text) {
            if (is_string($key) && isset($builtins[$key]) && is_string($text) && self::validText(trim($text), $builtins[$key])) { $strings[$key] = trim($text); }
            else { $skipped++; }
        }
        return ['code' => $code, 'name' => trim($name), 'native_name' => trim($native), 'direction' => $direction, 'strings' => $strings, 'skipped' => $skipped];
    }

    /** The bundled file for one language, or null. Broken bundled files are ignored. */
    public static function bundled(string $code, array $builtins, ?string $directory = null): ?array
    {
        if (!self::validCode($code)) { return null; }
        $file = ($directory ?? self::directory()) . '/' . $code . '.json';
        if (!is_file($file)) { return null; }
        try { $pack = self::parse((string) file_get_contents($file), $builtins); }
        catch (\InvalidArgumentException) { return null; }
        return $pack['code'] === $code ? $pack : null;
    }

    /** Every bundled language file, by code. */
    public static function all(array $builtins, ?string $directory = null): array
    {
        $packs = [];
        foreach (glob(($directory ?? self::directory()) . '/*.json') ?: [] as $file) {
            $pack = self::bundled(basename($file, '.json'), $builtins, $directory);
            if ($pack !== null) { $packs[$pack['code']] = $pack; }
        }
        ksort($packs);
        return $packs;
    }

    public static function encode(array $locale, array $strings): string
    {
        return json_encode(['meteor_language_pack' => self::FORMAT, 'code' => $locale['code'], 'name' => $locale['name'],
            'native_name' => $locale['native_name'], 'direction' => $locale['direction'], 'strings' => (object) $strings],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    }
}
