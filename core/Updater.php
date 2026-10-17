<?php
declare(strict_types=1);
namespace Core;
final class Updater
{
    public function __construct(private string $root, private Database $db) {}
    private function target(string $path): string
    {
        if (!ReleasePackage::managed($path)) { throw new \RuntimeException('Protected update path: ' . $path); }
        $current = $this->root;
        foreach (explode('/', $path) as $part) {
            $current .= '/' . $part;
            if (is_link($current)) { throw new \RuntimeException('Refusing symlink destination.'); }
        }
        return $current;
    }
    private function lock()
    {
        ReleasePackage::requirements();
        ReleasePackage::write($this->root . '/storage/updates/.gitkeep', '');
        $lock = fopen($this->root . '/storage/updates/update.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) { throw new \RuntimeException('Another update is running.'); }
        return $lock;
    }
    private function log(string $action, string $result): void
    {
        ReleasePackage::write($this->root . '/storage/logs/.gitkeep', '');
        file_put_contents($this->root . '/storage/logs/updates.log', json_encode(['at' => gmdate('c'), 'action' => $action, 'result' => $result], JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
    }
    public function backups(): array
    {
        $files = glob($this->root . '/storage/backups/meteor-*.zip'); rsort($files, SORT_STRING);
        $stamp = static fn ($file) => preg_replace('/^meteor-\d+\.\d+\.\d+-/', '', basename($file));
        usort($files, fn ($a, $b) => strcmp($stamp($b), $stamp($a)));
        return array_map('basename', $files);
    }
    private function backup(array $paths): string
    {
        $time = microtime(true);
        $name = 'meteor-' . Version::current($this->root) . '-' . gmdate('Ymd-His', (int) $time) . '-' . sprintf('%06d', (int) (($time - floor($time)) * 1000000)) . '-' . bin2hex(random_bytes(4)) . '.zip';
        $directory = $this->root . '/storage/backups';
        if (!is_dir($directory) && !mkdir($directory, 0700, true)) { throw new \RuntimeException('Cannot create backups directory.'); }
        $zip = new \ZipArchive();
        if ($zip->open($directory . '/' . $name, \ZipArchive::CREATE) !== true) { throw new \RuntimeException('Cannot create backup.'); }
        $files = []; $missing = [];
        foreach (array_unique($paths) as $path) {
            $target = $this->target($path);
            if (is_file($target)) { $files[$path] = hash_file('sha256', $target); $zip->addFile($target, 'meteor/' . $path); }
            elseif (file_exists($target)) { $zip->close(); throw new \RuntimeException('Update target is not a file: ' . $path); }
            else { $missing[] = $path; }
        }
        $zip->addFromString('backup.json', json_encode(['files' => $files, 'missing' => $missing], JSON_THROW_ON_ERROR));
        if (!$zip->close()) { throw new \RuntimeException('Cannot finish backup.'); }
        return $name;
    }
    private function readBackup(string $name): array
    {
        if (basename($name) !== $name || !in_array($name, $this->backups(), true)) { throw new \RuntimeException('Backup not found.'); }
        $zip = new \ZipArchive();
        if ($zip->open($this->root . '/storage/backups/' . $name) !== true) { throw new \RuntimeException('Cannot open backup.'); }
        try {
            $metadata = json_decode((string) $zip->getFromName('backup.json'), true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($metadata['files'] ?? null) || !is_array($metadata['missing'] ?? null)) { throw new \RuntimeException('Invalid backup metadata.'); }
            foreach ($metadata['files'] as $path => $hash) {
                $this->target($path); $stream = $zip->getStream('meteor/' . $path);
                if ($stream === false) { throw new \RuntimeException('Missing backup file.'); }
                try { $context = hash_init('sha256'); hash_update_stream($context, $stream); $actual = hash_final($context); }
                finally { fclose($stream); }
                if (!is_string($hash) || !hash_equals($hash, $actual)) { throw new \RuntimeException('Backup checksum mismatch.'); }
            }
            foreach ($metadata['missing'] as $path) { $this->target($path); }
            return [$metadata['files'], $metadata['missing']];
        } finally { $zip->close(); }
    }
    private function restoreFiles(string $name): void
    {
        [$files, $missing] = $this->readBackup($name);
        $zip = new \ZipArchive();
        if ($zip->open($this->root . '/storage/backups/' . $name) !== true) { throw new \RuntimeException('Cannot reopen backup.'); }
        try {
            foreach ($files as $path => $hash) {
                $stream = $zip->getStream('meteor/' . $path);
                if ($stream === false) { throw new \RuntimeException('Cannot read backup file.'); }
                try { $this->replaceStream($path, $stream); } finally { fclose($stream); }
            }
        } finally { $zip->close(); }
        foreach ($missing as $path) { $this->delete($path); }
    }
    private function replaceStream(string $path, $source): void
    {
        $target = $this->target($path);
        $temporary = $target . '.update-' . bin2hex(random_bytes(6));
        try {
            ReleasePackage::write($temporary, '');
            $output = fopen($temporary, 'wb');
            if (!$output) { throw new \RuntimeException('Cannot write ' . $path); }
            try {
                if (stream_copy_to_stream($source, $output) === false) { throw new \RuntimeException('Cannot copy ' . $path); }
            } finally { fclose($output); }
            if (!chmod($temporary, is_file($target) ? fileperms($target) & 0777 : (str_ends_with($path, '.sh') ? 0755 : 0644)) || !rename($temporary, $target)) { throw new \RuntimeException('Cannot replace ' . $path); }
        } finally { if (is_file($temporary)) { unlink($temporary); } }
    }
    private function delete(string $path): void
    {
        $target = $this->target($path);
        if (is_file($target) && !unlink($target)) { throw new \RuntimeException('Cannot remove ' . $path); }
    }
    private function prune(): void
    {
        foreach (array_slice($this->backups(), 5) as $name) { unlink($this->root . '/storage/backups/' . $name); }
    }
    public function apply(string $archive, bool $allowDowngrade = false, string $checksum = ''): void
    {
        $lock = $this->lock(); $backup = null; $maintenance = false;
        $staging = $this->root . '/storage/updates/staging-' . bin2hex(random_bytes(12));
        try {
            if ($checksum !== '' && (!preg_match('/^[a-fA-F0-9]{64}$/D', $checksum) || !is_file($archive) || !hash_equals(strtolower($checksum), hash_file('sha256', $archive)))) { throw new \RuntimeException('Update checksum mismatch.'); }
            $manifest = ReleasePackage::extract($archive, $staging);
            if (!$allowDowngrade && version_compare($manifest['version'], Version::current($this->root), '<')) { throw new \RuntimeException('Downgrade requires the allow downgrade checkbox or CLI flag.'); }
            if (version_compare($manifest['min_php'], PHP_VERSION, '>')) { throw new \RuntimeException('This release requires PHP ' . $manifest['min_php'] . '.'); }
            $old = is_file($this->root . '/manifest.json') ? json_decode(file_get_contents($this->root . '/manifest.json'), true, 32, JSON_THROW_ON_ERROR) : ['files' => []];
            $paths = array_values(array_filter(array_keys($manifest['files']), ReleasePackage::managed(...))); $paths[] = 'manifest.json';
            $removed = array_values(array_diff(array_filter(array_keys($old['files']), ReleasePackage::managed(...)), $paths));
            foreach (array_merge($paths, $removed) as $path) { $this->target($path); }
            ReleasePackage::write($this->root . '/storage/maintenance.flag', gmdate('c')); $maintenance = true;
            $backup = $this->backup(array_merge($paths, $removed));
            foreach ($paths as $path) {
                $stream = fopen($staging . '/' . $path, 'rb');
                if (!$stream) { throw new \RuntimeException('Cannot read staged file.'); }
                try { $this->replaceStream($path, $stream); } finally { fclose($stream); }
            }
            foreach ($removed as $path) { $this->delete($path); }
            (new Migrator($this->db, $this->root . '/database/migrations'))->run();
            if (function_exists('opcache_reset')) { opcache_reset(); }
            $this->log('update ' . $manifest['version'], 'success'); $this->prune();
        } catch (\Throwable $error) {
            if ($backup !== null) {
                try { $this->restoreFiles($backup); if (function_exists('opcache_reset')) { opcache_reset(); } }
                catch (\Throwable $rollback) { $this->log('rollback', $rollback->getMessage()); throw new \RuntimeException('Update failed and automatic file restore failed. Restore backup ' . $backup . ' manually. ' . $rollback->getMessage(), 0, $error); }
            }
            $this->log('update', 'failed: ' . $error->getMessage()); throw $error;
        } finally {
            if ($maintenance && is_file($this->root . '/storage/maintenance.flag')) { unlink($this->root . '/storage/maintenance.flag'); }
            ReleasePackage::removeTree($staging); flock($lock, LOCK_UN); fclose($lock);
        }
    }
    public function restore(string $name): void
    {
        $lock = $this->lock(); $rescue = null; $maintenance = false;
        try {
            [$files, $missing] = $this->readBackup($name);
            ReleasePackage::write($this->root . '/storage/maintenance.flag', gmdate('c')); $maintenance = true;
            $rescue = $this->backup(array_merge(array_keys($files), $missing));
            $this->restoreFiles($name);
            if (function_exists('opcache_reset')) { opcache_reset(); }
            $this->log('restore ' . $name, 'success'); $this->prune();
        } catch (\Throwable $error) {
            if ($rescue) { $this->restoreFiles($rescue); }
            $this->log('restore ' . $name, 'failed: ' . $error->getMessage()); throw $error;
        } finally {
            if ($maintenance && is_file($this->root . '/storage/maintenance.flag')) { unlink($this->root . '/storage/maintenance.flag'); }
            flock($lock, LOCK_UN); fclose($lock);
        }
    }
}
