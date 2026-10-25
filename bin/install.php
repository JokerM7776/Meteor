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
    (new Core\Installer(dirname(__DIR__)))->install($config['database'], $config['app_url'], 'Meteor Blog', $user, $password);
    fwrite(STDOUT, "Meteor installed with English and the first admin.\n");
} catch (Throwable $error) {
    fwrite(STDERR, 'Install failed: ' . $error->getMessage() . "\n");
    exit(1);
}
