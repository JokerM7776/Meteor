<?php
declare(strict_types=1);
namespace App\Services;
use Core\{Captcha, Database};

/** Site-wide comment and webmention settings (global rows in the settings table). */
final class CommentSettings
{
    public const MODES = ['hold' => 'Hold every comment for approval', 'returning' => 'Publish at once when the email already has an approved comment; hold the rest', 'open' => 'Publish every comment at once'];
    public const DEFAULTS = ['comments_enabled' => '0', 'comments_moderation' => 'hold', 'comments_captcha' => '1', 'comments_captcha_provider' => 'turnstile',
        'recaptcha_site_key' => '', 'recaptcha_secret_key' => '', 'webmention_receive' => '1', 'webmention_send' => '0'];
    private const KEY = '/^[0-9A-Za-z_-]{20,100}$/D';

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
        $values = $this->values();
        if ($values['comments_captcha'] !== '1') { return null; }
        $provider = $values['comments_captcha_provider'];
        [$site, $secret] = $provider === 'recaptcha' ? [$values['recaptcha_site_key'], $values['recaptcha_secret_key']] : array_values(array_intersect_key((new LoginProtection($this->db))->values(), ['site_key' => 1, 'secret' => 1]));
        return isset(Captcha::PROVIDERS[$provider]) && $site !== '' && $secret !== '' ? ['provider' => $provider, 'site_key' => $site, 'secret' => $secret] + Captcha::PROVIDERS[$provider] : null;
    }

    public function turnstileReady(): bool { $v = (new LoginProtection($this->db))->values(); return $v['site_key'] !== '' && $v['secret'] !== ''; }

    public function save(array $input): void
    {
        $current = $this->values();
        $flag = fn (string $key) => ($input[$key] ?? '0') === '1' ? '1' : '0';
        $values = ['comments_enabled' => $flag('comments_enabled'), 'comments_captcha' => $flag('comments_captcha'),
            'webmention_receive' => $flag('webmention_receive'), 'webmention_send' => $flag('webmention_send'),
            'comments_moderation' => (string) ($input['comments_moderation'] ?? 'hold'), 'comments_captcha_provider' => (string) ($input['comments_captcha_provider'] ?? 'turnstile'),
            'recaptcha_site_key' => trim((string) ($input['recaptcha_site_key'] ?? '')),
            // An empty secret field keeps the saved secret; it is never sent back to the browser.
            'recaptcha_secret_key' => trim((string) ($input['recaptcha_secret_key'] ?? '')) ?: $current['recaptcha_secret_key']];
        if (!empty($input['recaptcha_forget'])) { $values['recaptcha_site_key'] = $values['recaptcha_secret_key'] = ''; }
        if (!isset(self::MODES[$values['comments_moderation']])) { throw new \InvalidArgumentException('Choose how new comments are published.'); }
        if (!isset(Captcha::PROVIDERS[$values['comments_captcha_provider']])) { throw new \InvalidArgumentException('Choose a CAPTCHA provider.'); }
        foreach (['recaptcha_site_key' => 'site key', 'recaptcha_secret_key' => 'secret key'] as $key => $label) {
            if ($values[$key] !== '' && !preg_match(self::KEY, $values[$key])) { throw new \InvalidArgumentException('The reCAPTCHA ' . $label . ' looks wrong. Copy it from the Google reCAPTCHA admin console.'); }
        }
        if ($values['comments_captcha'] === '1' && $values['comments_captcha_provider'] === 'recaptcha' && ($values['recaptcha_site_key'] === '' || $values['recaptcha_secret_key'] === '')) {
            throw new \InvalidArgumentException('Enter the reCAPTCHA site key and secret key, or choose Cloudflare Turnstile.');
        }
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
