<?php
declare(strict_types=1);
namespace App\Services;
use Core\LanguagePack;

final class ThemeStrings
{
    public static function read(string $folder): array
    {
        $result = []; $builtins = require dirname(__DIR__, 2) . '/lang/en.php';
        if (is_link($folder . '/lang')) { throw new \UnexpectedValueException('lang must not be a symlink.'); }
        foreach (glob($folder . '/lang/*') ?: [] as $file) {
            $code = basename($file, '.json');
            if (!preg_match('/^[a-z]{2,8}(?:-[a-z0-9]{1,8})*$/D', $code) || !str_ends_with($file, '.json') || !is_file($file) || is_link($file) || filesize($file) > 65536) {
                throw new \UnexpectedValueException('Invalid theme language file: ' . basename($file) . ' (JSON, up to 64 KB).');
            }
            $object = json_decode((string) file_get_contents($file));
            if (!$object instanceof \stdClass) { throw new \UnexpectedValueException('lang/' . basename($file) . ' must be a JSON object of key → text.'); }
            $strings = (array) $object;
            foreach ($strings as $key => $text) {
                if (!preg_match('/^[a-z][a-z0-9_]{0,80}$/D', (string) $key) || !is_string($text) || mb_strlen($text) > 1000
                    || (isset($builtins[$key]) && !LanguagePack::validText($text, $builtins[$key]))) {
                    throw new \UnexpectedValueException('lang/' . basename($file) . ': invalid text for ' . $key . '. Use up to 1000 characters and keep built-in placeholders.');
                }
            }
            $canonical = LanguagePack::RENAMED[$code] ?? $code;
            // The canonical spelling wins when both old and new files are present.
            $result[$canonical] = $code === $canonical ? $strings + ($result[$canonical] ?? []) : ($result[$canonical] ?? []) + $strings;
        }
        return $result;
    }

    public static function ownKeys(?array $theme, array $builtins): array
    {
        $keys = [];
        foreach ($theme['strings'] ?? [] as $strings) { $keys += array_diff_key($strings, $builtins); }
        return $keys;
    }
}
