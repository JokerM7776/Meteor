<?php
declare(strict_types=1);
namespace App\Services;
use Core\{Database, Turnstile};

/** Cloudflare Turnstile on the password sign-in form. Passkey sign-in is not affected. */
final class LoginProtection
{
    private const KEY = '/^[0-9A-Za-z_-]{10,200}$/D';
    /** @var ?callable */
    private $send;

    /** $allowed is false when .env sets TURNSTILE=off (the way back in after a bad key). */
    public function __construct(private Database $db, private bool $allowed = true, ?callable $send = null) { $this->send = $send; }

    public function values(): array
    {
        $rows = array_column($this->db->all("SELECT `key`, value FROM settings WHERE `key` IN ('turnstile_enabled', 'turnstile_site_key', 'turnstile_secret_key') AND locale_scope = ?", ['']), 'value', 'key');
        return ['enabled' => ($rows['turnstile_enabled'] ?? '0') === '1', 'site_key' => $rows['turnstile_site_key'] ?? '', 'secret' => $rows['turnstile_secret_key'] ?? ''];
    }
    public function allowed(): bool { return $this->allowed; }
    public function active(): bool
    {
        $values = $this->values();
        return $this->allowed && $values['enabled'] && $values['site_key'] !== '' && $values['secret'] !== '';
    }
    public function siteKey(): string { return $this->values()['site_key']; }
    public function passes(string $token, string $ip): bool
    {
        return !$this->active() || (new Turnstile($this->values()['secret'], $this->send))->passes($token, $ip);
    }

    public function save(array $input): void
    {
        $current = $this->values();
        $enabled = ($input['turnstile_enabled'] ?? '0') === '1';
        $site = trim((string) ($input['turnstile_site_key'] ?? ''));
        // An empty secret field keeps the saved secret; it is never sent back to the browser.
        $secret = trim((string) ($input['turnstile_secret_key'] ?? '')) ?: $current['secret'];
        if ($site !== '' && !preg_match(self::KEY, $site)) { throw new \InvalidArgumentException('The site key looks wrong. Copy it from Cloudflare → Turnstile.'); }
        if ($secret !== '' && !preg_match(self::KEY, $secret)) { throw new \InvalidArgumentException('The secret key looks wrong. Copy it from Cloudflare → Turnstile.'); }
        if ($enabled) {
            if ($site === '' || $secret === '') { throw new \InvalidArgumentException('Enter the site key and the secret key to turn on Turnstile.'); }
            // Ask Cloudflare about a dummy token: a bad secret is reported as invalid-input-secret.
            $reply = (new Turnstile($secret, $this->send))->check('meteor-key-check');
            if ($reply === null) { throw new \InvalidArgumentException('Could not reach Cloudflare to check the secret key. Try again later.'); }
            if (in_array('invalid-input-secret', (array) ($reply['error-codes'] ?? []), true)) { throw new \InvalidArgumentException('Cloudflare rejected the secret key.'); }
        }
        $this->db->transaction(function () use ($enabled, $site, $secret) {
            foreach (['turnstile_enabled' => $enabled ? '1' : '0', 'turnstile_site_key' => $site, 'turnstile_secret_key' => $secret] as $key => $value) {
                $this->db->upsert('settings', ['key' => $key, 'locale' => null, 'value' => $value], ['key', 'locale_scope']);
            }
        });
    }
}
