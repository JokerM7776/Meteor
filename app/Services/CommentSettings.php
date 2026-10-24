<?php
declare(strict_types=1);
namespace App\Services;
use Core\Database;

/** Site-wide comment and webmention settings (global rows in the settings table). */
final class CommentSettings
{
    public const MODES = ['hold' => 'Hold every comment for approval', 'returning' => 'Publish at once when the email already has an approved comment; hold the rest', 'open' => 'Publish every comment at once'];
    public const DEFAULTS = ['comments_enabled' => '0', 'comments_moderation' => 'hold', 'comments_captcha' => '1',
        'webmention_receive' => '1', 'webmention_send' => '0'];

    public function __construct(private Database $db) {}

    public function values(): array
    {
        $marks = implode(', ', array_fill(0, count(self::DEFAULTS), '?'));
        $rows = array_column($this->db->all("SELECT `key`, value FROM settings WHERE locale_scope = ? AND `key` IN ($marks)", ['', ...array_keys(self::DEFAULTS)]), 'value', 'key');
        return $rows + self::DEFAULTS;
    }

    public function enabled(): bool { return $this->values()['comments_enabled'] === '1'; }
    /** Webmentions are received only while comments are on, because they are shown with them. */
    public function receivesWebmentions(): bool { $v = $this->values(); return $v['comments_enabled'] === '1' && $v['webmention_receive'] === '1'; }
    public function sendsWebmentions(): bool { return $this->values()['webmention_send'] === '1'; }

    /** The CAPTCHA to show commenters: provider, site key and secret, or null when off or not set up. */
    public function captcha(): ?array
    {
        return $this->values()['comments_captcha'] === '1' ? $this->provider() : null;
    }

    /** The site-wide provider chosen on the Security page, or null while its keys are missing. */
    public function provider(): ?array { return (new CaptchaSettings($this->db))->provider(); }

    /** Comments → Settings: comments, moderation, the CAPTCHA switch and webmentions. */
    public function save(array $input): void
    {
        $flag = fn (string $key) => ($input[$key] ?? '0') === '1' ? '1' : '0';
        $values = ['comments_enabled' => $flag('comments_enabled'), 'comments_captcha' => $flag('comments_captcha'),
            'webmention_receive' => $flag('webmention_receive'), 'webmention_send' => $flag('webmention_send'),
            'comments_moderation' => (string) ($input['comments_moderation'] ?? 'hold')];
        if (!isset(self::MODES[$values['comments_moderation']])) { throw new \InvalidArgumentException('Choose how new comments are published.'); }
        $this->db->transaction(function () use ($values) {
            foreach ($values as $key => $value) { $this->db->upsert('settings', ['key' => $key, 'locale' => null, 'value' => $value], ['key', 'locale_scope']); }
        });
    }

    /** A random per-site key for signing comment forms and hashing visitor IPs; created on first use. */
    public static function secret(Database $db): string
    {
        $row = $db->one('SELECT value FROM settings WHERE `key` = ? AND locale_scope = ?', ['app_secret', '']);
        if ($row && strlen($row['value']) === 64) { return $row['value']; }
        $db->upsert('settings', ['key' => 'app_secret', 'locale' => null, 'value' => bin2hex(random_bytes(32))], ['key', 'locale_scope']);
        return $db->one('SELECT value FROM settings WHERE `key` = ? AND locale_scope = ?', ['app_secret', ''])['value'];
    }

    public static function ipHash(Database $db, string $ip): string { return $ip === '' ? '' : hash_hmac('sha256', $ip, self::secret($db)); }
}
