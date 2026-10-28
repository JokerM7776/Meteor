<?php
declare(strict_types=1);
namespace App\Theme;

/** Theme API v1: Theme. The active theme, or the built-in look when $slug is empty. */
final readonly class Theme
{
    public function __construct(
        /** Folder name; empty for the built-in look. */
        public string $slug = '',
        /** Display name from theme.json. */
        public string $name = 'Built-in',
        /** Version from theme.json; empty for the built-in look. */
        public string $version = '',
        private string $root = '',
        private array $values = [],
        private array $types = [],
    ) {}

    /** A setting's value (bool for toggle, string otherwise), or null when the theme declares no such setting. */
    public function setting(string $key): string|bool|null { return $this->values[$key] ?? null; }

    /** Versioned URL of a file in the theme folder (css, images, fonts). Throws for a missing or disallowed file. */
    public function asset(string $path): string
    {
        if ($this->slug === '') { throw new \LogicException('The built-in look has no theme assets. Use asset() for Meteor assets.'); }
        $file = \App\Services\Themes::assetFile($this->root, $path);
        if ($file === null) { throw new \InvalidArgumentException('Theme asset is missing or not allowed: ' . $path); }
        return '/themes/' . $this->slug . '/' . implode('/', array_map('rawurlencode', explode('/', $path))) . '?v=' . hash_file('sha256', $file);
    }

    /** Whether the theme folder has this asset (always false for the built-in look). */
    public function hasAsset(string $path): bool { return $this->slug !== '' && \App\Services\Themes::assetFile($this->root, $path) !== null; }

    /** The color settings as CSS variables: ":root{--theme-accent:#ea580c}". Empty without color settings. */
    public function settingsCss(): string
    {
        $rules = [];
        foreach ($this->types as $key => $type) {
            if ($type === 'color' && is_string($this->values[$key] ?? null)) { $rules[] = '--theme-' . str_replace('_', '-', $key) . ':' . $this->values[$key]; }
        }
        return $rules ? ':root{' . implode(';', $rules) . '}' : '';
    }

    /** URL of settingsCss() as a stylesheet (public pages allow no inline styles), or '' when there is none. */
    public function settingsCssUrl(): string
    {
        $css = $this->settingsCss();
        return $css === '' ? '' : '/theme-settings.css?v=' . substr(hash('sha256', $this->slug . $css), 0, 16);
    }
}
