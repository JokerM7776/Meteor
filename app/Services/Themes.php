<?php
declare(strict_types=1);
namespace App\Services;

use Core\Database;

/**
 * Installed themes: folders in storage/themes/<slug>/ with a theme.json. Updates keep storage/, so themes
 * survive them. A theme with a problem is listed but cannot be activated or previewed.
 */
final class Themes
{
    use ThemeAuthoring;
    /** Theme API versions this release can run. */
    public const API = [1];
    public const SLUG = '/^[a-z0-9][a-z0-9-]{0,62}$/D';
    public const ASSETS = ['css' => 'text/css; charset=UTF-8', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif',
        'webp' => 'image/webp', 'avif' => 'image/avif', 'svg' => 'image/svg+xml', 'ico' => 'image/x-icon', 'woff' => 'font/woff', 'woff2' => 'font/woff2'];
    public const ASSET_MAX = 5242880;
    private const TYPES = ['toggle', 'select', 'text', 'color'];

    /** The example theme shipped with Meteor; "Install the starter theme" copies it to storage/themes/starter. */
    public const STARTER = 'views/examples/starter';
    /** Tests point this at a temporary folder. */
    public static ?string $directory = null;

    private string $root;
    public function __construct(private Database $db, ?string $root = null, private ?\Closure $rename = null) { $this->root = $root ?? self::$directory ?? dirname(__DIR__, 2) . '/storage/themes'; }

