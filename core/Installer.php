<?php
declare(strict_types=1);
namespace Core;
final class Installer
{
    public function __construct(private string $root) {}
    public function installed(): bool { return is_file($this->root . '/.env') && is_file($this->root . '/storage/installed.lock'); }
    public function requirements(): array
    {
        $checks = ['PHP >= 8.2' => version_compare(PHP_VERSION, '8.2', '>=')];
        foreach (['pdo', 'openssl', 'mbstring', 'intl', 'fileinfo', 'zip', 'curl'] as $extension) { $checks['ext-' . $extension] = extension_loaded($extension); }
        $checks['PDO MySQL or SQLite'] = extension_loaded('pdo_mysql') || extension_loaded('pdo_sqlite');
        foreach (['' => '.env location', '/storage' => 'storage', '/public/uploads' => 'public/uploads'] as $path => $label) { $checks['Writable ' . $label] = is_writable($this->root . $path); }
        return $checks;
    }
    public function connect(array $config): Database
    {
        try {
            if (($config['driver'] ?? '') === 'sqlite' && ($config['path'] ?? '') === ':memory:') { throw new \InvalidArgumentException('Installation requires a persistent SQLite file.'); }
            return new Database($config);
        }
        catch (\Throwable $e) { throw new \RuntimeException('Database connection failed. Check the driver, address, credentials and database permissions.', 0, $e); }
    }
    /**
     * Installs from ADMIN_USER / ADMIN_PASS in .env, for hosts whose firewall blocks the setup form.
     * The database settings come from the same .env. Both keys are removed from .env afterwards.
     */
    public function installFromEnv(string $fallbackUrl): bool
    {
        $env = Env::load($this->root . '/.env');
        $get = static fn (string $key): string => getenv($key) === false ? (string) ($env[$key] ?? '') : (string) getenv($key);
        if ($get('ADMIN_USER') === '' || $get('ADMIN_PASS') === '') { return false; }
        $config = (static fn (string $file): array => require $file)($this->root . '/config/config.php');
        $this->install($config['database'], $get('APP_URL') ?: $fallbackUrl, $get('SITE_TITLE') ?: 'Meteor', $get('ADMIN_USER'), $get('ADMIN_PASS'));
        return true;
    }
    public function install(array $config, string $url, string $title, string $username, string $password): Database
    {
        if (in_array(false, $this->requirements(), true)) { throw new \RuntimeException('Installation requirements are not met. Enable the missing extensions (including ext-zip and ext-curl) and fix directory permissions.'); }
        $username = mb_strtolower(trim($username));
        if (!preg_match('/^[\p{L}\p{N}_.-]{1,191}$/uD', $username) || mb_strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\0")) { throw new \InvalidArgumentException('Use a valid username and a password of at least 12 characters, at most 72 bytes.'); }
        if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true) || parse_url($url, PHP_URL_USER) || parse_url($url, PHP_URL_QUERY) || parse_url($url, PHP_URL_FRAGMENT)) { throw new \InvalidArgumentException('Enter a valid HTTP or HTTPS site URL.'); }
        $lock = fopen($this->root . '/storage/install.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) { throw new \RuntimeException('Another installation is running.'); }
        try {
            $db = $this->connect($config);
            $tables = $db->driver() === 'sqlite' ? $db->all("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'") : $db->all('SHOW TABLES');
            if ($tables) { throw new \RuntimeException('Database is not empty; installer will not overwrite existing tables.'); }
            if ($this->installed()) { throw new \RuntimeException('Meteor is already installed.'); }
            $env = ['APP_URL' => rtrim($url, '/'), 'DB_DRIVER' => $config['driver'], 'DB_PATH' => $config['path'] ?? '', 'DB_HOST' => $config['host'] ?? '', 'DB_PORT' => $config['port'] ?? '3306', 'DB_NAME' => $config['name'] ?? '', 'DB_USER' => $config['user'] ?? '', 'DB_PASS' => $config['password'] ?? '', 'WEBAUTHN_RP_ID' => parse_url($url, PHP_URL_HOST)];
            $env += Env::load($this->root . '/.env');
            unset($env['ADMIN_USER'], $env['ADMIN_PASS']);
            $text = '';
            foreach ($env as $key => $value) { $text .= $key . '="' . strtr((string) $value, ['\\' => '\\\\', '"' => '\\"', "\n" => '\\n', "\r" => '\\r', "\t" => '\\t']) . "\"\n"; }
            $temp = $this->root . '/.env.install-' . bin2hex(random_bytes(8));
            try {
                $mask = umask(0077);
                try {
                    if (file_put_contents($temp, $text) === false || !chmod($temp, 0600)) { throw new \RuntimeException('Cannot write private .env file.'); }
                } finally { umask($mask); }
                $create = function () use ($db, $username, $password, $title): void {
                    foreach (array_filter(array_map('trim', explode(';', file_get_contents($this->root . '/database/schema.' . $db->driver() . '.sql')))) as $sql) { $db->query($sql); }
                    $db->query('INSERT INTO locales (code, name, native_name, status, is_default, sort_order, direction) VALUES (?, ?, ?, ?, ?, ?, ?)', ['en', 'English', 'English', 'live', 1, 0, 'ltr']);
                    $db->query('INSERT INTO users (username, password_hash) VALUES (?, ?)', [$username, password_hash($password, PASSWORD_DEFAULT)]);
                    $db->upsert('settings', ['key' => 'site_title', 'locale' => 'en', 'value' => $title], ['key', 'locale_scope']);
                    (new \App\Repositories\Menus($db))->ensureDefaults();
                    \App\Services\LanguagePacks::sync($db, $this->root . '/lang');
                    (new Migrator($db, $this->root . '/database/migrations'))->markAll();
                };
                if ($db->driver() === 'sqlite') { $db->transaction($create); } else { $create(); }
                if (!rename($temp, $this->root . '/.env') || file_put_contents($this->root . '/storage/installed.lock', gmdate('c')) === false) { throw new \RuntimeException('Cannot finish installation. Check filesystem permissions.'); }
            } finally { if (is_file($temp)) { unlink($temp); } }
            return $db;
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }
}
