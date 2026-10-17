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
        if (!$user || (int) $user['session_version'] !== (int) $_SESSION['session_version']) {
            unset($_SESSION['user_id'], $_SESSION['session_version']);
            return null;
        }
        return $user;
    }

    public function limited(string $username, string $ip): bool
    {
        $row = $this->db->one('SELECT COUNT(*) AS n FROM login_attempts WHERE ip_address = ? AND username = ? AND attempted_at >= ?',
            [$ip, mb_strtolower($username), gmdate('Y-m-d H:i:s', time() - 900)]);
        return (int) $row['n'] >= 5;
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
