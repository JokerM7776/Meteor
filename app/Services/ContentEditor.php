<?php
declare(strict_types=1);
namespace App\Services;
use Core\{Database, Markdown};

final class ContentEditor
{
    public function __construct(private Database $db) {}
    public static function tables(bool $page): array { return $page ? ['pages', 'page_translations', 'page_id'] : ['posts', 'post_translations', 'post_id']; }
    public function save(bool $page, int $id, string $locale, array $input, ?array $upload = null): int
    {
        [$table, $translations, $foreign] = self::tables($page);
        $old = $id ? $this->db->one("SELECT * FROM $table WHERE id = ?", [$id]) : null;
        if ($id && !$old) { throw new \InvalidArgumentException('Content not found.'); }
        if (!$this->db->one('SELECT code FROM locales WHERE code = ?', [$locale])) { throw new \InvalidArgumentException('Unknown language.'); }
        $slug = trim($input['slug'] ?? ''); $title = trim($input['title'] ?? '');
        if (!preg_match('/^[\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)*$/uD', $slug) || mb_strlen($slug) > 191) { throw new \InvalidArgumentException('Slug: use letters, numbers and single hyphens.'); }
        if ($page && in_array(strtolower($slug), \App\Repositories\Pages::RESERVED, true)) { throw new \InvalidArgumentException('Slug is reserved.'); }
        if ($this->db->one("SELECT id FROM $table WHERE slug = ? AND id <> ?", [$slug, $id])) { throw new \InvalidArgumentException('Slug is already in use.'); }
        if ($title === '' || mb_strlen($title) > 255) { throw new \InvalidArgumentException('Title is required (up to 255 characters).'); }
        if ($page && mb_strlen($input['nav_label'] ?? '') > 191) { throw new \InvalidArgumentException('Navigation label must be at most 191 characters.'); }
        $status = $input['status'] ?? 'draft';
        if (!in_array($status, ['draft', 'published'], true)) { throw new \InvalidArgumentException('Invalid translation status.'); }
        $category = empty($input['category_id']) ? null : (int) $input['category_id'];
        if (!$page && $category && !$this->db->one('SELECT id FROM categories WHERE id = ?', [$category])) { throw new \InvalidArgumentException('Unknown category.'); }
        $tags = array_values(array_unique(array_filter(array_map('trim', explode(',', $input['tags'] ?? '')))));
        foreach ($tags as $tag) { if (mb_strlen($tag) > 191) { throw new \InvalidArgumentException('Tag names must be at most 191 characters.'); } }
        $cover = !$page ? $this->upload($upload) : null;
        try {
            $result = $this->db->transaction(function () use ($page, $id, $locale, $input, $old, $table, $translations, $foreign, $slug, $title, $status, $category, $tags, $cover) {
                $now = gmdate('Y-m-d H:i:s');
                $values = ['slug' => $slug, 'base_locale' => $old['base_locale'] ?? $locale, 'is_published' => isset($input['is_published']) ? 1 : 0, 'updated_at' => $now];
                if (!$page) { $values += ['category_id' => $category, 'is_pinned' => isset($input['is_pinned']) ? 1 : 0,
                    'cover_image' => $cover ?? ($old['cover_image'] ?? null), 'published_at' => $old['published_at'] ?? ($status === 'published' ? $now : null)]; }
                else { $values += ['show_in_nav' => isset($input['show_in_nav']) ? 1 : 0]; }
                if ($id) { $this->db->upsert($table, ['id' => $id] + $values, ['id']); }
                else { $columns = implode(', ', array_keys($values)); $marks = implode(', ', array_fill(0, count($values), '?'));
                    $id = $this->db->insert("INSERT INTO $table ($columns) VALUES ($marks)", array_values($values)); }
                $previous = $this->db->one("SELECT published_at FROM $translations WHERE $foreign = ? AND locale = ?", [$id, $locale]);
                $compiled = (new Markdown())->compile($input['content_md'] ?? '');
                $compiled['content_html'] = $compiled['html']; unset($compiled['html']);
                $translation = [$foreign => $id, 'locale' => $locale, 'title' => $title, 'summary' => $input['summary'] ?? '',
                    'content_md' => $input['content_md'] ?? '', 'status' => $status, 'updated_at' => $now,
                    'published_at' => $previous['published_at'] ?? ($status === 'published' ? $now : null)] + $compiled;
                if ($page) { $translation['nav_label'] = trim($input['nav_label'] ?? '') ?: null; }
                $this->db->upsert($translations, $translation, [$foreign, 'locale']);
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
        } catch (\Throwable $error) { if ($cover) { $this->removeCover($cover); } throw $error; }
        if ($cover && !empty($old['cover_image'])) { $this->removeCover($old['cover_image']); }
        return $result;
    }
    private function upload(?array $file): ?string
    {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) { return null; }
        if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 5 * 1024 * 1024 || !is_uploaded_file($file['tmp_name'])) { throw new \InvalidArgumentException('Cover: upload a JPG, PNG or WebP up to 5 MB.'); }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
        if (!$extension || !getimagesize($file['tmp_name'])) { throw new \InvalidArgumentException('Cover must be a valid JPG, PNG or WebP image.'); }
        $path = '/uploads/' . bin2hex(random_bytes(16)) . '.' . $extension;
        if (!move_uploaded_file($file['tmp_name'], dirname(__DIR__, 2) . '/public' . $path)) { throw new \RuntimeException('Could not store cover.'); }
        return $path;
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
        $this->removeCover($item['cover_image'] ?? null);
    }
}
