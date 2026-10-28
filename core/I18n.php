<?php
declare(strict_types=1);
namespace Core;

final class I18n
{
    private array $locales = [];
    private string $default;
    private string $current;

    private array $packs = [];
    private array $edits = [];
    private array $themeStrings = [];

    /**
     * Text comes from: site edits → rendered theme → language file (a download in storage/lang wins
     * over the bundled lang/<code>.json) → default language → built-in English.
     */
    public function __construct(Database $db, private array $builtins, ?string $packDirectory = null, ?string $downloadDirectory = null)
    {
        foreach ($db->all('SELECT * FROM locales ORDER BY sort_order, code') as $locale) {
            $this->locales[$locale['code']] = $locale;
        }
        $defaults = array_filter($this->locales, fn ($row) => (int) $row['is_default'] === 1 && $row['status'] === 'live');
        if (count($defaults) !== 1) {
            throw new \RuntimeException('Exactly one live default locale is required. Run the installer.');
        }
        $this->default = (string) array_key_first($defaults);
        $this->current = $this->default;
        foreach (array_keys($this->locales) as $code) {
            $this->packs[$code] = LanguagePack::strings((string) $code, $builtins, $packDirectory, $downloadDirectory);
        }
        foreach ($db->all('SELECT locale, `key`, value FROM ui_strings') as $row) {
            $this->edits[$row['locale']][$row['key']] = $row['value'];
        }
    }

    public function useTheme(array $stringsByLocale): void { $this->themeStrings = $stringsByLocale; }

    public function registry(): array { return $this->locales; }
    public function builtins(): array { return $this->builtins; }
    /** Text from the language file of a language, without site edits. */
    public function packText(string $locale, string $key): ?string { return $this->packs[$locale][$key] ?? null; }
    public function defaultLocale(): string { return $this->default; }
    public function locale(): string { return $this->current; }
    public function direction(): string { return $this->locales[$this->current]['direction']; }

    public function enabledLocales(bool $isAdmin = false): array
    {
        return array_keys(array_filter($this->locales, fn ($row) => $row['status'] === 'live' || ($isAdmin && $row['status'] === 'preview')));
    }

    public function setLocale(string $locale, bool $isAdmin = false): void
    {
        if (!in_array($locale, $this->enabledLocales($isAdmin), true)) {
            throw new \InvalidArgumentException('Locale is not accessible.');
        }
        $this->current = $locale;
    }

    public function translate(string $key, array $params = []): string
    {
        $text = $this->translateFor($this->current, $key);
        $replace = [];
        foreach ($params as $name => $value) {
            $replace['{' . $name . '}'] = (string) $value;
        }
        return strtr($text, $replace);
    }

    /** The raw text of a key in any language, with the usual fallback chain and no placeholder replacement. */
    public function translateFor(string $locale, string $key): string
    {
        $theme = $this->themeStrings;
        $code = LanguagePack::RENAMED[$locale] ?? $locale;
        $default = LanguagePack::RENAMED[$this->default] ?? $this->default;
        return $this->edits[$locale][$key] ?? $theme[$code][$key] ?? $this->packs[$locale][$key]
            ?? $this->edits[$this->default][$key] ?? $theme[$default][$key] ?? $this->packs[$this->default][$key]
            ?? $theme['en'][$key] ?? $this->builtins[$key] ?? $key;
    }

    public function negotiate(string $header): string
    {
        $preferences = [];
        foreach (explode(',', $header) as $index => $part) {
            if (preg_match('/^\s*([a-z]{1,8}(?:-[a-z0-9]{1,8})*|\*)(?:\s*;\s*q=(0(?:\.\d{0,3})?|1(?:\.0{0,3})?))?\s*$/iD', $part, $m)) {
                $preferences[] = ['range' => strtolower($m[1]), 'q' => isset($m[2]) ? (float) $m[2] : 1.0, 'order' => $index];
            }
        }
        usort($preferences, fn ($a, $b) => ($b['q'] <=> $a['q']) ?: ($a['order'] <=> $b['order']));
        $live = array_values(array_filter($this->enabledLocales(), static function ($code) use ($preferences): bool {
            $quality = null;
            $specificity = -1;
            foreach ($preferences as $entry) {
                $range = $entry['range'];
                if ($range === '*' || strtolower($code) === $range || str_starts_with(strtolower($code), $range . '-')) {
                    $length = $range === '*' ? 0 : strlen($range);
                    if ($length > $specificity) { $quality = $entry['q']; $specificity = $length; }
                }
            }
            return $quality !== 0.0;
        }));
        foreach ($preferences as $preference) {
            if ($preference['q'] === 0.0) {
                continue;
            }
            $range = $preference['range'];
            if ($range === '*') {
                return in_array($this->default, $live, true) ? $this->default : ($live[0] ?? $this->default);
            }
            do {
                foreach ($live as $code) {
                    if (strtolower($code) === $range) {
                        return $code;
                    }
                }
                foreach ($live as $code) {
                    if (str_starts_with(strtolower($code), $range . '-')) {
                        return $code;
                    }
                }
                $pos = strrpos($range, '-');
                $range = $pos === false ? '' : substr($range, 0, $pos);
            } while ($range !== '');
        }
        return $this->default;
    }
}
