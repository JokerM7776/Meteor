<?php
declare(strict_types=1);
// Resets an admin password from the server shell: php bin/reset-password.php <username>
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/core/autoload.php';
$config = require dirname(__DIR__) . '/config/config.php';
try {
    $username = mb_strtolower(trim($argv[1] ?? ''));
    if ($username === '') { throw new RuntimeException('Usage: php bin/reset-password.php <username>'); }
    $db = new Core\Database($config['database']);
    if (!$db->one('SELECT id FROM users WHERE username = ?', [$username])) { throw new RuntimeException("No admin named \"$username\"."); }
    $password = getenv('NEW_PASSWORD');
    if ($password === false) {
        fwrite(STDOUT, 'New password (at least 12 characters; input is visible): ');
        $password = rtrim((string) fgets(STDIN), "\r\n");
    }
    if (mb_strlen($password) < 12 || strlen($password) > 72) { throw new RuntimeException('Passwords need at least 12 characters and at most 72 bytes.'); }
    // Bumping session_version signs out every existing session for this admin.
    $db->query('UPDATE users SET password_hash = ?, password_changed_at = ?, session_version = session_version + 1 WHERE username = ?',
        [password_hash($password, PASSWORD_DEFAULT), gmdate('Y-m-d H:i:s'), $username]);
    $db->query('DELETE FROM remember_tokens WHERE user_id = (SELECT id FROM users WHERE username = ?)', [$username]);
    $db->query('DELETE FROM login_attempts WHERE username = ?', [$username]);
    fwrite(STDOUT, "Password updated for \"$username\". Every session and remembered browser was signed out.\n");
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
