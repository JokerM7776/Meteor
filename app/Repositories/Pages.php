<?php
declare(strict_types=1);
namespace App\Repositories;
use Core\Database;

final class Pages
{
    public const RESERVED = ['posts', 'tags', 'categories', 'rss.xml', 'sitemap.xml', 'custom.css', 'admin', 'assets', 'uploads', '404'];
    public function __construct(private Database $db) {}
    public function published(string $locale): array
    {
        return array_values(array_filter($this->db->all("SELECT p.*, t.title, t.summary, t.content_html, t.toc_html, t.locale
            FROM pages p JOIN page_translations t ON t.page_id = p.id
            WHERE t.locale = ? AND p.is_published = 1 AND t.status = 'published' ORDER BY p.sort_order, p.id", [$locale]),
            fn ($p) => !in_array($p['slug'], self::RESERVED, true)));
    }
    public function find(string $locale, string $slug): ?array
    {
        foreach ($this->published($locale) as $page) { if ($page['slug'] === $slug) { return $page; } }
        return null;
    }
    public function translations(int $id, bool $admin = false): array
    {
        return $this->db->all("SELECT l.* FROM page_translations t JOIN locales l ON l.code = t.locale
            JOIN pages p ON p.id = t.page_id WHERE t.page_id = ? AND t.status = 'published' AND p.is_published = 1
            AND (l.status = 'live' OR (l.status = 'preview' AND ? = 1)) ORDER BY l.sort_order, l.code", [$id, (int) $admin]);
    }
}
