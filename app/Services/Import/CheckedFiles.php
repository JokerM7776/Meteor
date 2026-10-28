<?php
declare(strict_types=1);
namespace App\Services\Import;

/** Private, short-lived admin uploads. Session state contains a token, never a filesystem path. */
final class CheckedFiles
{
    public const LIFETIME = 7200;
    private const NAMES = ['archive' => 'source.zip', 'sqlite' => 'source.db', 'uploads' => 'uploads.zip'];
    public function __construct(private string $root) {}
    public function create(array $files): string
    {
        if (!is_dir($this->root) && !mkdir($this->root, 0700, true)) { throw new \RuntimeException('Cannot create the import folder.'); }
        $token = bin2hex(random_bytes(16)); $directory = $this->path($token);
        if (!mkdir($directory, 0700)) { throw new \RuntimeException('Cannot store the import files.'); }
        try {
            foreach (self::NAMES as $key => $name) {
                if (empty($files[$key])) { continue; }
                if (!copy($files[$key], $directory . '/' . $name) || !chmod($directory . '/' . $name, 0600)) { throw new \RuntimeException('Cannot store the import files.'); }
            }
        } catch (\Throwable $error) { $this->remove($token); throw $error; }
        return $token;
    }
    public function input(string $token, string $source, array $options): array
    {
        if (!$this->exists($token)) { throw new \InvalidArgumentException('The checked files expired. Upload the files and Check again.'); }
        $directory = $this->path($token);
        if ($source === 'hexo') { $options['file'] = $directory . '/source.zip'; }
        elseif (($options['driver'] ?? 'mysql') === 'sqlite') { $options['file'] = $directory . '/source.db'; }
        if (is_file($directory . '/uploads.zip')) { $options['uploads'] = $directory . '/uploads.zip'; }
        return $options;
    }
    public function exists(string $token): bool { return preg_match('/^[a-f0-9]{32}$/D', $token) && !is_link($this->path($token)) && is_dir($this->path($token)); }
    private function path(string $token): string
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $token)) { throw new \InvalidArgumentException('Invalid checked import token.'); }
        return $this->root . '/' . $token;
    }
    public function remove(string $token): void
    {
        if (!$this->exists($token)) { return; }
        $directory = $this->path($token);
        foreach (glob($directory . '/*') ?: [] as $file) { if (is_file($file) || is_link($file)) { unlink($file); } }
        rmdir($directory);
    }
    public function prune(): void
    {
        foreach (glob($this->root . '/*', GLOB_ONLYDIR) ?: [] as $directory) {
            $token = basename($directory);
            if ($this->exists($token) && filemtime($directory) < time() - self::LIFETIME) { $this->remove($token); }
        }
    }
    public static function options(array $input): array
    {
        $options = [];
        foreach (['driver', 'host', 'port', 'database', 'user', 'prefix', 'site', 'locale', 'conflict', 'drafts'] as $key) {
            if (is_string($input[$key] ?? null)) { $options[$key] = $input[$key]; }
        }
        return $options;
    }
}