    /** Copies the starter theme into the themes folder (never over an existing one). */
    public function installStarter(): array
    {
        $source = dirname(__DIR__, 2) . '/' . self::STARTER;
        $target = $this->root . '/starter';
        if (file_exists($target)) { throw new \InvalidArgumentException('A theme folder named “starter” already exists.'); }
        if (!is_dir($this->root) && !@mkdir($this->root, 0755, true)) { throw new \InvalidArgumentException('Cannot create ' . $this->root . '. Check folder permissions.'); }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $relative = substr($file->getPathname(), strlen($source) + 1);
            if (!is_dir(dirname($target . '/' . $relative)) && !@mkdir(dirname($target . '/' . $relative), 0755, true)) { throw new \InvalidArgumentException('Cannot copy the starter theme.'); }
            if (!copy($file->getPathname(), $target . '/' . $relative)) { throw new \InvalidArgumentException('Cannot copy the starter theme.'); }
        }
        return $this->find('starter') ?? throw new \InvalidArgumentException('Cannot copy the starter theme.');
    }

    public function root(): string { return $this->root; }

    /** @return list<array> Every theme folder, valid or not, sorted by name. */
    public function all(): array
    {
        $themes = [];
        foreach (is_dir($this->root) ? (scandir($this->root) ?: []) : [] as $name) {
            if ($name[0] === '.' || !is_dir($this->root . '/' . $name) || is_link($this->root . '/' . $name)) { continue; }
            $themes[] = $this->read($name);
        }
        usort($themes, fn ($a, $b) => strcasecmp($a['name'], $b['name']));
        return $themes;
    }

    public function find(string $slug): ?array
    {
        if (!preg_match(self::SLUG, $slug) || !is_dir($this->root . '/' . $slug) || is_link($this->root . '/' . $slug)) { return null; }
        return $this->read($slug);
    }

    public function activeSlug(): string
    {
        return (string) ($this->db->one("SELECT value FROM settings WHERE `key` = 'theme_active' AND locale_scope = ''")['value'] ?? '');
    }

    /** The theme to render: an admin's preview, else the active theme; null for the built-in look or a theme with a problem. */
    public function current(bool $admin): ?array
    {
        $preview = $admin ? ($_SESSION['theme_preview'] ?? '') : '';
        foreach ([$preview, $this->activeSlug()] as $slug) {
            if (!is_string($slug) || $slug === '') { continue; }
            $theme = $this->find($slug);
            if ($theme && $theme['problem'] === '') { return $theme; }
        }
        return null;
    }

    public function previewing(bool $admin): ?array
    {
        $slug = $admin ? ($_SESSION['theme_preview'] ?? '') : '';
        $theme = is_string($slug) && $slug !== '' ? $this->find($slug) : null;
        return $theme && $theme['problem'] === '' ? $theme : null;
    }

    public function activate(string $slug): array
    {
        $theme = $this->usable($slug);
        $this->db->upsert('settings', ['key' => 'theme_active', 'locale' => null, 'value' => $slug], ['key', 'locale_scope']);
        return $theme;
    }

    public function deactivate(): void
    {
        $this->db->upsert('settings', ['key' => 'theme_active', 'locale' => null, 'value' => ''], ['key', 'locale_scope']);
    }

    public function usable(string $slug): array
    {
        $theme = $this->find($slug);
        if (!$theme) { throw new \InvalidArgumentException('This theme is not installed.'); }
        if ($theme['problem'] !== '') { throw new \InvalidArgumentException($theme['name'] . ': ' . $theme['problem']); }
        return $theme;
    }

    /** Typed values of a theme's settings: stored ones that are still valid, else the defaults. */
    public function values(array $theme): array
    {
        $stored = [];
        $rows = $this->db->all("SELECT `key`, value FROM settings WHERE locale_scope = '' AND `key` LIKE ?", ['theme.' . $theme['slug'] . '.%']);
        foreach ($rows as $row) { $stored[substr($row['key'], strlen('theme.' . $theme['slug'] . '.'))] = json_decode($row['value'], true); }
        $values = [];
        foreach ($theme['settings'] as $setting) {
            $value = $stored[$setting['key']] ?? null;
            $values[$setting['key']] = $value !== null && self::valid($setting, $value) ? $value : $setting['default'];
        }
        return $values;
    }

    /** Saves the settings form. Returns field errors (key => message); nothing is saved when there are any. */
    public function saveValues(array $theme, array $input): array
    {
        $values = []; $errors = [];
        foreach ($theme['settings'] as $setting) {
            $key = $setting['key']; $raw = $input[$key] ?? null;
            $value = match ($setting['type']) {
                'toggle' => $raw === '1',
                'text' => is_string($raw) ? trim(str_replace("\r\n", "\n", $raw)) : '',
                default => is_string($raw) ? trim($raw) : '',
            };
            if ($setting['type'] === 'color') { $value = strtolower($value); }
            if (!self::valid($setting, $value)) {
                $errors[$key] = match ($setting['type']) {
                    'select' => 'Choose one of the options.', 'color' => 'Use a color like #ea580c.',
                    default => 'Use at most ' . $setting['max'] . ' characters.',
                };
                continue;
            }
            $values[$key] = $value;
        }
        if ($errors) { return $errors; }
        $this->db->transaction(function () use ($theme, $values) {
            foreach ($values as $key => $value) {
                $this->db->upsert('settings', ['key' => 'theme.' . $theme['slug'] . '.' . $key, 'locale' => null, 'value' => json_encode($value, JSON_UNESCAPED_UNICODE)], ['key', 'locale_scope']);
            }
        });
        return [];
    }

    /** The Theme API object for a theme, or for the built-in look. */
    public function object(?array $theme): \App\Theme\Theme
    {
        if ($theme === null) { return new \App\Theme\Theme(); }
        return new \App\Theme\Theme($theme['slug'], $theme['name'], $theme['version'], $theme['path'], $this->values($theme), array_column($theme['settings'], 'type', 'key'));
    }

    /** A setting or option label in one language: the language, then the default language, then English, then the first. */
    public static function label(mixed $label, string $locale, string $default = 'en'): string
    {
        if (is_string($label)) { return $label; }
        if (!is_array($label) || !$label) { return ''; }
        foreach ([$locale, $default, 'en'] as $code) { if (isset($label[$code]) && is_string($label[$code])) { return $label[$code]; } }
        $first = reset($label);
        return is_string($first) ? $first : '';
    }

    /** Absolute path of an allowed asset inside a theme folder, or null. */
    public static function assetFile(string $themeRoot, string $path): ?string
    {
        $path = str_replace('\\', '/', $path);
        if ($path === '' || str_contains($path, "\0") || str_starts_with($path, '/')) { return null; }
        $candidate = $themeRoot;
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part[0] === '.') { return null; }
            $candidate .= '/' . $part;
            if (is_link($candidate)) { return null; }
        }
        if (!isset(self::ASSETS[strtolower(pathinfo($path, PATHINFO_EXTENSION))])) { return null; }
        $root = realpath($themeRoot); $file = realpath($themeRoot . '/' . $path);
        if ($root === false || $file === false || !str_starts_with($file, $root . DIRECTORY_SEPARATOR) || !is_file($file)) { return null; }
        if (filesize($file) > self::ASSET_MAX) { return null; }
        return $file;
    }

    private function read(string $slug): array
    {
        $path = $this->root . '/' . $slug;
        $theme = ['slug' => $slug, 'name' => $slug, 'version' => '', 'api' => 0, 'requires' => '', 'author' => '', 'homepage' => '', 'license' => '',
            'description' => '', 'settings' => [], 'strings' => [], 'path' => $path, 'problem' => ''];
        try {
            if (!preg_match(self::SLUG, $slug)) { throw new \UnexpectedValueException('Folder name must use lowercase letters, numbers and hyphens.'); }
            $file = $path . '/theme.json';
            if (!is_file($file) || is_link($file)) { throw new \UnexpectedValueException('theme.json is missing.'); }
            if (filesize($file) > 65536) { throw new \UnexpectedValueException('theme.json is larger than 64 KB.'); }
            $json = json_decode((string) file_get_contents($file), true);
            if (!is_array($json) || array_is_list($json)) { throw new \UnexpectedValueException('theme.json is not a JSON object.'); }
            foreach (['version', 'author', 'homepage', 'license', 'description', 'requires'] as $key) {
                if (isset($json[$key]) && is_string($json[$key])) { $theme[$key] = mb_substr(trim($json[$key]), 0, $key === 'description' ? 500 : 191); }
            }
            if (is_string($json['name'] ?? null) && trim($json['name']) !== '') { $theme['name'] = mb_substr(trim($json['name']), 0, 100); }
            else { throw new \UnexpectedValueException('theme.json needs a name.'); }
            if (($json['slug'] ?? $slug) !== $slug) { throw new \UnexpectedValueException('The slug in theme.json must match the folder name.'); }
            if (!is_int($json['api'] ?? null)) { throw new \UnexpectedValueException('theme.json needs "api": 1.'); }
            $theme['api'] = $json['api'];
            if (!in_array($json['api'], self::API, true)) { throw new \UnexpectedValueException('Built for Theme API ' . $json['api'] . '; this Meteor supports API ' . implode(', ', self::API) . '.'); }
            if ($theme['homepage'] !== '' && !preg_match('~^https?://~i', $theme['homepage'])) { $theme['homepage'] = ''; }
            $current = \Core\Version::current();
            if ($theme['requires'] !== '' && preg_match('/^\d+\.\d+\.\d+/', $current) && preg_match('/^\d+\.\d+(\.\d+)?$/D', $theme['requires'])
                && version_compare($current, $theme['requires'], '<')) {
                throw new \UnexpectedValueException('Needs Meteor ' . $theme['requires'] . ' or newer.');
            }
            $theme['settings'] = self::settings($json['settings'] ?? []);
            $theme['strings'] = ThemeStrings::read($path);
        } catch (\UnexpectedValueException $e) { $theme['problem'] = $e->getMessage(); }
        return $theme;
    }

    private static function settings(mixed $list): array
    {
        if (!is_array($list) || !array_is_list($list)) { throw new \UnexpectedValueException('"settings" must be a list.'); }
        $settings = []; $keys = [];
        foreach ($list as $i => $raw) {
            $where = 'Setting ' . ($i + 1) . ': ';
            if (!is_array($raw)) { throw new \UnexpectedValueException($where . 'must be an object.'); }
            $key = $raw['key'] ?? null; $type = $raw['type'] ?? null;
            if (!is_string($key) || !preg_match('/^[a-z][a-z0-9_]{0,40}$/D', $key) || isset($keys[$key])) { throw new \UnexpectedValueException($where . 'needs a unique key of lowercase letters, numbers and _.'); }
            if (!in_array($type, self::TYPES, true)) { throw new \UnexpectedValueException($where . 'type must be toggle, select, text or color.'); }
            $label = $raw['label'] ?? $key;
            if (!self::labelOk($label)) { throw new \UnexpectedValueException($where . 'label must be text or {"language": "text"}.'); }
            $setting = ['key' => $key, 'type' => $type, 'label' => $label, 'options' => [], 'max' => 0];
            if ($type === 'select') {
                $options = $raw['options'] ?? null;
                if (!is_array($options) || !$options || array_is_list($options)) { throw new \UnexpectedValueException($where . 'options must be {"value": "Label"}.'); }
                foreach ($options as $value => $optionLabel) {
                    if (!self::labelOk($optionLabel)) { throw new \UnexpectedValueException($where . 'option labels must be text.'); }
                    $setting['options'][(string) $value] = $optionLabel;
                }
            }
            if ($type === 'text') {
                $max = $raw['max'] ?? 200;
                if (!is_int($max) || $max < 1 || $max > 1000) { throw new \UnexpectedValueException($where . 'max must be 1 to 1000.'); }
                $setting['max'] = $max;
            }
            $default = $raw['default'] ?? match ($type) { 'toggle' => false, 'select' => array_key_first($setting['options']), 'color' => '#000000', default => '' };
            if ($type === 'select') { $default = (string) $default; }
            if (!self::valid($setting, $default)) { throw new \UnexpectedValueException($where . 'default is not valid for its type.'); }
            $setting['default'] = $default;
            $settings[] = $setting; $keys[$key] = true;
        }
        return $settings;
    }

    private static function labelOk(mixed $label): bool
    {
        if (is_string($label)) { return trim($label) !== ''; }
        if (!is_array($label) || !$label || array_is_list($label)) { return false; }
        foreach ($label as $text) { if (!is_string($text)) { return false; } }
        return true;
    }

    private static function valid(array $setting, mixed $value): bool
    {
        return match ($setting['type']) {
            'toggle' => is_bool($value),
            'select' => is_string($value) && array_key_exists($value, $setting['options']),
            'text' => is_string($value) && mb_strlen($value) <= $setting['max'],
            'color' => is_string($value) && (bool) preg_match('/^#[0-9a-f]{6}$/iD', $value),
            default => false,
        };
    }
}
