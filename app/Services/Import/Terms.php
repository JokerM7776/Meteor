<?php
declare(strict_types=1);
namespace App\Services\Import;
use Core\Database;

final class Terms
{
    public array $planned = [];
    public function __construct(private Database $db, private Report $report) {}
    public function id(string $kind, string $name, string $locale, string $description = ''): int
    {
        $name = mb_substr(trim($name), 0, 191); $slug = \slugify($name);
        [$table, $translations, $foreign] = $kind === 'categories' ? ['categories', 'category_translations', 'category_id'] : ['tags', 'tag_translations', 'tag_id'];
        $row = $this->db->one("SELECT t.id FROM $table t JOIN $translations tt ON tt.$foreign = t.id WHERE tt.locale = ? AND tt.name = ? ORDER BY t.id LIMIT 1", [$locale, $name])
            ?? $this->db->one("SELECT id FROM $table WHERE slug = ?", [$slug]);
        $key = $kind . ':' . $slug;
        if ($this->report->check) {
            if (!$row && !isset($this->planned[$key])) { $this->planned[$key] = count($this->planned) + 1; $this->report->count($kind); }
            return (int) ($row['id'] ?? $this->planned[$key]);
        }
        if (!$row) { $id = $this->db->insert("INSERT INTO $table (slug) VALUES (?)", [$slug]); $this->report->count($kind); }
        else { $id = (int) $row['id']; }
        if (!$this->db->one("SELECT id FROM $translations WHERE $foreign = ? AND locale = ?", [$id, $locale])) {
            $values = [$foreign => $id, 'locale' => $locale, 'name' => $name];
            if ($kind === 'categories') { $values['description'] = $description; }
            $this->db->upsert($translations, $values, [$foreign, 'locale']);
        }
        return $id;
    }
}
