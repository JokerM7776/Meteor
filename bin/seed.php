#!/usr/bin/env php
<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/core/autoload.php';
$config = require dirname(__DIR__) . '/config/config.php';
try {
    $db = new Core\Database($config['database']);
    (new App\Services\DemoSeeder($db))->run(in_array('--demo-locales', $argv, true));
    echo "Demo content seeded.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Seed failed: ' . $error->getMessage() . "\n");
    exit(1);
}
