<?php
declare(strict_types=1);
namespace Core;

/** Comment CAPTCHA: Cloudflare Turnstile or Google reCAPTCHA v2. Both verify a posted token the same way. */
final class Captcha
{
    public const PROVIDERS = [
        'turnstile' => ['name' => 'Cloudflare Turnstile', 'script' => 'https://challenges.cloudflare.com/turnstile/v0/api.js',
            'verify' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify', 'field' => 'cf-turnstile-response', 'widget' => 'cf-turnstile',
            'scripts' => 'https://challenges.cloudflare.com', 'frames' => 'https://challenges.cloudflare.com'],
        'recaptcha' => ['name' => 'Google reCAPTCHA v2', 'script' => 'https://www.google.com/recaptcha/api.js',
            'verify' => 'https://www.google.com/recaptcha/api/siteverify', 'field' => 'g-recaptcha-response', 'widget' => 'g-recaptcha',
            'scripts' => 'https://www.google.com https://www.gstatic.com', 'frames' => 'https://www.google.com'],
    ];
    /** @var callable(string, array): ?array */
    private $send;

    public function __construct(private string $provider, private string $secret, ?callable $send = null)
    {
        if (!isset(self::PROVIDERS[$provider])) { throw new \InvalidArgumentException('Unknown CAPTCHA provider.'); }
        $this->send = $send ?? Turnstile::post(...);
    }

    public function field(): string { return self::PROVIDERS[$this->provider]['field']; }

    public function passes(string $token, string $ip = ''): bool
    {
        if ($token === '' || strlen($token) > 4096) { return false; }
        $fields = ['secret' => $this->secret, 'response' => $token] + ($ip !== '' ? ['remoteip' => $ip] : []);
        return ((($this->send)(self::PROVIDERS[$this->provider]['verify'], $fields))['success'] ?? false) === true;
    }
}
