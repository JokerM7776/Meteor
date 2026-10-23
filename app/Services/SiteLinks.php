<?php
declare(strict_types=1);
namespace App\Services;

final class SiteLinks
{
    /**
     * Allowed link targets: a root-relative path (not //host), #fragment, http(s):// or mailto:.
     * The {locale} token is allowed and stands for the current language code.
     */
    public static function validUrl(string $url): bool
    {
        $url = str_replace('{locale}', 'xx', $url);
        return $url !== '' && strlen($url) <= 2048 && !preg_match('/[\p{C}\s\\\\]/u', $url)
            && preg_match('~^(?:/(?!/)|#|https?://[^/?#]|mailto:)~i', $url) === 1;
    }
}
