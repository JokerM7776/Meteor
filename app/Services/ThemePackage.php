<?php
declare(strict_types=1);
namespace App\Services;

/** Shared file policy for uploads, downloads and the author checker. Never follows symlinks. */
final class ThemePackage
{
    public const LIMIT = 20 * 1024 * 1024;
    public const EXPANDED = 50 * 1024 * 1024;

    public static function zipRequired(): void
    {
        if (!class_exists(\ZipArchive::class)) { throw new \InvalidArgumentException('Theme zip files need the PHP zip extension (ext-zip). Ask your host to enable it.'); }
    }

    /** Returns false for invisible metadata; unsafe names are always rejected first. */
    public static function visible(string $name): bool
    {
        if ($name === '' || str_contains($name, "\0") || str_contains($name, '\\') || str_starts_with($name, '/') || preg_match('/^[a-z]:/i', $name)
            || in_array('..', explode('/', $name), true)) { throw new \InvalidArgumentException('Unsafe theme path: ' . $name); }
        foreach (explode('/', rtrim($name, '/')) as $part) {
            if ($part === '' || $part[0] === '.' || $part === '__MACOSX') { return false; }
        }
        return true;
    }

    public static function allowed(string $name): void
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext === 'js') { throw new \InvalidArgumentException('Themes cannot ship JavaScript: public pages run no scripts.'); }
        if (!in_array($ext, ['php', 'json', 'css', 'md', 'txt', ...array_keys(Themes::ASSETS)], true)
            && !($ext === '' && in_array(basename($name), ['LICENSE', 'README', 'CHANGELOG'], true))) {
            throw new \InvalidArgumentException('File type is not allowed: ' . $name);
        }
    }

    /** @return array<string,string> Relative path => absolute file. */
    public static function files(string $folder, bool $skipLinks = false): array
    {
        if (!is_dir($folder) || is_link($folder)) { throw new \InvalidArgumentException('Choose a theme folder.'); }
        $files = [];
        $walk = function (string $dir, string $prefix = '') use (&$walk, &$files, $skipLinks): void {
            foreach (scandir($dir) ?: [] as $name) {
                if ($name === '.' || $name === '..') { continue; }
                $relative = $prefix . $name;
                if (!self::visible($relative)) { continue; }
                $path = $dir . '/' . $name;
                if (is_link($path) && $skipLinks) { continue; }
                if (is_link($path)) { throw new \InvalidArgumentException('Symlinks are not allowed: ' . $relative); }
                if (is_dir($path)) { $walk($path, $relative . '/'); }
                elseif (is_file($path)) { $files[$relative] = $path; }
                else { throw new \InvalidArgumentException('Not a regular file: ' . $relative); }
            }
        };
        $walk($folder);
        return $files;
    }

    public static function errors(string $folder): array
    {
        try { $files = self::files($folder); } catch (\InvalidArgumentException $e) { return [$e->getMessage()]; }
        $errors = []; $total = 0;
        if (count($files) > 1000) { $errors[] = 'Themes may contain at most 1000 entries.'; }
        foreach ($files as $name => $file) {
            try { self::allowed($name); } catch (\InvalidArgumentException $e) { $errors[] = $e->getMessage(); }
            $size = filesize($file); $total += $size;
            if ($size > Themes::ASSET_MAX) { $errors[] = $name . ' exceeds 5 MB.'; }
        }
        if ($total > self::EXPANDED) { $errors[] = 'The uncompressed theme exceeds 50 MB.'; }
        return $errors;
    }

    /** Validates the entire archive before writing any file. */
    public static function extract(string $source, string $destination): string
    {
        self::zipRequired();
        if (!is_file($source) || filesize($source) > self::LIMIT) { throw new \InvalidArgumentException('Upload a zip up to 20 MB.'); }
        $zip = new \ZipArchive();
        if ($zip->open($source) !== true) { throw new \InvalidArgumentException('This file is not a readable zip.'); }
        try {
            if ($zip->numFiles > 1000) { throw new \InvalidArgumentException('Themes may contain at most 1000 entries.'); }
            $entries = []; $total = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i); $name = $stat['name'];
                $visible = self::visible($name);
                $zip->getExternalAttributesIndex($i, $opsys, $attributes);
                if ((($attributes >> 16) & 0170000) === 0120000) { throw new \InvalidArgumentException('Symlinks are not allowed: ' . $name); }
                $total += $stat['size'];
                if ($total > self::EXPANDED || $stat['size'] > Themes::ASSET_MAX) { throw new \InvalidArgumentException('Theme exceeds the 50 MB total or 5 MB per-file limit.'); }
                if (!$visible) { continue; }
                if (isset($entries[$name])) { throw new \InvalidArgumentException('Duplicate zip entry: ' . $name); }
                if (!str_ends_with($name, '/')) { self::allowed($name); }
                $entries[$name] = $i;
            }
            $prefix = '';
            if (!isset($entries['theme.json'])) {
                $tops = array_unique(array_map(fn ($n) => explode('/', $n)[0], array_keys($entries)));
                if (count($tops) !== 1 || !isset($entries[reset($tops) . '/theme.json'])) {
                    throw new \InvalidArgumentException('The zip must have theme.json at its top level or inside one folder.');
                }
                $prefix = reset($tops) . '/';
            }
            $json = json_decode((string) $zip->getFromIndex($entries[$prefix . 'theme.json']), true);
            $slug = $json['slug'] ?? null;
            if (!is_string($slug) || $slug === '') { throw new \InvalidArgumentException('Add "slug": "my-theme" to theme.json'); }
            if (!preg_match(Themes::SLUG, $slug)) { throw new \InvalidArgumentException('Folder name must use lowercase letters, numbers and hyphens.'); }
            $target = $destination . '/' . $slug;
            foreach ($entries as $name => $i) {
                $relative = substr($name, strlen($prefix));
                if ($relative === '' || str_ends_with($relative, '/')) { continue; }
                $file = $target . '/' . $relative;
                self::mkdir(dirname($file));
                $contents = $zip->getFromIndex($i);
                if ($contents === false || file_put_contents($file, $contents, LOCK_EX) === false) { throw new \InvalidArgumentException('Cannot extract ' . $relative . '. Check folder permissions.'); }
            }
            return $slug;
        } finally { $zip->close(); }
    }

    public static function mkdir(string $folder): void
    {
        if (!is_dir($folder) && !@mkdir($folder, 0755, true)) { throw new \InvalidArgumentException('Cannot create ' . $folder . '. Check folder permissions.'); }
    }
}
