<?php
declare(strict_types=1);
namespace App\Services;
use Core\{Database, ImageOptimizer};

/** Uploaded images. Files get random names, so their URLs cannot be guessed or listed. */
final class MediaLibrary
{
    public const MAX_BYTES = 15 * 1024 * 1024;
    public const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'image/avif' => 'avif'];
    public const DIRECTORY = '/uploads/media/';
    public const SIZES = [0 => 'Keep the original size', 1280 => '1280 px', 1920 => '1920 px', 2560 => '2560 px', 3840 => '3840 px'];
    private const NAME = '~^[a-f0-9]{32}\.(jpg|png|webp|gif|avif)$~D';

    public function __construct(private Database $db, private string $public) {}

    public static function url(?string $name): string { return $name ? self::DIRECTORY . $name : ''; }

    /** Largest upload this server accepts (PHP limits included). */
    public static function uploadLimit(): int
    {
        $limits = [self::MAX_BYTES];
        foreach (['upload_max_filesize', 'post_max_size'] as $key) { $bytes = ImageOptimizer::bytes((string) ini_get($key)); if ($bytes > 0) { $limits[] = $bytes; } }
        return min($limits);
    }

    public function options(): array
    {
        $rows = $this->db->all("SELECT `key`, value FROM settings WHERE locale_scope = '' AND `key` IN ('media_optimize', 'media_format', 'media_quality', 'media_max_size')");
        $values = array_column($rows, 'value', 'key');
        $format = $values['media_format'] ?? 'webp';
        $size = (int) ($values['media_max_size'] ?? 2560);
        return ['optimize' => ($values['media_optimize'] ?? '1') !== '0', 'format' => isset(ImageOptimizer::FORMATS[$format]) ? $format : 'webp',
            'quality' => max(40, min(95, (int) ($values['media_quality'] ?? 82))), 'max_size' => isset(self::SIZES[$size]) ? $size : 2560];
    }

    public function saveOptions(array $input): void
    {
        $format = (string) ($input['media_format'] ?? 'webp'); $quality = (int) ($input['media_quality'] ?? 0); $size = (int) ($input['media_max_size'] ?? -1);
        if (!isset(ImageOptimizer::FORMATS[$format])) { throw new \InvalidArgumentException('Choose WebP or AVIF.'); }
        if ($format === 'avif' && !ImageOptimizer::available('avif')) { throw new \InvalidArgumentException('This server cannot create AVIF images. Choose WebP.'); }
        if ($quality < 40 || $quality > 95) { throw new \InvalidArgumentException('Quality must be between 40 and 95.'); }
        if (!isset(self::SIZES[$size])) { throw new \InvalidArgumentException('Choose a maximum size from the list.'); }
        $values = ['media_optimize' => ($input['media_optimize'] ?? '0') === '1' ? '1' : '0', 'media_format' => $format, 'media_quality' => (string) $quality, 'media_max_size' => (string) $size];
        $this->db->transaction(function () use ($values) {
            foreach ($values as $key => $value) { $this->db->upsert('settings', ['key' => $key, 'locale' => null, 'value' => $value], ['key', 'locale_scope']); }
        });
    }

    /** Stores one file from $_FILES. Returns the new row plus a 'note' about the conversion. */
    public function upload(array $file, array $meta): array
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE) { throw new \InvalidArgumentException('Choose an image to upload.'); }
        if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || ($file['size'] ?? 0) > self::uploadLimit()) {
            throw new \InvalidArgumentException('Images can be up to ' . self::size(self::uploadLimit()) . ' on this server.');
        }
        if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) { throw new \InvalidArgumentException('The upload failed. Try again.'); }
        return $this->import($file['tmp_name'], (string) ($file['name'] ?? 'image'), $meta);
    }

    public function import(string $path, string $originalName, array $meta): array
    {
        [$alt, $category, $tags] = $this->meta($meta);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $size = @getimagesize($path);
        if (!isset(self::TYPES[$mime]) || !$size || $size[0] < 1 || $size[1] < 1) { throw new \InvalidArgumentException('Upload a JPG, PNG, WebP, GIF or AVIF image.'); }
        $options = $this->options();
        $result = ImageOptimizer::process($path, $mime, $options + ['thumb_size' => 400]);
        [$bytes, $mime, $width, $height] = $result['image'] ?? [(string) file_get_contents($path), $mime, $size[0], $size[1]];
        $directory = $this->public . self::DIRECTORY;
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) { throw new \RuntimeException('Cannot create the media folder.'); }
        $name = bin2hex(random_bytes(16)) . '.' . self::TYPES[$mime];
        $thumb = $result['thumb'] ? bin2hex(random_bytes(16)) . '.webp' : null;
        $written = [];
        try {
            $files = [$name => $bytes]; if ($thumb !== null) { $files[$thumb] = $result['thumb'][0]; }
            foreach ($files as $file => $content) {
                if (file_put_contents($directory . $file, $content, LOCK_EX) !== strlen($content)) { throw new \RuntimeException('Cannot store the image.'); }
                $written[] = $directory . $file;
            }
            $original = mb_substr(trim(preg_replace('/[\x00-\x1f\x7f]+/u', '', basename(str_replace('\\', '/', $originalName))) ?? ''), 0, 255) ?: 'image';
            $id = $this->db->transaction(function () use ($name, $thumb, $original, $mime, $width, $height, $bytes, $alt, $category, $tags) {
                $id = $this->db->insert('INSERT INTO media (file_name, thumb_name, original_name, mime_type, width, height, size_bytes, alt_text, category, search_text, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$name, $thumb, $original, $mime, $width, $height, strlen($bytes), $alt, $category, self::searchText($original, $alt, $category, $tags), gmdate('Y-m-d H:i:s')]);
                $this->saveTags($id, $tags);
                return $id;
            });
        } catch (\Throwable $error) { foreach ($written as $file) { @unlink($file); } throw $error; }
        return $this->find($id) + ['note' => $result['note']];
    }

    public function find(int $id): ?array
    {
        $row = $this->db->one('SELECT * FROM media WHERE id = ?', [$id]);
        return $row ? $this->withTags([$row])[0] : null;
    }

    /** One page of results, newest first. Every word of $query must match the name, alt text, category or a tag. */
    public function search(string $query, string $category, int $page, int $perPage): array
    {
        $where = []; $params = [];
        if ($category !== '') { $where[] = 'category = ?'; $params[] = $category; }
        foreach (array_slice(preg_split('/\s+/u', mb_strtolower(trim($query)), -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 8) as $word) {
            $where[] = "search_text LIKE ? ESCAPE '!'"; $params[] = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $word) . '%';
        }
        $sql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $total = (int) $this->db->one('SELECT COUNT(*) AS n FROM media' . $sql, $params)['n'];
        $pages = max(1, (int) ceil($total / $perPage)); $page = max(1, min($page, $pages));
        $rows = $this->db->all('SELECT * FROM media' . $sql . ' ORDER BY created_at DESC, id DESC LIMIT ' . (int) $perPage . ' OFFSET ' . (int) (($page - 1) * $perPage), $params);
        return ['rows' => $this->withTags($rows), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    public function categories(): array
    {
        return array_column($this->db->all("SELECT DISTINCT category FROM media WHERE category <> '' ORDER BY category"), 'category');
    }

    public function update(int $id, array $meta): void
    {
        $row = $this->find($id);
        if (!$row) { throw new \InvalidArgumentException('This image no longer exists.'); }
        [$alt, $category, $tags] = $this->meta($meta);
        $this->db->transaction(function () use ($row, $alt, $category, $tags) {
            $this->db->query('UPDATE media SET alt_text = ?, category = ?, search_text = ? WHERE id = ?', [$alt, $category, self::searchText($row['original_name'], $alt, $category, $tags), $row['id']]);
            $this->db->query('DELETE FROM media_tags WHERE media_id = ?', [$row['id']]);
            $this->saveTags((int) $row['id'], $tags);
        });
    }

    public function delete(int $id): void
    {
        $row = $this->find($id);
        if (!$row) { return; }
        $this->db->query('DELETE FROM media WHERE id = ?', [$id]);
        foreach ([$row['file_name'], $row['thumb_name']] as $name) {
            if ($name && preg_match(self::NAME, $name) && is_file($this->public . self::DIRECTORY . $name)) { unlink($this->public . self::DIRECTORY . $name); }
        }
    }

    /** Translations, covers, the site icon and the author avatar that still point at the file. */
    public function usage(array $row): int
    {
        $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], self::url($row['file_name'])) . '%';
        $count = 0;
        foreach (['post_translations', 'page_translations'] as $table) { $count += (int) $this->db->one("SELECT COUNT(*) AS n FROM $table WHERE content_md LIKE ? ESCAPE '!'", [$like])['n']; }
        $count += (int) $this->db->one("SELECT COUNT(*) AS n FROM settings WHERE `key` IN ('site_icon', 'author_avatar') AND locale_scope = '' AND value = ?", [self::url($row['file_name'])])['n'];
        return $count + (int) $this->db->one('SELECT COUNT(*) AS n FROM posts WHERE cover_image = ?', [self::url($row['file_name'])])['n'];
    }

    /** The fields the editor's media picker needs. */
    public static function json(array $row): array
    {
        return ['id' => (int) $row['id'], 'url' => self::url($row['file_name']), 'thumb' => self::url($row['thumb_name'] ?: $row['file_name']),
            'name' => $row['original_name'], 'alt' => $row['alt_text'], 'width' => (int) $row['width'], 'height' => (int) $row['height'],
            'category' => $row['category'], 'tags' => $row['tags']];
    }

    public static function size(int $bytes): string
    {
        return $bytes >= 1048576 ? round($bytes / 1048576, 1) . ' MB' : max(1, (int) round($bytes / 1024)) . ' KB';
    }

    private function meta(array $meta): array
    {
        $text = static fn (string $key) => trim(preg_replace('/\s+/u', ' ', is_string($meta[$key] ?? null) ? $meta[$key] : '') ?? '');
        $alt = $text('alt'); $category = $text('category');
        foreach ([$alt, $category, $text('tags')] as $value) { if (!mb_check_encoding($value, 'UTF-8')) { throw new \InvalidArgumentException('Use valid UTF-8 text.'); } }
        if (mb_strlen($alt) > 255) { throw new \InvalidArgumentException('Alt text: use up to 255 characters.'); }
        if (mb_strlen($category) > 191) { throw new \InvalidArgumentException('Category: use up to 191 characters.'); }
        $tags = [];
        foreach (explode(',', $text('tags')) as $tag) {
            $tag = trim($tag);
            if ($tag === '') { continue; }
            if (mb_strlen($tag) > 64) { throw new \InvalidArgumentException('Tags: use up to 64 characters each.'); }
            $tags[mb_strtolower($tag)] ??= $tag;
        }
        if (count($tags) > 20) { throw new \InvalidArgumentException('Use at most 20 tags per image.'); }
        return [$alt, $category, array_values($tags)];
    }

    private function saveTags(int $id, array $tags): void
    {
        foreach ($tags as $tag) { $this->db->query('INSERT INTO media_tags (media_id, tag) VALUES (?, ?)', [$id, $tag]); }
    }

    private function withTags(array $rows): array
    {
        if (!$rows) { return []; }
        $ids = array_map(static fn ($row) => (int) $row['id'], $rows);
        $tags = [];
        foreach ($this->db->all('SELECT media_id, tag FROM media_tags WHERE media_id IN (' . implode(', ', array_fill(0, count($ids), '?')) . ') ', $ids) as $tag) { $tags[(int) $tag['media_id']][] = $tag['tag']; }
        foreach ($tags as &$list) { usort($list, static fn ($a, $b) => strcmp(mb_strtolower($a), mb_strtolower($b))); }
        unset($list);
        return array_map(static fn ($row) => $row + ['tags' => $tags[(int) $row['id']] ?? []], $rows);
    }

    private static function searchText(string $name, string $alt, string $category, array $tags): string
    {
        return mb_strtolower(implode("\n", array_merge([$name, $alt, $category], $tags)));
    }
}
