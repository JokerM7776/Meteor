<?php
declare(strict_types=1);
// Shows the secret sign-in address, or moves sign-in back to /admin/login:
//   php bin/login-path.php           show it
//   php bin/login-path.php --clear   use /admin/login again
//   php bin/login-path.php <path>    set a new one
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/core/autoload.php';
$config = require dirname(__DIR__) . '/config/config.php';
try {
    $db = new Core\Database($config['database']);
    $argument = $argv[1] ?? null;
    if ($argument !== null) { App\Services\LoginPath::save($db, $argument === '--clear' ? '' : $argument); }
    $url = App\Services\LoginPath::url($db);
    fwrite(STDOUT, 'Sign-in page: ' . rtrim($config['app_url'], '/') . $url . "\n");
    if ($url !== App\Services\LoginPath::DEFAULT_URL) { fwrite(STDOUT, "Signed-out visitors to /admin get “page not found”. Run with --clear to undo.\n"); }
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
