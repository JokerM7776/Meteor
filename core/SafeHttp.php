<?php
declare(strict_types=1);
namespace Core;

/**
 * HTTP for webmentions, where other people choose the URLs. Only http(s) on the default ports, never
 * to private, loopback or reserved addresses (checked after DNS and pinned for the connection), every
 * redirect checked again, short timeouts, and at most 1 MB read.
 */
final class SafeHttp
{
    public const MAX_BYTES = 1048576;
    private const REDIRECTS = 5;
    private \Closure $transport;
    private \Closure $resolve;

    /**
     * $transport(method, url, headers, body, ip) returns ['status', 'headers' => [name => [values]], 'body'];
     * $resolve(host) returns IP addresses. Both are swappable for tests.
     */
    public function __construct(?\Closure $transport = null, ?\Closure $resolve = null)
    {
        $this->transport = $transport ?? $this->curl(...);
        $this->resolve = $resolve ?? self::lookup(...);
    }

    /** GET that follows redirects; adds 'url' (the final address). */
    public function get(string $url, array $headers = []): array
    {
        for ($hop = 0; ; $hop++) {
            $response = ($this->transport)('GET', $url, $headers, null, $this->check($url));
            $location = $response['headers']['location'][0] ?? null;
            if (!in_array($response['status'], [301, 302, 303, 307, 308], true) || $location === null) { return $response + ['url' => $url]; }
            if ($hop >= self::REDIRECTS) { throw new \RuntimeException('Too many redirects.'); }
            $url = self::resolve($url, $location);
        }
    }

    public function post(string $url, array $fields): array
    {
        return ($this->transport)('POST', $url, ['Content-Type: application/x-www-form-urlencoded'], http_build_query($fields), $this->check($url));
    }

    /** The public IP address to connect to, or an exception for a URL we must not fetch. */
    public function check(string $url): string
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));
        if (strlen($url) > 2048 || !in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])
            || (isset($parts['port']) && $parts['port'] !== ($scheme === 'https' ? 443 : 80)) || preg_match('/[\x00-\x20\x7f]/', $url)) {
            throw new \RuntimeException('Only plain http(s) URLs on the default ports are allowed.');
        }
        $ips = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : ($this->resolve)($host);
        if (!$ips) { throw new \RuntimeException('The host name does not resolve.'); }
        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) === false) { throw new \RuntimeException('The host points to a private or reserved address.'); }
        }
        return $ips[0];
    }

    private static function lookup(string $host): array
    {
        $ips = gethostbynamel($host) ?: [];
        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) { if (isset($record['ipv6'])) { $ips[] = $record['ipv6']; } }
        return $ips;
    }

    /** Absolute URL of $reference relative to $base (RFC 3986, enough for links and redirects). */
    public static function resolve(string $base, string $reference): string
    {
        $reference = trim($reference);
        if (preg_match('~^[a-z][a-z0-9+.-]*:~i', $reference)) { return $reference; }
        $b = parse_url($base);
        $origin = ($b['scheme'] ?? 'https') . '://' . ($b['host'] ?? '') . (isset($b['port']) ? ':' . $b['port'] : '');
        if (str_starts_with($reference, '//')) { return ($b['scheme'] ?? 'https') . ':' . $reference; }
        if ($reference === '' || $reference[0] === '#') { return preg_replace('/#.*$/', '', $base) . $reference; }
        $path = $b['path'] ?? '/';
        if ($reference[0] === '?') { return $origin . $path . $reference; }
        [$refPath, $tail] = array_pad(preg_split('/(?=[?#])/', $reference, 2), 2, '');
        $merged = $refPath[0] === '/' ? $refPath : substr($path, 0, (int) strrpos($path, '/') + 1) . $refPath;
        $out = [];
        foreach (explode('/', $merged) as $segment) {
            if ($segment === '..') { if (count($out) > 1) { array_pop($out); } }
            elseif ($segment !== '.') { $out[] = $segment; }
        }
        $last = basename($merged);
        return $origin . implode('/', $out) . ($last === '.' || $last === '..' ? '/' : '') . $tail;
    }

    private function curl(string $method, string $url, array $headers, ?string $body, string $ip): array
    {
        if (!extension_loaded('curl')) { throw new \RuntimeException('Enable the PHP curl extension for webmentions.'); }
        $parts = parse_url($url);
        $port = $parts['port'] ?? (strtolower($parts['scheme']) === 'https' ? 443 : 80);
        $handle = curl_init($url); $data = ''; $responseHeaders = [];
        curl_setopt_array($handle, [
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS, CURLOPT_NOPROXY => '*',
            // Connect only to the address that was checked, so DNS cannot change between the check and the request.
            CURLOPT_RESOLVE => [$parts['host'] . ':' . $port . ':' . (str_contains($ip, ':') ? '[' . $ip . ']' : $ip)],
            CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 10, CURLOPT_USERAGENT => 'Meteor-Webmention (+https://www.w3.org/TR/webmention/)',
            CURLOPT_HTTPHEADER => $headers, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_ENCODING => '',
            CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$responseHeaders): int {
                if (preg_match('~^HTTP/~', $line)) { $responseHeaders = []; }
                elseif (str_contains($line, ':')) { [$name, $value] = explode(':', $line, 2); $responseHeaders[strtolower(trim($name))][] = trim($value); }
                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => static function ($ch, string $chunk) use (&$data): int {
                $room = self::MAX_BYTES - strlen($data);
                $data .= substr($chunk, 0, max(0, $room));
                return $room > 0 ? strlen($chunk) : 0; // stop reading at the limit; what was read is kept
            },
        ]);
        if ($method === 'POST') { curl_setopt_array($handle, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => (string) $body]); }
        $ok = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        if ($ok === false && !(curl_errno($handle) === CURLE_WRITE_ERROR && $status > 0)) { throw new \RuntimeException('Request failed: ' . curl_error($handle)); }
        return ['status' => $status, 'headers' => $responseHeaders, 'body' => $data];
    }
}
