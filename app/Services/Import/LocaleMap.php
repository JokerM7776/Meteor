<?php
declare(strict_types=1);
namespace App\Services\Import;

final class LocaleMap
{
    public static function match(string $language, array $locales): ?string
    {
        $map = array_combine(array_map('strtolower', $locales), $locales);
        if (isset($map[strtolower($language)])) { return $map[strtolower($language)]; }
        $code = strtolower(str_replace('_', '-', $language));
        $alias = match ($code) { 'zh', 'zh-cn', 'zh-hans', 'zh-sg' => 'zh-hans', 'zh-tw', 'zh-hk', 'zh-hant' => 'zh-hant', default => $code };
        return $map[$alias] ?? $map[explode('-', $code)[0]] ?? null;
    }
}
