<?php
declare(strict_types=1);
namespace App\Repositories;
use Core\Database;
use Core\I18n;

final class Settings
{
    public function __construct(private Database $db, private I18n $i18n) {}
    public function get(string $key, ?string $locale = null, string $fallback = ''): string
    {
        foreach (array_unique([$locale ?? '', $this->i18n->defaultLocale(), '']) as $scope) {
            $row = $this->db->one('SELECT value FROM settings WHERE `key` = ? AND locale_scope = ?', [$key, $scope]);
            if ($row !== null) { return $row['value']; }
        }
        return $fallback;
    }
    public function global(string $key, string $fallback = ''): string
    {
        return $this->db->one('SELECT value FROM settings WHERE `key` = ? AND locale_scope = ?', [$key, ''])['value'] ?? $fallback;
    }
    public function enabled(string $key): bool { return $this->global($key, '1') !== '0'; }
    public function icon(): string
    {
        $path = $this->global('site_icon');
        return \App\Services\SiteIcon::validPath($path) ? $path : '';
    }
    public function label(string $key): string
    {
        return $this->get($key, $this->i18n->locale(), $this->i18n->translate($key === 'site_title' ? 'site_name' : $key));
    }
}
