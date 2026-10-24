<?php
declare(strict_types=1);
namespace Core;

final class Auth
{
    public function __construct(private Database $db) {}

    public function user(): ?array
    {
        if (!isset($_SESSION['user_id'], $_SESSION['session_version'])) { return null; }
        $user = $this->db->one('SELECT * FROM users WHERE id = ?', [$_SESSION['user_id']]);
        // A session started from "Keep me signed in" ends when that device is signed out on the Security page.
        $forgotten = isset($_SESSION['remember_id']) && !$this->db->one('SELECT id FROM remember_tokens WHERE id = ?', [$_SESSION['remember_id']]);
        if (!$user || (int) $user['session_version'] !== (int) $_SESSION['session_version'] || $forgotten) {
            unset($_SESSION['user_id'], $_SESSION['session_version'], $_SESSION['remember_id']);
            return null;
        }
        return $user;
    }

    public const PER_ACCOUNT = 5;
    public const PER_ADDRESS = 20;

    /** Too many failures in 15 minutes: 5 for one username from an address, or 20 for any usernames from it. */
    public function limited(string $username, string $ip): bool
    {
        $since = gmdate('Y-m-d H:i:s', time() - 900);
        $account = $this->db->one('SELECT COUNT(*) AS n FROM login_attempts WHERE ip_address = ? AND username = ? AND attempted_at >= ?', [$ip, mb_strtolower($username), $since]);
        if ((int) $account['n'] >= self::PER_ACCOUNT) { return true; }
        $address = $this->db->one('SELECT COUNT(*) AS n FROM login_attempts WHERE ip_address = ? AND attempted_at >= ?', [$ip, $since]);
        return (int) $address['n'] >= self::PER_ADDRESS;
    }

    public function failure(string $username, string $ip): void
    {
        $this->db->query('DELETE FROM login_attempts WHERE attempted_at < ?', [gmdate('Y-m-d H:i:s', time() - 900)]);
        $this->db->query('INSERT INTO login_attempts (ip_address, username) VALUES (?, ?)', [$ip, mb_strtolower(mb_substr($username, 0, 191))]);
    }

    public function password(string $username, string $password, string $ip): bool
    {
        // Usernames are stored lowercase so MySQL (case-insensitive) and SQLite (case-sensitive) agree.
        $username = mb_strtolower(mb_substr(trim($username), 0, 191));
        if ($this->limited($username, $ip)) { return false; }
        $user = $this->db->one('SELECT * FROM users WHERE username = ?', [$username]);
        $valid = password_verify($password, $user['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
        if (!$user || !$valid) { $this->failure($username, $ip); return false; }
        $this->db->query('DELETE FROM login_attempts WHERE ip_address = ? AND username = ?', [$ip, mb_strtolower($username)]);
        Session::login((int) $user['id'], (int) $user['session_version']);
        return true;
    }
}
