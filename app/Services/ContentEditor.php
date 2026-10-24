<?php
declare(strict_types=1);
namespace App\Services;
use Core\{Database, Markdown};

final class ContentEditor
{
    /** [post id, locale] after saving a post translation whose public text changed; webmentions are due for it. */
    public ?array $published = null;
    public function __construct(private Database $db) {}
    public static function tables(bool $page): array { return $page ? ['pages', 'page_translations', 'page_id'] : ['posts', 'post_translations', 'post_id']; }
    public function save(bool $page, int $id, string $locale, array $input): int
    {
        [$table, $translations, $foreign] = self::tables($page);
        $old = $id ? $this->db->one("SELECT * FROM $table WHERE id = ?", [$id]) : null;
        if ($id && !$old) { throw new \InvalidArgumentException('Content not found.'); }
        if (!$this->db->one('SELECT code FROM locales WHERE code = ?', [$locale])) { throw new \InvalidArgumentException('Unknown language.'); }
        $slug = trim((string) ($input['slug'] ?? '')); $title = trim((string) ($input['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 255) { throw new \InvalidArgumentException('Title is required (up to 255 characters).'); }
        if ($slug === '') { $slug = $this->uniqueSlug($page, $id, $this->slugSource($page, $id, $locale, $old, $title)); }
        if (!preg_match('/^[\p{L}\p{N}\p{M}]+(?:-[\p{L}\p{N}\p{M}]+)*$/uD', $slug) || mb_strlen($slug) > 191) { throw new \InvalidArgumentException('Slug: use letters, numbers and single hyphens.'); }
        if ($page && in_array(mb_strtolower($slug), [...\App\Repositories\Pages::RESERVED, 'search'], true)) { throw new \InvalidArgumentException('Slug is reserved.'); }
        if ($this->db->one("SELECT id FROM $table WHERE slug = ? AND id <> ?", [$slug, $id])) { throw new \InvalidArgumentException('Slug is already in use.'); }
        $status = $input['status'] ?? 'draft';
        if (!in_array($status, ['draft', 'published'], true)) { throw new \InvalidArgumentException('Invalid translation status.'); }
        $category = empty($input['category_id']) ? null : (int) $input['category_id'];
        if (!$page && $category && !$this->db->one('SELECT id FROM categories WHERE id = ?', [$category])) { throw new \InvalidArgumentException('Unknown category.'); }
        $tags = array_values(array_unique(array_filter(array_map('trim', explode(',', $input['tags'] ?? '')))));
        foreach ($tags as $tag) { if (mb_strlen($tag) > 191) { throw new \InvalidArgumentException('Tag names must be at most 191 characters.'); } }
        $cover = $page ? null : $this->cover($input, $old);
        $result = $this->db->transaction(function () use ($page, $id, $locale, $input, $old, $table, $translations, $foreign, $slug, $title, $status, $category, $tags, $cover) {
            $now = gmdate('Y-m-d H:i:s');
            $extended = isset($input['markdown_extended']) ? 1 : 0;
            $values = ['slug' => $slug, 'base_locale' => $old['base_locale'] ?? $locale, 'is_published' => isset($input['is_published']) ? 1 : 0,
                'markdown_extended' => $extended, 'updated_at' => $now];
            if (!$page) { $values += ['category_id' => $category, 'is_pinned' => isset($input['is_pinned']) ? 1 : 0,
                'comments_enabled' => isset($input['comments_enabled']) ? 1 : 0,
                'cover_image' => $cover, 'published_at' => $old['published_at'] ?? ($status === 'published' ? $now : null)]; }
            if ($id) { $this->db->upsert($table, ['id' => $id] + $values, ['id']); }
            else { $columns = implode(', ', array_keys($values)); $marks = implode(', ', array_fill(0, count($values), '?'));
                $id = $this->db->insert("INSERT INTO $table ($columns) VALUES ($marks)", array_values($values)); }
            $previous = $this->db->one("SELECT published_at, status, content_html FROM $translations WHERE $foreign = ? AND locale = ?", [$id, $locale]);
            $compiled = self::compile($input['content_md'] ?? '', (bool) $extended, $locale);
            $translation = [$foreign => $id, 'locale' => $locale, 'title' => $title, 'summary' => $input['summary'] ?? '',
                'content_md' => $input['content_md'] ?? '', 'status' => $status, 'updated_at' => $now,
                'published_at' => $previous['published_at'] ?? ($status === 'published' ? $now : null)] + $compiled;
            $this->db->upsert($translations, $translation, [$foreign, 'locale']);
            if (!$page && $status === 'published' && !empty($values['is_published']) && (($previous['status'] ?? '') !== 'published' || ($previous['content_html'] ?? '') !== $compiled['content_html'])) { $this->published = [$id, $locale]; }
            // The Markdown flavour is shared, so the other languages are rebuilt when it changes.
            if ($old && (int) ($old['markdown_extended'] ?? 0) !== $extended) {
                foreach ($this->db->all("SELECT locale, content_md FROM $translations WHERE $foreign = ? AND locale <> ?", [$id, $locale]) as $other) {
                    $html = self::compile($other['content_md'], (bool) $extended, $other['locale']);
                    $this->db->query("UPDATE $translations SET content_html = ?, toc_html = ?, reading_minutes = ? WHERE $foreign = ? AND locale = ?",
                        [$html['content_html'], $html['toc_html'], $html['reading_minutes'], $id, $other['locale']]);
                }
            }
            if (!$page) {
                $this->db->query('DELETE FROM post_tags WHERE post_id = ?', [$id]);
                foreach ($tags as $name) {
                    $tagSlug = \slugify($name);
                    $tag = $this->db->one('SELECT id FROM tags WHERE slug = ?', [$tagSlug]);
                    $tagId = $tag['id'] ?? $this->db->insert('INSERT INTO tags (slug) VALUES (?)', [$tagSlug]);
                    $this->db->upsert('tag_translations', ['tag_id' => $tagId, 'locale' => $locale, 'name' => $name], ['tag_id', 'locale']);
                    $this->db->upsert('post_tags', ['post_id' => $id, 'tag_id' => $tagId], ['post_id', 'tag_id']);
                }
            }
            return $id;
        });
        // An older cover uploaded straight to /uploads (not the media library) has no other owner.
        if (($old['cover_image'] ?? null) !== null && $old['cover_image'] !== $cover) { $this->removeCover($old['cover_image']); }
        return $result;
    }
    /** Compiled content_html, toc_html and reading_minutes. Footnote labels use the content's language. */
    public static function compile(string $markdown, bool $extended, string $locale): array
    {
        $i18n = $GLOBALS['i18n'] ?? null;
        $labels = $i18n instanceof \Core\I18n ? ['footnotes' => $i18n->translateFor($locale, 'footnotes'), 'footnote_back' => $i18n->translateFor($locale, 'footnote_back')] : [];
        $compiled = (new Markdown($extended, $labels))->compile($markdown);
        $compiled['content_html'] = $compiled['html']; unset($compiled['html']);
        return $compiled;
    }
    /** Slug text: the base-language title when this is a translation of an existing item. */
    private function slugSource(bool $page, int $id, string $locale, ?array $old, string $title): string
    {
        if ($old && $old['base_locale'] !== $locale) {
            [, $translations, $foreign] = self::tables($page);
            $base = $this->db->one("SELECT title FROM $translations WHERE $foreign = ? AND locale = ?", [$id, $old['base_locale']]);
            if ($base) { return $base['title']; }
        }
        return $title;
    }
    public static function slugFrom(string $title, string $fallback): string
    {
        $slug = trim((string) preg_replace('/[^\p{L}\p{N}\p{M}]+/u', '-', mb_strtolower(trim($title), 'UTF-8')), '-');
        $slug = rtrim(mb_substr($slug, 0, 80), '-');
        return $slug !== '' ? $slug : $fallback;
    }
    private function uniqueSlug(bool $page, int $id, string $title): string
    {
        [$table] = self::tables($page);
        $base = self::slugFrom($title, $page ? 'page' : 'post');
        for ($n = 1; ; $n++) {
            $slug = $n === 1 ? $base : $base . '-' . $n;
            if ($page && in_array($slug, [...\App\Repositories\Pages::RESERVED, 'search'], true)) { continue; }
            if (!$this->db->one("SELECT id FROM $table WHERE slug = ? AND id <> ?", [$slug, $id])) { return $slug; }
        }
    }
    /** The cover URL to store: none, the one already stored, or an image in the media library. */
    private function cover(array $input, ?array $old): ?string
    {
        $current = $old['cover_image'] ?? null;
        if (!array_key_exists('cover_image', $input)) { return $current; }
        $url = trim(is_string($input['cover_image']) ? $input['cover_image'] : '');
        if ($url === '') { return null; }
        if ($url === $current) { return $current; }
        $name = substr($url, strlen(MediaLibrary::DIRECTORY));
        if (str_starts_with($url, MediaLibrary::DIRECTORY) && $this->db->one('SELECT id FROM media WHERE file_name = ?', [$name])) { return $url; }
        throw new \InvalidArgumentException('Cover: choose an image from the media library.');
    }
    public function removeCover(?string $path): void
    {
        if ($path && preg_match('~^/uploads/[a-f0-9]{32}\.(jpg|png|webp)$~D', $path)) {
            $file = dirname(__DIR__, 2) . '/public' . $path;
            if (is_file($file)) { unlink($file); }
        }
    }
    public function delete(bool $page, int $id): void
    {
        [$table] = self::tables($page);
        $item = $this->db->one("SELECT * FROM $table WHERE id = ?", [$id]);
        $this->db->query("DELETE FROM $table WHERE id = ?", [$id]);
        if ($page) { $this->db->query('DELETE FROM menu_items WHERE page_id = ?', [$id]); }
        $this->removeCover($item['cover_image'] ?? null);
    }
}
