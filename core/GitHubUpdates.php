<?php
declare(strict_types=1);
namespace Core;
final class GitHubUpdates
{
    public function __construct(private HttpClient $http) {}
    public static function validateUrl(string $url): void
    {
        $parts = parse_url($url);
        if (!$parts || ($parts['scheme'] ?? '') !== 'https'
            || !in_array($parts['host'] ?? '', ['api.github.com', 'github.com', 'objects.githubusercontent.com'], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment']) || (isset($parts['port']) && $parts['port'] !== 443)
            || preg_match('/[\\\\\x00-\x20\x7f]/', $url)) { throw new \RuntimeException('Update URL is not on the HTTPS GitHub allow-list.'); }
    }
    private function fetch(string $url, string $token, int $limit, bool $asset = false): string
    {
        for ($redirect = 0; $redirect <= 5; $redirect++) {
            self::validateUrl($url);
            $headers = ['Accept: ' . ($asset ? 'application/octet-stream' : 'application/vnd.github+json'), 'X-GitHub-Api-Version: 2022-11-28'];
            if ($token !== '' && parse_url($url, PHP_URL_HOST) === 'api.github.com') {
                if (preg_match('/[\r\n]/', $token)) { throw new \InvalidArgumentException('Invalid GitHub token.'); }
                $headers[] = 'Authorization: Bearer ' . $token;
            }
            $response = $this->http->get($url, $headers, $limit);
            if (strlen($response['body']) > $limit) { throw new \RuntimeException('Download exceeds the size limit.'); }
            if (in_array($response['status'], [301, 302, 303, 307, 308], true)) {
                $location = $response['headers']['location'] ?? '';
                if (str_starts_with($location, '/') && !str_starts_with($location, '//')) { $location = 'https://' . parse_url($url, PHP_URL_HOST) . $location; }
                $url = $location; continue;
            }
            if ($response['status'] !== 200) { throw new \RuntimeException('GitHub returned HTTP ' . (int) $response['status'] . '. Check the repository and token.'); }
            return $response['body'];
        }
        throw new \RuntimeException('Too many GitHub redirects.');
    }
    public function latest(string $repository, string $token = ''): array
    {
        if (!preg_match('~^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$~D', $repository)) { throw new \InvalidArgumentException('Set a GitHub repository as owner/name to enable online updates.'); }
        $release = json_decode($this->fetch('https://api.github.com/repos/' . $repository . '/releases/latest', $token, 2097152), true, 32, JSON_THROW_ON_ERROR);
        $version = ltrim($release['tag_name'] ?? '', 'v');
        if (!preg_match('/^\d+\.\d+\.\d+$/D', $version)) { throw new \RuntimeException('Release has an invalid version tag.'); }
        return ['version' => $version, 'name' => (string) ($release['name'] ?? ''), 'date' => (string) ($release['published_at'] ?? ''),
            'notes' => (string) ($release['body'] ?? ''), 'assets' => $release['assets'] ?? []];
    }
    public function download(array $release, string $destination, string $token = ''): string
    {
        $name = 'meteor-' . $release['version'] . '.zip'; $assets = array_column($release['assets'], null, 'name');
        if (!isset($assets[$name], $assets[$name . '.sha256'])) { throw new \RuntimeException('Release zip and mandatory SHA-256 asset are missing.'); }
        $get = fn (array $asset, int $limit) => $this->fetch($asset['url'] ?? $asset['browser_download_url'] ?? '', $token, $limit, true);
        $checksum = $get($assets[$name . '.sha256'], 4096);
        if (!preg_match('/^([a-fA-F0-9]{64})(?:\s|$)/', trim($checksum), $m)) { throw new \RuntimeException('Invalid SHA-256 asset.'); }
        $bytes = $get($assets[$name], ReleasePackage::LIMIT);
        if (!hash_equals(strtolower($m[1]), hash('sha256', $bytes))) { throw new \RuntimeException('Download checksum mismatch.'); }
        ReleasePackage::write($destination, $bytes);
        return strtolower($m[1]);
    }
}
