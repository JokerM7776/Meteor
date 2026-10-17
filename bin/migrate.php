<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/core/autoload.php';
$config = require dirname(__DIR__) . '/config/config.php';
try {
    $names = (new Core\Migrator(new Core\Database($config['database']), dirname(__DIR__) . '/database/migrations'))->run();
    echo count($names) . " migrations applied.\n";
} catch (Throwable $error) { fwrite(STDERR, $error->getMessage() . "\n"); exit(1); }
