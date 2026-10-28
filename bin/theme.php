<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/core/autoload.php';
use App\Services\{ThemeCheck, Themes};

try {
    if (($argv[1] ?? '') === 'check' && isset($argv[2])) {
        // Buffer controller headers/output until every fixture has rendered.
        ob_start();
        try { $findings = (new ThemeCheck())->check($argv[2]); } finally { ob_end_clean(); }
        foreach ($findings as $finding) { echo str_replace(["\r", "\n"], ' ', $finding) . "\n"; }
        echo count($findings) === 1 ? "1 finding.\n" : count($findings) . " findings.\n";
        exit($findings ? 1 : 0);
    }
    if (($argv[1] ?? '') === 'new' && isset($argv[2])) {
        $root = dirname(__DIR__);
        if (is_file($root . '/storage/installed.lock') && is_file($root . '/.env')) {
            $config = require $root . '/config/config.php';
            $db = new Core\Database($config['database']);
        } else { $db = ThemeCheck::database(); }
        $theme = (new Themes($db))->create($argv[3] ?? $argv[2], $argv[2]);
        echo 'Created ' . $theme['path'] . ".\n";
        exit(0);
    }
    echo "Usage: php bin/theme.php check <folder|zip>\n       php bin/theme.php new <slug> [name]\n";
    exit(1);
} catch (Throwable $e) { fwrite(STDERR, $e->getMessage() . "\n"); exit(1); }
