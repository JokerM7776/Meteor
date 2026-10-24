<?php
declare(strict_types=1);
namespace Core;

/** Cloudflare Turnstile or Google reCAPTCHA v2. Both verify a posted token the same way. */
final class Captcha
{
    public const PROVIDERS = [
        'turnstile' => ['name' => 'Cloudflare Turnstile', 'company' => 'Cloudflare', 'script' => 'https://challenges.cloudflare.com/turnstile/v0/api.js',
            'verify' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify', 'field' => 'cf-turnstile-response', 'widget' => 'cf-turnstile',
            'scripts' => 'https://challenges.cloudflare.com', 'frames' => 'https://challenges.cloudflare.com'],
        'recaptcha' => ['name' => 'Google reCAPTCHA v2', 'company' => 'Google', 'script' => 'https://www.google.com/recaptcha/api.js',
            'verify' => 'https://www.google.com/recaptcha/api/siteverify', 'field' => 'g-recaptcha-response', 'widget' => 'g-recaptcha',
            'scripts' => 'https://www.google.com https://www.gstatic.com', 'frames' => 'https://www.google.com'],
    ];
    /** @var callable(string, array): ?array */
    private $send;

    public function __construct(private string $provider, private string $secret, ?callable $send = null)
    {
        if (!isset(self::PROVIDERS[$provider])) { throw new \InvalidArgumentException('Unknown CAPTCHA provider.'); }
        $this->send = $send ?? self::post(...);
    }

    /** The provider's decoded reply, or null when it could not be reached. */
    public function check(string $token, string $ip = ''): ?array
    {
        $fields = ['secret' => $this->secret, 'response' => $token] + ($ip !== '' ? ['remoteip' => $ip] : []);
        return ($this->send)(self::PROVIDERS[$this->provider]['verify'], $fields);
    }

    public function passes(string $token, string $ip = ''): bool
    {
        if ($token === '' || strlen($token) > 4096) { return false; }
        return ($this->check($token, $ip)['success'] ?? false) === true;
    }

    /** Asks about a dummy token: both providers report a wrong secret as invalid-input-secret. */
    public function secretAccepted(): ?bool
    {
        $reply = $this->check('meteor-key-check');
        return $reply === null ? null : !in_array('invalid-input-secret', (array) ($reply['error-codes'] ?? []), true);
    }

    /** POSTs a form to a verify endpoint over HTTPS and decodes the JSON reply. */
    public static function post(string $url, array $fields): ?array
    {
        $body = http_build_query($fields);
        if (extension_loaded('curl')) {
            $handle = curl_init($url);
            curl_setopt_array($handle, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_USERAGENT => 'Meteor']);
            $reply = curl_exec($handle);
        } else {
            $reply = @file_get_contents($url, false, stream_context_create(['http' => ['method' => 'POST', 'timeout' => 10, 'ignore_errors' => true,
                'header' => "Content-Type: application/x-www-form-urlencoded\r\nUser-Agent: Meteor", 'content' => $body]]));
        }
        $data = is_string($reply) ? json_decode($reply, true) : null;
        return is_array($data) ? $data : null;
    }
}
