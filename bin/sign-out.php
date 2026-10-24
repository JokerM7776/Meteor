<?php
declare(strict_types=1);
// Signs out every session and every "Keep me signed in" browser from the server shell:
//   php bin/sign-out.php             all admins
//   php bin/sign-out.php <username>  one admin
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/core/autoload.php';
$config = require dirname(__DIR__) . '/config/config.php';
try {
    $db = new Core\Database($config['database']);
    $username = mb_strtolower(trim($argv[1] ?? ''));
    $userId = null;
    if ($username !== '') {
        $user = $db->one('SELECT id FROM users WHERE username = ?', [$username]);
        if (!$user) { throw new RuntimeException("No admin named \"$username\"."); }
        $userId = (int) $user['id'];
    }
    $count = App\Services\Account::signOutEverywhere($db, $userId);
    fwrite(STDOUT, ($username !== '' ? "\"$username\" was" : "$count admin" . ($count === 1 ? ' was' : 's were'))
        . " signed out on every device. Sign in again with a password or passkey.\n");
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
