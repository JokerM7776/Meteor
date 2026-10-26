<?php
declare(strict_types=1);
namespace App\Services;
use Core\Database;

/**
 * An optional secret address for the sign-in page, such as /studio-7k2m. While one is set, signed-out
 * visitors get the site's ordinary 404 page for every /admin URL, so bots can't find the login form.
 */
final class LoginPath
{
    public const KEY = 'login_path';
    public const DEFAULT_URL = '/admin/login';
    /** Passkey sign-in calls these from the sign-in page; they open only after that page was visited. */
    public const PASSKEY = ['/admin/passkey/login/options', '/admin/passkey/login/verify'];

    /** The saved path without slashes, or '' when the sign-in page stays at /admin/login. */
    public static function current(Database $db): string
    {
        $path = (string) ($db->one('SELECT value FROM settings WHERE `key` = ? AND locale_scope = ?', [self::KEY, ''])['value'] ?? '');
        try { return self::normalize($path); } catch (\InvalidArgumentException) { return ''; }
    }

    public static function url(Database $db): string
    {
        $path = self::current($db);
        return $path === '' ? self::DEFAULT_URL : '/' . $path;
    }

    /** Lower-cases and checks a path; '' turns the feature off. */
    public static function normalize(string $input): string
    {
        $path = strtolower(trim(trim($input), '/'));
        if ($path === '') { return ''; }
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{6,62}[a-z0-9]$/D', $path)) {
            throw new \InvalidArgumentException('Use 8 to 64 letters, digits, hyphens or underscores for the sign-in address.');
        }
        // A path shaped like a language code (ja, pt-br, zh-hans-cn …) could clash with a language added later.
        if (preg_match('/^[a-z]{2,3}(-[a-z0-9]{2,8})*$/D', $path)) {
            throw new \InvalidArgumentException('That sign-in address looks like a language code. Add a digit or a longer first word.');
        }
        if ($path === 'webmention' || file_exists(dirname(__DIR__, 2) . '/public/' . $path)) {
            throw new \InvalidArgumentException('That sign-in address is already used by the site. Choose another.');
        }
        return $path;
    }

    public static function save(Database $db, string $input): string
    {
        $path = self::normalize($input);
        $db->upsert('settings', ['key' => self::KEY, 'locale' => null, 'value' => $path], ['key', 'locale_scope']);
        return $path;
    }

    /** A random suggestion for the empty field. */
    public static function suggestion(): string { return 'sign-in-' . bin2hex(random_bytes(4)); }
}
