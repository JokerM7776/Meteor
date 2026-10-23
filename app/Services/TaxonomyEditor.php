<?php
declare(strict_types=1);
namespace App\Services;
use Core\Database;
final class TaxonomyEditor
{
    public function __construct(private Database $db) {}
    public static function tables(string $kind): array
    {
        if (!in_array($kind, ['category', 'tag'], true)) { throw new \InvalidArgumentException('Unknown term type.'); }
        return $kind === 'category' ? ['categories', 'category_translations', 'category_id'] : ['tags', 'tag_translations', 'tag_id'];
    }
    /** Terms with their names per language and how many posts use them, filtered by slug or any name. */
    public function rows(string $kind, string $query = ''): array
    {
        [$table, $translations, $foreign] = self::tables($kind);
        $count = $kind === 'category' ? 'SELECT COUNT(*) FROM posts p WHERE p.category_id = t.id' : 'SELECT COUNT(*) FROM post_tags p WHERE p.tag_id = t.id';
        $terms = $this->db->all("SELECT t.*, ($count) AS post_count FROM $table t ORDER BY " . ($kind === 'category' ? 't.sort_order, ' : '') . 't.slug');
        $names = [];
        foreach ($this->db->all("SELECT $foreign AS term, locale, name FROM $translations") as $name) { $names[$name['term']][$name['locale']] = $name['name']; }
        $rows = [];
        $needle = mb_strtolower(trim($query));
        foreach ($terms as $term) {
            $term['names'] = $names[$term['id']] ?? [];
            if ($needle !== '' && !str_contains(mb_strtolower($term['slug'] . "\n" . implode("\n", $term['names'])), $needle)) { continue; }
            $rows[] = $term;
        }
        return $rows;
    }
    public function find(string $kind, int $id): ?array
    {
        [$table] = self::tables($kind);
        foreach ($this->rows($kind) as $row) { if ((int) $row['id'] === $id) { return $row; } }
        return null;
    }
    public function delete(string $kind, int $id): void
    {
        [$table] = self::tables($kind);
        $this->db->query("DELETE FROM $table WHERE id = ?", [$id]);
    }
    /** Saves one term. Names for languages absent from the input are left as they are. */
    public function save(array $input): void
    {
        $kind = ($input['kind'] ?? '') === 'category' ? 'category' : 'tag';
        [$table, $translations, $foreign] = self::tables($kind);
        $id = (int) ($input['id'] ?? 0);
        if (($input['action'] ?? '') === 'delete') { $this->delete($kind, $id); return; }
        $this->db->transaction(function () use ($input, $kind, $table, $translations, $foreign, $id) {
            if ($id && !$this->db->one("SELECT id FROM $table WHERE id = ?", [$id])) { throw new \InvalidArgumentException('This term no longer exists. Reload the page.'); }
            $slug = trim((string) ($input['slug'] ?? ''));
            if (!preg_match('/^[\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)*$/uD', $slug) || mb_strlen($slug) > 191) { throw new \InvalidArgumentException('Enter a valid slug: letters and numbers joined by single hyphens.'); }
            if ($this->db->one("SELECT id FROM $table WHERE slug = ? AND id <> ?", [$slug, $id])) { throw new \InvalidArgumentException('Slug “' . $slug . '” already exists.'); }
            $order = null;
            if ($kind === 'category' && array_key_exists('sort_order', $input)) {
                $raw = trim((string) $input['sort_order']);
                if ($raw === '') { $raw = '0'; }
                if (!preg_match('/^-?\d{1,4}$/D', $raw)) { throw new \InvalidArgumentException('Order must be a whole number between -9999 and 9999.'); }
                $order = (int) $raw;
            }
            if (!$id) { $id = $this->db->insert("INSERT INTO $table (slug) VALUES (?)", [$slug]); }
            else { $this->db->query("UPDATE $table SET slug = ? WHERE id = ?", [$slug, $id]); }
            if ($order !== null) { $this->db->query('UPDATE categories SET sort_order = ? WHERE id = ?', [$order, $id]); }
            $names = is_array($input['names'] ?? null) ? $input['names'] : [];
            foreach ($this->db->all('SELECT code FROM locales') as $locale) {
                if (!array_key_exists($locale['code'], $names)) { continue; }
                $name = trim((string) $names[$locale['code']]);
                if (mb_strlen($name) > 191) { throw new \InvalidArgumentException('Names must be up to 191 characters.'); }
                $this->db->query("DELETE FROM $translations WHERE $foreign = ? AND locale = ?", [$id, $locale['code']]);
                if ($name !== '') { $this->db->upsert($translations, [$foreign => $id, 'locale' => $locale['code'], 'name' => $name], [$foreign, 'locale']); }
            }
        });
    }
}
