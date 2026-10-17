<?php
declare(strict_types=1);
namespace Core;

final class I18n
{
    private array $locales = [];
    private array $strings = [];
    private string $default;
    private string $current;

    public function __construct(Database $db, private array $builtins)
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
        foreach ($db->all('SELECT locale, `key`, value FROM ui_strings') as $row) {
            $this->strings[$row['locale']][$row['key']] = $row['value'];
        }
    }

    public function registry(): array { return $this->locales; }
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
        $text = $this->strings[$this->current][$key] ?? $this->strings[$this->default][$key] ?? $this->builtins[$key] ?? $key;
        $replace = [];
        foreach ($params as $name => $value) {
            $replace['{' . $name . '}'] = (string) $value;
        }
        return strtr($text, $replace);
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
