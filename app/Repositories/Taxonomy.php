<?php
declare(strict_types=1);
namespace App\Repositories;
use Core\Database;

final class Taxonomy
{
    public function __construct(private Database $db) {}
    public function categories(string $locale): array
    {
        return $this->db->all('SELECT c.*, t.name FROM categories c JOIN category_translations t ON t.category_id = c.id WHERE t.locale = ? ORDER BY c.sort_order, c.id', [$locale]);
    }
    public function tags(string $locale, ?int $post = null): array
    {
        $sql = 'SELECT g.*, t.name FROM tags g JOIN tag_translations t ON t.tag_id = g.id WHERE t.locale = ?';
        $params = [$locale];
        if ($post !== null) { $sql .= ' AND EXISTS (SELECT 1 FROM post_tags p WHERE p.tag_id = g.id AND p.post_id = ?)'; $params[] = $post; }
        return $this->db->all($sql . ' ORDER BY t.name, g.id', $params);
    }
}
