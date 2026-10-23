<?php
declare(strict_types=1);
namespace Core;
final class CurlHttpClient implements HttpClient
{
    public function get(string $url, array $headers, int $limit): array
    {
        if (!extension_loaded('curl')) { throw new \RuntimeException('Enable ext-curl to check and download updates.'); }
        GitHubUpdates::validateUrl($url);
        $handle = curl_init($url); $body = ''; $responseHeaders = []; $overflow = false;
        curl_setopt_array($handle, [
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 120, CURLOPT_HTTPHEADER => $headers,
            CURLOPT_USERAGENT => 'Meteor-Updater', CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$responseHeaders): int {
                if (str_contains($line, ':')) { [$name, $value] = explode(':', $line, 2); $responseHeaders[strtolower(trim($name))] = trim($value); }
                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => static function ($ch, string $chunk) use (&$body, &$overflow, $limit): int {
                if (strlen($body) + strlen($chunk) > $limit) { $overflow = true; return 0; }
                $body .= $chunk; return strlen($chunk);
            },
        ]);
        $result = curl_exec($handle); $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        if ($overflow) { throw new \RuntimeException('Download exceeds the size limit.'); }
        if ($result === false) { throw new \RuntimeException('GitHub request failed. Check network access and try again.'); }
        return ['status' => $status, 'headers' => $responseHeaders, 'body' => $body];
    }
}
