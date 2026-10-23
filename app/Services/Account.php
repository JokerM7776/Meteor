<?php
declare(strict_types=1);
namespace App\Services;
use Core\Database;

final class Account
{
    public function __construct(private Database $db, private ?LoginProtection $protection = null) {}
    public function update(array $user, string $action, array $input): void
    {
        if ($action !== 'rename' && !password_verify($input['current_password'] ?? '', $user['password_hash'])) { throw new \InvalidArgumentException('Current password is incorrect.'); }
        if ($action === 'username') {
            $name = mb_strtolower(trim($input['username'] ?? ''));
            if (!preg_match('/^[\p{L}\p{N}_.-]{1,191}$/uD', $name) || $this->db->one('SELECT id FROM users WHERE username = ? AND id <> ?', [$name, $user['id']])) { throw new \InvalidArgumentException('Choose a unique username (up to 191 characters).'); }
            $this->db->query('UPDATE users SET username = ? WHERE id = ?', [$name, $user['id']]);
        } elseif ($action === 'password') {
            $password = $input['new_password'] ?? '';
            if (mb_strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\0") || $password !== ($input['new_password_confirm'] ?? '')) { throw new \InvalidArgumentException('Passwords must match and contain at least 12 characters (at most 72 bytes).'); }
            $this->db->query('UPDATE users SET password_hash = ?, password_changed_at = ?, session_version = session_version + 1 WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), gmdate('Y-m-d H:i:s'), $user['id']]);
            $_SESSION['session_version'] = (int) $this->db->one('SELECT session_version FROM users WHERE id = ?', [$user['id']])['session_version'];
        } elseif ($action === 'turnstile') {
            ($this->protection ?? new LoginProtection($this->db))->save($input);
        } elseif ($action === 'rename' || $action === 'remove') {
            if (!$this->db->one('SELECT id FROM passkeys WHERE id = ? AND user_id = ?', [$input['id'] ?? 0, $user['id']])) { throw new \InvalidArgumentException('Passkey not found.'); }
            if ($action === 'rename') {
                $name = trim($input['name'] ?? '');
                if (!$name || mb_strlen($name) > 191) { throw new \InvalidArgumentException('Enter a name (up to 191 characters).'); }
                $this->db->query('UPDATE passkeys SET name = ? WHERE id = ? AND user_id = ?', [$name, $input['id'], $user['id']]);
            } else { $this->db->query('DELETE FROM passkeys WHERE id = ? AND user_id = ?', [$input['id'], $user['id']]); }
        } else { throw new \InvalidArgumentException('Unknown account action.'); }
    }
}
