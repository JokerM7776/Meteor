<?php
declare(strict_types=1);
namespace App\Services\Import;

/** Indexed, bounded reads; archives are never extracted and directory symlinks are never followed. */
final class Files
{
    public const MAX_TOTAL = 536870912;
    private array $entries = [];
    private array $directories = [];
    private ?\ZipArchive $zip = null;
    public function __construct(string $path)
    {
        $total = 0; $count = 0;
        if (is_dir($path)) {
            $root = realpath($path);
            $iterator = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
                fn ($file) => !$file->isLink() && self::safe(substr($file->getPathname(), strlen($root) + 1)) !== null), \RecursiveIteratorIterator::SELF_FIRST);
            foreach ($iterator as $file) {
                $this->limit(++$count, $total);
                if ($file->isDir()) { $this->directories[] = substr($file->getPathname(), strlen($root) + 1) . '/'; continue; }
                if (!$file->isFile()) { continue; }
                $name = self::safe(substr($file->getPathname(), strlen($root) + 1));
                $this->limit($count, $total += $file->getSize());
                $this->entries[$name] = [$file->getPathname(), $file->getSize()];
            }
        } else {
            if (!class_exists(\ZipArchive::class)) { throw new \RuntimeException('Enable ext-zip to read zip files.'); }
            $zip = new \ZipArchive();
            if ($zip->open($path) !== true) { throw new \RuntimeException('Cannot open the zip file.'); }
            $this->zip = $zip;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i); $this->limit(++$count, $total += $stat['size']);
                $name = self::safe($stat['name']);
                if ($name !== null && str_ends_with($name, '/')) { $this->directories[] = $name; }
                if ($name !== null && !str_ends_with($name, '/')) { $this->entries[$name] = [$i, $stat['size']]; }
            }
        }
        ksort($this->entries);
    }
    public function __destruct() { $this->zip?->close(); }
    private function limit(int $count, int $size): void
    {
        if ($count > 20000 || $size > self::MAX_TOTAL) { throw new \RuntimeException('Import exceeds 20,000 entries or 512 MB uncompressed.'); }
    }
    public static function safe(string $path): ?string
    {
        $path = str_replace('\\', '/', $path);
        if (str_contains($path, "\0") || preg_match('~^(?:/|[A-Za-z]:)~', $path)) { return null; }
        foreach (explode('/', $path) as $part) {
            if (str_starts_with($part, '.') || in_array($part, ['node_modules', 'themes', 'public', 'scaffolds', '__MACOSX', '_data'], true)) { return null; }
        }
        return $path;
    }
    public function directories(): array { return $this->directories; }
    public function names(): array { return array_keys($this->entries); }
    public function has(string $name): bool { return isset($this->entries[$name]); }
    public function read(string $name, int $limit = 5242880): ?string
    {
        if (!isset($this->entries[$name])) { return null; }
        [$ref, $size] = $this->entries[$name];
        if ($size > $limit) { throw new \RuntimeException('File exceeds the import size limit: ' . $name); }
        $bytes = $this->zip ? $this->zip->getFromIndex($ref, $limit + 1) : file_get_contents($ref, false, null, 0, $limit + 1);
        if ($bytes === false || strlen($bytes) > $limit) { throw new \RuntimeException('Cannot read file: ' . $name); }
        return $bytes;
    }
    public static function join(string $path): string
    {
        $parts = [];
        foreach (explode('/', str_replace('\\', '/', $path)) as $part) {
            if ($part === '..') { array_pop($parts); } elseif ($part !== '' && $part !== '.') { $parts[] = $part; }
        }
        return implode('/', $parts);
    }
}
