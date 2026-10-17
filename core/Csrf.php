<?php
declare(strict_types=1);
namespace Core;

final class Csrf
{
    public static function token(): string
    {
        return $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
    }

    public static function field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . \e(self::token()) . '">';
    }

    public static function verify(mixed $token): bool
    {
        return is_string($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    /** Supply the form value or X-CSRF-Token header explicitly. */
    public static function requireValid(mixed $token): void
    {
        if (!self::verify($token)) {
            throw new \RuntimeException('Invalid CSRF token.', 403);
        }
    }
}
