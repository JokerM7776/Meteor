<?php
declare(strict_types=1);
namespace App\Repositories;
use Core\Database;

final class Posts
{
    public function __construct(private Database $db) {}
    public function published(string $locale, ?int $category = null, ?int $tag = null): array
    {
        $sql = "SELECT p.*, t.title, t.summary, t.content_html, t.toc_html, t.reading_minutes, t.locale,
            c.slug AS category_slug, ct.name AS category_name FROM posts p
            JOIN post_translations t ON t.post_id = p.id
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN category_translations ct ON ct.category_id = c.id AND ct.locale = t.locale
            WHERE t.locale = ? AND t.status = 'published' AND p.is_published = 1
            AND p.published_at <= ?";
        $params = [$locale, gmdate('Y-m-d H:i:s')];
        if ($category !== null) { $sql .= ' AND p.category_id = ?'; $params[] = $category; }
        if ($tag !== null) { $sql .= ' AND EXISTS (SELECT 1 FROM post_tags pt WHERE pt.post_id = p.id AND pt.tag_id = ?)'; $params[] = $tag; }
        return $this->db->all($sql . ' ORDER BY p.published_at DESC, p.id DESC', $params);
    }
    public function find(string $locale, string $slug): ?array
    {
        foreach ($this->published($locale) as $post) { if ($post['slug'] === $slug) { return $post; } }
        return null;
    }
    public function translations(int $id, bool $admin = false): array
    {
        return $this->db->all("SELECT l.* FROM post_translations t JOIN locales l ON l.code = t.locale
            JOIN posts p ON p.id = t.post_id WHERE t.post_id = ? AND t.status = 'published'
            AND p.is_published = 1 AND p.published_at <= ? AND (l.status = 'live' OR (l.status = 'preview' AND ? = 1))
            ORDER BY l.sort_order, l.code", [$id, gmdate('Y-m-d H:i:s'), (int) $admin]);
    }
}
