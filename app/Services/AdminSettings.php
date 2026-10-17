<?php
declare(strict_types=1);
namespace App\Services;
use Core\Database;

final class AdminSettings
{
    public const FIELDS = ['site_title', 'home_intro', 'nav_posts', 'nav_tags', 'nav_about', 'nav_rss', 'copyright'];
    public function __construct(private Database $db) {}
    public function save(string $locale, array $input): void
    {
        if (!$this->db->one('SELECT code FROM locales WHERE code = ?', [$locale])) { throw new \InvalidArgumentException('Unknown language.'); }
        if (stripos($input['custom_css'] ?? '', '</style') !== false) { throw new \InvalidArgumentException('Custom CSS must not contain a closing style tag.'); }
        $this->db->transaction(function () use ($locale, $input) {
            foreach (self::FIELDS as $key) {
                $value = trim($input[$key] ?? '');
                $this->db->query('DELETE FROM settings WHERE `key` = ? AND locale_scope = ?', [$key, $locale]);
                if ($value !== '') { $this->db->upsert('settings', ['key' => $key, 'locale' => $locale, 'value' => $value], ['key', 'locale_scope']); }
            }
            foreach (require dirname(__DIR__, 2) . '/lang/en.php' as $key => $english) {
                $value = trim($input['strings'][$key] ?? '');
                $this->db->query('DELETE FROM ui_strings WHERE locale = ? AND `key` = ?', [$locale, $key]);
                if ($value !== '') { $this->db->upsert('ui_strings', ['locale' => $locale, 'key' => $key, 'value' => $value], ['locale', 'key']); }
            }
            $this->db->upsert('settings', ['key' => 'custom_css', 'locale' => null, 'value' => $input['custom_css'] ?? ''], ['key', 'locale_scope']);
        });
    }
}
