<?php
declare(strict_types=1);
namespace Core;
interface HttpClient
{
    /** One request only; redirects are validated by GitHubUpdates. */
    public function get(string $url, array $headers, int $limit): array;
}
