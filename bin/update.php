<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/core/autoload.php';
try {
    if (!isset($argv[1]) || count($argv) > 3 || (isset($argv[2]) && $argv[2] !== '--allow-downgrade')) { throw new RuntimeException('Usage: php bin/update.php <zip> [--allow-downgrade]'); }
    $config = require dirname(__DIR__) . '/config/config.php';
    (new Core\Updater(dirname(__DIR__), new Core\Database($config['database'])))->apply($argv[1], in_array('--allow-downgrade', $argv, true));
    echo "Update installed.\n";
} catch (Throwable $e) { fwrite(STDERR, $e->getMessage() . "\n"); exit(1); }
