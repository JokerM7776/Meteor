<?php
declare(strict_types=1);
namespace Core;

/**
 * "Keep me signed in": a long-lived cookie that starts a new session when the old one has expired.
 * The cookie is "selector:secret"; only the SHA-256 of the secret is stored. Signing out deletes the
 * row, and a password change (session_version) makes every older row useless. A session that came
 * from a row keeps its id in $_SESSION['remember_id'], so removing the row ends that session too.
 */
final class Remember
{
    public const DAYS = 30;

    public function __construct(private Database $db, private string $cookie, private bool $secure) {}

    /** The cookie sits next to the session cookie and shares its Secure flag. */
    public static function forRequest(Database $db, array $config): self
    {
        $secure = parse_url((string) ($config['app_url'] ?? ''), PHP_URL_SCHEME) === 'https' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        return new self($db, ($config['session_name'] ?? 'meteor_session') . '_remember', $secure);
    }

    public function present(): bool { return is_string($_COOKIE[$this->cookie] ?? null); }

    /** Call after a successful sign-in when the visitor ticked "Keep me signed in". */
    public function issue(int $userId, int $sessionVersion): void
    {
        $this->db->query('DELETE FROM remember_tokens WHERE expires_at < ?', [gmdate('Y-m-d H:i:s')]);
        [$selector, $secret] = [bin2hex(random_bytes(12)), bin2hex(random_bytes(32))];
        $expires = time() + self::DAYS * 86400;
        $now = gmdate('Y-m-d H:i:s');
        $_SESSION['remember_id'] = $this->db->insert('INSERT INTO remember_tokens (user_id, selector, validator_hash, session_version, user_agent, expires_at, last_used_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$userId, $selector, hash('sha256', $secret), $sessionVersion, mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255), gmdate('Y-m-d H:i:s', $expires), $now]);
        $this->send($selector . ':' . $secret, $expires);
    }

    /** Signs the visitor in from the cookie. The session must already be started. */
    public function resume(): bool
    {
        $value = $_COOKIE[$this->cookie] ?? null;
        if (!is_string($value)) { return false; }
        $row = preg_match('/^([0-9a-f]{24}):([0-9a-f]{64})$/D', $value, $m)
            ? $this->db->one('SELECT t.*, u.session_version AS user_version FROM remember_tokens t JOIN users u ON u.id = t.user_id WHERE t.selector = ?', [$m[1]])
            : null;
        $valid = $row && hash_equals($row['validator_hash'], hash('sha256', $m[2]))
            && $row['expires_at'] > gmdate('Y-m-d H:i:s') && (int) $row['session_version'] === (int) $row['user_version'];
        if (!$valid) {
            if ($row) { $this->db->query('DELETE FROM remember_tokens WHERE id = ?', [$row['id']]); }
            $this->clear();
            return false;
        }
        Session::login((int) $row['user_id'], (int) $row['session_version']);
        $_SESSION['remember_id'] = (int) $row['id'];
        $this->db->query('UPDATE remember_tokens SET last_used_at = ? WHERE id = ?', [gmdate('Y-m-d H:i:s'), $row['id']]);
        return true;
    }

    /** Remembered browsers of one admin, newest first, with a short device name. */
    public function devices(int $userId): array
    {
        $rows = $this->db->all('SELECT id, user_agent, created_at, last_used_at, expires_at FROM remember_tokens WHERE user_id = ? AND expires_at > ? ORDER BY created_at DESC, id DESC',
            [$userId, gmdate('Y-m-d H:i:s')]);
        foreach ($rows as &$row) { $row['device'] = self::device($row['user_agent']); $row['current'] = (int) $row['id'] === (int) ($_SESSION['remember_id'] ?? 0); }
        return $rows;
    }

    /** "Safari on iPhone" from a user agent; good enough to tell your own devices apart. */
    public static function device(string $agent): string
    {
        $browser = match (true) { str_contains($agent, 'Edg/') => 'Edge', str_contains($agent, 'OPR/') => 'Opera', str_contains($agent, 'Firefox/') || str_contains($agent, 'FxiOS') => 'Firefox',
            str_contains($agent, 'Chrome/') || str_contains($agent, 'CriOS') => 'Chrome', str_contains($agent, 'Safari/') => 'Safari', default => '' };
        $system = match (true) { str_contains($agent, 'iPhone') => 'iPhone', str_contains($agent, 'iPad') => 'iPad', str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Mac OS X') => 'Mac', str_contains($agent, 'Windows') => 'Windows', str_contains($agent, 'CrOS') => 'ChromeOS', str_contains($agent, 'Linux') => 'Linux', default => '' };
        return $browser !== '' && $system !== '' ? "$browser on $system" : ($browser . $system ?: 'Unknown browser');
    }

    /** Sign-out: forget this browser. */
    public function forget(): void
    {
        $value = $_COOKIE[$this->cookie] ?? null;
        if (is_string($value) && preg_match('/^([0-9a-f]{24}):/', $value, $m)) {
            $this->db->query('DELETE FROM remember_tokens WHERE selector = ?', [$m[1]]);
        }
        $this->clear();
    }

    private function clear(): void
    {
        if ($this->present()) { $this->send('', time() - 3600); unset($_COOKIE[$this->cookie]); }
    }

    private function send(string $value, int $expires): void
    {
        if (headers_sent()) { return; }
        setcookie($this->cookie, $value, ['expires' => $expires, 'path' => '/', 'secure' => $this->secure, 'httponly' => true, 'samesite' => 'Lax']);
        if ($value !== '') { $_COOKIE[$this->cookie] = $value; }
    }
}
