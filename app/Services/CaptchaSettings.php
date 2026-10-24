<?php
declare(strict_types=1);
namespace App\Services;
use Core\{Captcha, Database};

/**
 * The site's CAPTCHA: keys for Cloudflare Turnstile and Google reCAPTCHA v2, and the one provider the
 * whole site uses (sign-in page and comments). Passkey sign-in never shows it.
 * The setting turnstile_enabled is the sign-in switch; it kept its name from when only Turnstile existed.
 */
final class CaptchaSettings
{
    public const DEFAULTS = ['captcha_provider' => 'turnstile', 'turnstile_enabled' => '0', 'turnstile_site_key' => '', 'turnstile_secret_key' => '',
        'recaptcha_site_key' => '', 'recaptcha_secret_key' => ''];
    private const KEY = ['turnstile' => '/^[0-9A-Za-z_-]{10,200}$/D', 'recaptcha' => '/^[0-9A-Za-z_-]{20,100}$/D'];
    /** @var ?callable */
    private $send;

    /** $allowed is false when .env sets CAPTCHA=off (the way back in after a bad key). */
    public function __construct(private Database $db, private bool $allowed = true, ?callable $send = null) { $this->send = $send; }

    public function values(): array
    {
        $keys = [...array_keys(self::DEFAULTS), 'comments_captcha_provider'];
        $marks = implode(', ', array_fill(0, count($keys), '?'));
        $rows = array_column($this->db->all("SELECT `key`, value FROM settings WHERE locale_scope = ? AND `key` IN ($marks)", ['', ...$keys]), 'value', 'key');
        // Older sites chose a provider for comments only; that choice becomes the site-wide one.
        $rows['captcha_provider'] ??= $rows['comments_captcha_provider'] ?? 'turnstile';
        if (!isset(Captcha::PROVIDERS[$rows['captcha_provider']])) { $rows['captcha_provider'] = 'turnstile'; }
        return array_intersect_key($rows, self::DEFAULTS) + self::DEFAULTS;
    }

    public function ready(string $provider, ?array $values = null): bool
    {
        $values ??= $this->values();
        return ($values[$provider . '_site_key'] ?? '') !== '' && ($values[$provider . '_secret_key'] ?? '') !== '';
    }

    /** The chosen provider with its keys, or null while its keys are missing. */
    public function provider(): ?array
    {
        $values = $this->values(); $code = $values['captcha_provider'];
        if (!$this->ready($code, $values)) { return null; }
        return ['provider' => $code, 'site_key' => $values[$code . '_site_key'], 'secret' => $values[$code . '_secret_key']] + Captcha::PROVIDERS[$code];
    }

    public function allowed(): bool { return $this->allowed; }
    public function signInEnabled(): bool { return $this->values()['turnstile_enabled'] === '1'; }
    /** The CAPTCHA for the password form of the sign-in page, or null when it is off. */
    public function signIn(): ?array { return $this->allowed && $this->signInEnabled() ? $this->provider() : null; }

    public function passes(array $input, string $ip): bool
    {
        $captcha = $this->signIn();
        if ($captcha === null) { return true; }
        $token = $input[$captcha['field']] ?? '';
        return is_string($token) && (new Captcha($captcha['provider'], $captcha['secret'], $this->send))->passes($token, $ip);
    }

    public function save(array $input): void
    {
        $current = $this->values();
        $values = ['captcha_provider' => (string) ($input['captcha_provider'] ?? 'turnstile'), 'turnstile_enabled' => ($input['turnstile_enabled'] ?? '0') === '1' ? '1' : '0'];
        if (!isset(Captcha::PROVIDERS[$values['captcha_provider']])) { throw new \InvalidArgumentException('Choose a CAPTCHA provider.'); }
        foreach (Captcha::PROVIDERS as $code => $info) {
            $site = trim((string) ($input[$code . '_site_key'] ?? ''));
            // An empty secret field keeps the saved secret; it is never sent back to the browser.
            $secret = trim((string) ($input[$code . '_secret_key'] ?? '')) ?: $current[$code . '_secret_key'];
            if (!empty($input[$code . '_forget'])) { $site = $secret = ''; }
            foreach (['site key' => $site, 'secret key' => $secret] as $label => $value) {
                if ($value !== '' && !preg_match(self::KEY[$code], $value)) { throw new \InvalidArgumentException("The {$info['name']} $label looks wrong. Copy it from the {$info['company']} dashboard."); }
            }
            $values += [$code . '_site_key' => $site, $code . '_secret_key' => $secret];
        }
        $chosen = Captcha::PROVIDERS[$values['captcha_provider']];
        $anyKey = implode('', array_diff_key($values, ['captcha_provider' => 1, 'turnstile_enabled' => 1])) !== '';
        if (!$this->ready($values['captcha_provider'], $values) && ($anyKey || $values['turnstile_enabled'] === '1')) {
            throw new \InvalidArgumentException("Enter the {$chosen['name']} site key and secret key, or choose the other provider.");
        }
        if ($values['turnstile_enabled'] === '1') {
            $accepted = (new Captcha($values['captcha_provider'], $values[$values['captcha_provider'] . '_secret_key'], $this->send))->secretAccepted();
            if ($accepted === null) { throw new \InvalidArgumentException("Could not reach {$chosen['company']} to check the secret key. Try again later."); }
            if (!$accepted) { throw new \InvalidArgumentException("{$chosen['company']} rejected the secret key."); }
        }
        $this->db->transaction(function () use ($values) {
            foreach ($values as $key => $value) { $this->db->upsert('settings', ['key' => $key, 'locale' => null, 'value' => $value], ['key', 'locale_scope']); }
        });
    }
}
