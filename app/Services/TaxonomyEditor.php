<?php
declare(strict_types=1);
namespace App\Services;
use Core\Database;
final class TaxonomyEditor
{
    public function __construct(private Database $db) {}
    public function save(array $input): void
    {
        $category = ($input['kind'] ?? '') === 'category';
        [$table, $translations, $foreign] = $category ? ['categories', 'category_translations', 'category_id'] : ['tags', 'tag_translations', 'tag_id'];
        $id = (int) ($input['id'] ?? 0);
        $this->db->transaction(function () use ($input, $table, $translations, $foreign, $id) {
            if (($input['action'] ?? '') === 'delete') { $this->db->query("DELETE FROM $table WHERE id = ?", [$id]); return; }
            $slug = trim($input['slug'] ?? '');
            if (!preg_match('/^[\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)*$/uD', $slug) || mb_strlen($slug) > 191) { throw new \InvalidArgumentException('Enter a valid slug.'); }
            if ($this->db->one("SELECT id FROM $table WHERE slug = ? AND id <> ?", [$slug, $id])) { throw new \InvalidArgumentException('Slug already exists.'); }
            if (!$id) { $id = $this->db->insert("INSERT INTO $table (slug) VALUES (?)", [$slug]); }
            else { $this->db->query("UPDATE $table SET slug = ? WHERE id = ?", [$slug, $id]); }
            foreach ($this->db->all('SELECT code FROM locales') as $locale) {
                $name = trim($input['names'][$locale['code']] ?? '');
                if (mb_strlen($name) > 191) { throw new \InvalidArgumentException('Names must be up to 191 characters.'); }
                $this->db->query("DELETE FROM $translations WHERE $foreign = ? AND locale = ?", [$id, $locale['code']]);
                if ($name !== '') { $this->db->upsert($translations, [$foreign => $id, 'locale' => $locale['code'], 'name' => $name], [$foreign, 'locale']); }
            }
        });
    }
}
