<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/core/autoload.php';
$config = require dirname(__DIR__) . '/config/config.php';
try {
    $user = getenv('ADMIN_USER');
    $password = getenv('ADMIN_PASS');
    if ($user === false) {
        fwrite(STDOUT, 'Admin username: ');
        $user = trim((string) fgets(STDIN));
    }
    if ($password === false) {
        fwrite(STDOUT, 'Admin password (at least 12 characters; input is visible): ');
        $password = rtrim((string) fgets(STDIN), "\r\n");
    }
    if (!preg_match('/^[\p{L}\p{N}_.-]{1,191}$/uD', $user) || mb_strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\0")) {
        throw new RuntimeException('Use a valid username and a password of at least 12 characters, at most 72 bytes.');
    }
    if ($config['database']['driver'] === 'sqlite' && $config['database']['path'] !== ':memory:') {
        $directory = dirname($config['database']['path']);
        if (!is_dir($directory) && !mkdir($directory, 0700, true)) { throw new RuntimeException('Cannot create database directory.'); }
    }
    $db = new Core\Database($config['database']);
    $tables = $db->driver() === 'sqlite'
        ? $db->all("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'")
        : $db->all('SHOW TABLES');
    if ($tables) { throw new RuntimeException('Database is not empty; installer will not overwrite existing tables.'); }
    $schema = file_get_contents(dirname(__DIR__) . '/database/schema.' . $db->driver() . '.sql');
    // Schema files contain no semicolons inside literals or comments.
    $statements = array_filter(array_map('trim', explode(';', $schema)));
    $create = static function () use ($db, $statements): void {
        foreach ($statements as $statement) { $db->query($statement); }
    };
    if ($db->driver() === 'sqlite') { $db->transaction($create); } else { $create(); }
    $db->transaction(static function () use ($db, $user, $password): void {
        $db->query('INSERT INTO locales (code, name, native_name, status, is_default, sort_order, direction) VALUES (?, ?, ?, ?, ?, ?, ?)', ['en', 'English', 'English', 'live', 1, 0, 'ltr']);
        $db->query('INSERT INTO users (username, password_hash) VALUES (?, ?)', [mb_strtolower($user), password_hash($password, PASSWORD_DEFAULT)]);
    });
    fwrite(STDOUT, "Meteor installed with English and the first admin.\n");
} catch (Throwable $error) {
    fwrite(STDERR, 'Install failed: ' . $error->getMessage() . "\n");
    exit(1);
}
