<?php
declare(strict_types=1);
namespace Core;
final class ReleasePackage
{
    public const LIMIT = 52428800;
    public const EXPANDED_LIMIT = 209715200;
    public static function requirements(): void
    {
        foreach (['zip', 'curl'] as $ext) {
            if (!extension_loaded($ext)) { throw new \RuntimeException('Enable ext-' . $ext . ' to package or update Meteor.'); }
        }
    }
    public static function safePath(string $path): bool
    {
        return $path !== '' && !preg_match('~[\\\\\x00-\x1f\x7f:]|^/|(?:^|/)\.{1,2}(?:/|$)|//~', $path);
    }
    public static function managed(string $path): bool
    {
        if (!self::safePath($path)) { return false; }
        foreach (explode('/', $path) as $part) {
            if (str_starts_with(strtolower($part), '.env') && $path !== 'config/.env.example') { return false; }
        }
        if (preg_match('~^(storage|public/uploads)(/|$)~i', $path)) { return false; }
        return in_array($path, ['VERSION', 'README.md', 'README', 'LICENSE', 'LICENSE.md', 'bootstrap.php', 'manifest.json'], true)
            || preg_match('~^(app|bin|config|core|database|lang|public|src|views)/~', $path) === 1;
    }
    public static function build(string $root, string $destination): string
    {
        self::requirements();
        $version = Version::current($root);
        if (!is_dir($destination) && !mkdir($destination, 0700, true)) { throw new \RuntimeException('Cannot create dist directory.'); }
        $files = [];
        foreach (['app', 'bin', 'config', 'core', 'database', 'lang', 'public', 'src', 'views'] as $directory) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $directory, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                $path = substr($file->getPathname(), strlen($root) + 1);
                if ($file->isLink()) { throw new \RuntimeException('Release sources must not contain symlinks.'); }
                if ($file->isFile() && self::managed($path)) { $files[$path] = hash_file('sha256', $file->getPathname()); }
            }
        }
        foreach (['bootstrap.php', 'VERSION', 'README.md', 'README', 'LICENSE', 'LICENSE.md'] as $path) {
            if (is_file($root . '/' . $path)) { $files[$path] = hash_file('sha256', $root . '/' . $path); }
        }
        $files['storage/.gitkeep'] = hash('sha256', '');
        $files['public/uploads/.gitkeep'] = hash('sha256', '');
        ksort($files);
        $zipPath = $destination . '/meteor-' . $version . '.zip';
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) { throw new \RuntimeException('Cannot create release zip.'); }
        foreach ($files as $path => $hash) {
            if (in_array($path, ['storage/.gitkeep', 'public/uploads/.gitkeep'], true)) { $zip->addFromString('meteor/' . $path, ''); }
            else { $zip->addFile($root . '/' . $path, 'meteor/' . $path); }
        }
        $zip->addFromString('meteor/manifest.json', json_encode(['name' => 'meteor', 'version' => $version, 'min_php' => '8.2.0', 'built_at' => gmdate('c'), 'files' => $files], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        if (!$zip->close()) { throw new \RuntimeException('Cannot finish release zip.'); }
        if (file_put_contents($zipPath . '.sha256', hash_file('sha256', $zipPath) . '  ' . basename($zipPath) . "\n") === false) { throw new \RuntimeException('Cannot write checksum.'); }
        return $zipPath;
    }
    public static function extract(string $archive, string $staging): array
    {
        self::requirements();
        if (!is_file($archive) || filesize($archive) > self::LIMIT) { throw new \RuntimeException('Update zip must be at most 50 MB.'); }
        $zip = new \ZipArchive();
        if ($zip->open($archive) !== true) { throw new \RuntimeException('Invalid update zip.'); }
        try {
            if ($zip->numFiles > 5000) { throw new \RuntimeException('Too many zip entries.'); }
            $entries = []; $names = []; $total = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i); $name = $stat['name'];
                $zip->getExternalAttributesIndex($i, $opsys, $attributes);
                $type = ($attributes >> 16) & 0170000;
                if (!self::safePath($name) || !str_starts_with($name, 'meteor/') || isset($names[strtolower($name)])
                    || ($type !== 0 && !in_array($type, [0100000, 0040000], true))
                    || ($stat['encryption_method'] ?? 0) !== 0) { throw new \RuntimeException('Unsafe zip entry: ' . $name); }
                $total += $stat['size'];
                if ($total > self::EXPANDED_LIMIT) { throw new \RuntimeException('Expanded zip exceeds 200 MB.'); }
                $entries[$name] = $stat; $names[strtolower($name)] = true;
            }
            $raw = $zip->getFromName('meteor/manifest.json');
            if ($raw === false || strlen($raw) > 2097152) { throw new \RuntimeException('Missing or oversized manifest.'); }
            $manifest = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
            if (($manifest['name'] ?? '') !== 'meteor' || !preg_match('/^\d+\.\d+\.\d+$/D', $manifest['version'] ?? '')
                || !preg_match('/^\d+\.\d+(?:\.\d+)?$/D', $manifest['min_php'] ?? '') || !is_array($manifest['files'] ?? null)) { throw new \RuntimeException('Invalid release manifest.'); }
            if (!isset($manifest['files']['VERSION'])) { throw new \RuntimeException('VERSION missing from manifest.'); }
            foreach ($entries as $name => $stat) {
                if (str_ends_with($name, '/') || $name === 'meteor/manifest.json') { continue; }
                if (!isset($manifest['files'][substr($name, 7)])) { throw new \RuntimeException('Unlisted zip file.'); }
            }
            foreach ($manifest['files'] as $path => $hash) {
                if (!is_string($path) || (!self::managed($path) && !in_array($path, ['storage/.gitkeep', 'public/uploads/.gitkeep'], true))
                    || $path === 'manifest.json' || !is_string($hash) || !preg_match('/^[a-f0-9]{64}$/D', $hash)) { throw new \RuntimeException('Unsafe manifest path or hash.'); }
                if (!isset($entries['meteor/' . $path])) { throw new \RuntimeException('Missing manifest file: ' . $path); }
                $input = $zip->getStream('meteor/' . $path);
                if ($input === false) { throw new \RuntimeException('Cannot read release file.'); }
                $target = $staging . '/' . $path;
                self::write($target, '');
                $output = fopen($target, 'wb'); $context = hash_init('sha256'); $length = 0;
                if (!$output) { fclose($input); throw new \RuntimeException('Cannot extract release file.'); }
                try {
                    while (!feof($input)) {
                        $chunk = fread($input, 65536);
                        if ($chunk === false) { throw new \RuntimeException('Cannot read release file.'); }
                        $length += strlen($chunk);
                        if ($length > $entries['meteor/' . $path]['size']) { throw new \RuntimeException('Expanded file exceeds declared size.'); }
                        hash_update($context, $chunk);
                        if (fwrite($output, $chunk) !== strlen($chunk)) { throw new \RuntimeException('Cannot extract release file.'); }
                    }
                } finally { fclose($input); fclose($output); }
                if (!hash_equals($hash, hash_final($context))) { throw new \RuntimeException('Manifest checksum mismatch: ' . $path); }
            }
            self::write($staging . '/manifest.json', $raw);
            if (Version::current($staging) !== $manifest['version']) { throw new \RuntimeException('VERSION does not match manifest.'); }
            return $manifest;
        } finally { $zip->close(); }
    }
    public static function write(string $file, string $bytes): void
    {
        if (!is_dir(dirname($file)) && !mkdir(dirname($file), 0755, true)) { throw new \RuntimeException('Cannot create directory for ' . basename($file)); }
        if (file_put_contents($file, $bytes) === false) { throw new \RuntimeException('Cannot write ' . basename($file)); }
    }
    public static function removeTree(string $directory): void
    {
        if (!is_dir($directory)) { return; }
        foreach (new \FilesystemIterator($directory) as $file) {
            if ($file->isDir() && !$file->isLink()) { self::removeTree($file->getPathname()); }
            else { unlink($file->getPathname()); }
        }
        rmdir($directory);
    }
}
