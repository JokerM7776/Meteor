<?php
declare(strict_types=1);
namespace Core;

final class Session
{
    public static function start(string $name, bool $secure): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_name($name);
        session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $secure,
            'httponly' => true, 'samesite' => 'Lax']);
        session_cache_limiter('');
        if (!session_start()) {
            throw new \RuntimeException('Could not start session.');
        }
    }

    /** Call only after credentials have been verified. */
    public static function login(int $userId, int $sessionVersion): void
    {
        session_regenerate_id(true);
        $_SESSION = ['user_id' => $userId, 'session_version' => $sessionVersion];
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
    }
}
