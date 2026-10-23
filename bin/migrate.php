<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/core/autoload.php';
$config = require dirname(__DIR__) . '/config/config.php';
try {
    $db = new Core\Database($config['database']);
    $names = (new Core\Migrator($db, dirname(__DIR__) . '/database/migrations'))->run();
    echo count($names) . " migrations applied.\n";
    $added = App\Services\LanguagePacks::sync($db);
    if ($added) { echo 'Languages added (Off): ' . implode(', ', $added) . "\n"; }
} catch (Throwable $error) { fwrite(STDERR, $error->getMessage() . "\n"); exit(1); }
