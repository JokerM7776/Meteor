<?php
declare(strict_types=1);
namespace Core;

/** Server-side check of a Cloudflare Turnstile token. The sender is swappable for tests. */
final class Turnstile
{
    public const ORIGIN = 'https://challenges.cloudflare.com';
    public const SCRIPT = self::ORIGIN . '/turnstile/v0/api.js';
    private const VERIFY = self::ORIGIN . '/turnstile/v0/siteverify';
    /** @var callable(string, array): ?array */
    private $send;

    public function __construct(private string $secret, ?callable $send = null) { $this->send = $send ?? self::post(...); }

    /** Cloudflare's decoded reply, or null when it could not be reached. */
    public function check(string $token, string $ip = ''): ?array
    {
        $fields = ['secret' => $this->secret, 'response' => $token];
        if ($ip !== '') { $fields['remoteip'] = $ip; }
        return ($this->send)(self::VERIFY, $fields);
    }

    public function passes(string $token, string $ip = ''): bool
    {
        // Cloudflare tokens are at most 2048 characters.
        if ($token === '' || strlen($token) > 2048) { return false; }
        return ($this->check($token, $ip)['success'] ?? false) === true;
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
