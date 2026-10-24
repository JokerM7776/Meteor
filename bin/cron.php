<?php
declare(strict_types=1);
// Background tasks. Run every 10 minutes, e.g. crontab: */10 * * * * php /path/to/meteor/bin/cron.php
// Checks received webmentions, sends queued ones (including for scheduled posts) and retries failures.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/core/autoload.php';
$config = require dirname(__DIR__) . '/config/config.php';
$GLOBALS['config'] = $config;
try {
    $db = new Core\Database($config['database']);
    $done = (new App\Services\Webmentions($db, $config['app_url']))->process(50);
    // Old queue rows and old spam are not needed any more.
    $db->query("DELETE FROM webmentions WHERE status <> 'queued' AND updated_at < ?", [gmdate('Y-m-d H:i:s', time() - 90 * 86400)]);
    $db->query("DELETE FROM comments WHERE status = 'spam' AND created_at < ?", [gmdate('Y-m-d H:i:s', time() - 30 * 86400)]);
    echo "Webmentions processed: $done\n";
} catch (Throwable $error) { fwrite(STDERR, $error->getMessage() . "\n"); exit(1); }
