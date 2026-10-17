<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/core/autoload.php';
$config = require dirname(__DIR__) . '/config/config.php';
try {
    $db = new Core\Database($config['database']);
    foreach (['post_translations', 'page_translations'] as $table) {
        if (!$db->hasColumn($table, 'published_at')) { $db->query("ALTER TABLE $table ADD COLUMN published_at DATETIME"); }
        $db->query("UPDATE $table SET published_at = updated_at WHERE status = ? AND published_at IS NULL", ['published']);
    }
    $db->query('UPDATE users SET username = LOWER(username) WHERE username <> LOWER(username)');
    echo "Phase 3 schema is up to date.\n";
} catch (Throwable $error) { fwrite(STDERR, $error->getMessage() . "\n"); exit(1); }
