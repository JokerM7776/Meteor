<?php
declare(strict_types=1);
namespace App\Services\Import;
use App\Services\{ContentEditor, MediaLibrary};
use App\Repositories\Pages;
use Core\Database;

final class Writer
{
    private array $groups = [];
    private array $slugs = [];
    private Terms $terms;
    private Images $images;
    private CommentWriter $comments;
    public function __construct(private Database $db, private Report $report, private array $options, \Closure $resolve, string $public, string $storage)
    {
        $this->terms = new Terms($db, $report); $this->images = new Images(new MediaLibrary($db, $public), $storage, $report, $resolve); $this->comments = new CommentWriter($db, $report);
    }
    public static function validSlug(string $slug): bool { return mb_strlen($slug) <= 191 && (bool) preg_match('/^[\p{L}\p{N}\p{M}]+(?:-[\p{L}\p{N}\p{M}]+)*$/uD', $slug); }
    public function run(array $items): void
    {
        $bases = []; $seen = [];
        foreach ($items as $index => &$item) {
            $key = $item['kind'] . ':' . $item['group'] . ':' . $item['locale'];
            if (isset($seen[$key])) { $item['group'] .= ':' . $item['source'] . ':' . $index; }
            $seen[$key] = true;
        }
        unset($item);
        foreach ($items as $item) { $key = $item['kind'] . ':' . $item['group']; if (!isset($bases[$key]) || $item['locale'] === $this->options['locale']) { $bases[$key] = $item['locale']; } }
        foreach ($items as $item) { $this->item($item, $bases[$item['kind'] . ':' . $item['group']]); }
    }
    private function item(array $item, string $base): void
    {
        $page = $item['kind'] === 'page'; [$table, $translations, $foreign] = ContentEditor::tables($page);
        $kind = $page ? 'pages' : 'posts'; $key = $item['kind'] . ':' . $item['group']; $group = $this->groups[$key] ?? null;
        $totals = $this->report->totals; $statuses = $this->report->commentStatuses; $planned = $this->terms->planned; $this->images->begin();
        try {
            if (!$this->db->one('SELECT code FROM locales WHERE code = ?', [$item['locale']])) { throw new \InvalidArgumentException('Unknown language: ' . $item['locale']); }
            $item['markdown'] = ReferenceLinks::convert($item['markdown']);
            $item['title'] = trim($item['title']) ?: (pathinfo($item['source'], PATHINFO_FILENAME) ?: 'Untitled');
            if (mb_strlen($item['title']) > 255) { $item['title'] = mb_substr($item['title'], 0, 255); $item['warnings'][] = 'Title truncated to 255 characters.'; }
            if (!self::validSlug($item['slug'])) { $item['slug'] = ContentEditor::slugFrom($item['slug'], $page ? 'page' : 'post'); $item['warnings'][] = 'Invalid slug normalized.'; }
            $slug = $group['slug'] ?? $item['slug']; $root = $slug; $n = 1;
            while ($page && in_array(mb_strtolower($slug), [...Pages::RESERVED, 'search'], true)) { $slug = $root . '-' . ++$n; $item['warnings'][] = 'Reserved page slug renamed.'; }
            $conflict = fn ($s) => isset($this->slugs[$table . ':' . mb_strtolower($s)]) || $this->db->one("SELECT id FROM $table WHERE slug = ?", [$s]);
            $replace = $group === null && $page && $slug === 'about' && !isset($this->slugs['pages:about']) ? DefaultAbout::id($this->db) : null;
            if ($replace !== null) { $group = ['id' => $replace, 'slug' => $slug]; $item['warnings'][] = 'Replaced the default About page.'; }
            if ($group === null && $conflict($slug)) {
                if (($this->options['conflict'] ?? 'skip') === 'rename') { do { $slug = rtrim(mb_substr($root, 0, 180), '-') . '-' . ++$n; } while ($conflict($slug)); $item['warnings'][] = 'Slug already used; renamed.'; }
                else { $group = ['skip' => true, 'slug' => $slug]; $this->groups[$key] = $group; }
            }
            $item['slug'] = $slug;
            $duplicate = isset($group['locales'][$item['locale']]);
            // Validate images even on skipped rows so Check reports missing source assets consistently.
            if (($group['skip'] ?? false) || $duplicate) {
                $this->images->rewrite($item, true);
                $item['warnings'][] = $duplicate ? 'Duplicate group and language; skipped.' : 'Slug already used; skipped.';
                $this->report->count('translations', 'skipped');
                if (!empty($group['skip'])) {
                    if (empty($group['counted'])) { $this->report->count($kind, 'skipped'); }
                    $group['counted'] = true; $group['locales'][$item['locale']] = true; $this->groups[$key] = $group;
                }
                $this->report->count('comments', 'skipped', count($item['comments'])); $this->report->row($item, 'skipped'); return;
            }
            if (!empty($this->options['drafts'])) { $item['status'] = 'draft'; }
            $this->images->rewrite($item);
            $compiled = ContentEditor::compile($item['markdown'], true, $item['locale']);
            $write = function () use ($item, $base, $page, $table, $translations, $foreign, $group, $compiled, $replace) {
                $category = null; $tags = [];
                if (!$page) {
                    if ($item['category']) { $category = $this->terms->id('categories', $item['category'], $item['locale'], $item['category_description']); }
                    foreach ($item['tags'] as $name) { $tags[] = $this->terms->id('tags', $name, $item['locale']); }
                }
                $published = $item['status'] === 'published'; $id = $group['id'] ?? 0;
                if (!$this->report->check) {
                    if ($replace !== null) {
                        $this->db->query('UPDATE pages SET base_locale = ?, is_published = ?, markdown_extended = 1, sort_order = ?, created_at = ?, updated_at = ? WHERE id = ?',
                            [$base, (int) $published, $item['sort_order'], $item['date'], $item['updated'], $id]);
                    }
                    if (!$id) {
                        $values = ['slug' => $item['slug'], 'base_locale' => $base, 'is_published' => (int) $published, 'markdown_extended' => 1, 'created_at' => $item['date'], 'updated_at' => $item['updated']];
                        $values += $page ? ['sort_order' => $item['sort_order']] : ['category_id' => $category, 'cover_image' => $item['cover'], 'is_pinned' => (int) $item['pinned'], 'comments_enabled' => (int) $item['comments_enabled'], 'published_at' => $item['date']];
                        $id = $this->db->insert('INSERT INTO ' . $table . ' (' . implode(', ', array_keys($values)) . ') VALUES (' . implode(', ', array_fill(0, count($values), '?')) . ')', array_values($values));
                    } elseif ($published) { $this->db->query("UPDATE $table SET is_published = 1 WHERE id = ?", [$id]); }
                    $this->db->upsert($translations, [$foreign => $id, 'locale' => $item['locale'], 'title' => $item['title'], 'summary' => $item['summary'], 'content_md' => $item['markdown'],
                        'status' => $item['status'], 'published_at' => $published ? $item['date'] : null, 'updated_at' => $item['updated']] + $compiled, [$foreign, 'locale']);
                    foreach ($tags as $tag) { $this->db->upsert('post_tags', ['post_id' => $id, 'tag_id' => $tag], ['post_id', 'tag_id']); }
                }
                if (!$page) { $this->comments->write($item, $id); } return $id;
            };
            $id = $this->report->check ? $write() : $this->db->transaction($write);
            $this->groups[$key] = ['id' => $id, 'slug' => $slug, 'locales' => ($group['locales'] ?? []) + [$item['locale'] => true]];
            $this->slugs[$table . ':' . mb_strtolower($slug)] = true;
            if ($group === null || $replace !== null) { $this->report->count($kind); } $this->report->count('translations');
            $item['id'] = $id;
            $this->report->row($item, $this->report->check ? 'would import' : 'imported');
        } catch (\Throwable $error) {
            $this->images->rollback(); $this->terms->planned = $planned; $this->report->totals = $totals; $this->report->commentStatuses = $statuses;
            $this->report->count('translations', 'failed'); if ($group === null) { $this->report->count($kind, 'failed'); }
            $item['warnings'][] = $error instanceof \PDOException ? 'Database could not store this item.' : $error->getMessage(); $this->report->row($item, 'failed');
        }
    }
}
