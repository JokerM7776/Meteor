<?php
declare(strict_types=1);
namespace App\Services\Import;
use PDO;

final class TypechoSource
{
    public array $warnings = [];
    public int $skipped = 0;
    private PDO $pdo;
    private string $prefix;
    private array $attachments = [];
    public function __construct(array $options, private string $target, private ?Files $uploads = null, private string $site = '')
    {
        $this->prefix = (string) ($options['prefix'] ?? 'typecho_');
        if (!preg_match('/^[A-Za-z0-9_]{0,32}$/D', $this->prefix)) { throw new \InvalidArgumentException('Invalid Typecho table prefix.'); }
        $driver = $options['driver'] ?? 'mysql';
        $pdoOptions = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_TIMEOUT => 5];
        if ($driver === 'sqlite') {
            $path = $options['file'] ?? ''; $head = is_file($path) ? file_get_contents($path, false, null, 0, 16) : '';
            if ($head !== "SQLite format 3\0") { throw new \InvalidArgumentException('Choose a valid Typecho SQLite database file.'); }
            if (class_exists(\Pdo\Sqlite::class)) { $pdoOptions[\Pdo\Sqlite::ATTR_OPEN_FLAGS] = \Pdo\Sqlite::OPEN_READONLY; }
            elseif (defined('PDO::SQLITE_ATTR_OPEN_FLAGS')) { $pdoOptions[PDO::SQLITE_ATTR_OPEN_FLAGS] = PDO::SQLITE_OPEN_READONLY; }
            else { throw new \RuntimeException('This PHP version cannot open SQLite read-only.'); }
            $dsn = 'sqlite:' . realpath($path);
        } elseif ($driver === 'mysql') {
            foreach (['host', 'port', 'database'] as $key) { if (preg_match('/[;\x00-\x1f]/', (string) ($options[$key] ?? ''))) { throw new \InvalidArgumentException('Invalid database connection setting.'); } }
            $port = (string) ($options['port'] ?? '3306');
            if (!ctype_digit($port) || (int) $port < 1 || (int) $port > 65535) { throw new \InvalidArgumentException('Invalid database port.'); }
            $dsn = 'mysql:host=' . ($options['host'] ?? '127.0.0.1') . ';port=' . $port . ';dbname=' . ($options['database'] ?? '') . ';charset=utf8mb4';
        } else { throw new \InvalidArgumentException('PostgreSQL is not supported. Export Typecho to MySQL or SQLite first.'); }
        try { $this->pdo = new PDO($dsn, $options['user'] ?? null, $options['password'] ?? null, $pdoOptions); }
        catch (\PDOException) { throw new \RuntimeException('Cannot connect to Typecho. Check the database details and read permissions.'); }
        if (!$uploads) { $this->warnings[] = 'No uploads archive supplied; local image URLs will be kept.'; }
    }
    private function rows(string $table, bool $optional = false): array
    {
        try { $statement = $this->pdo->prepare('SELECT * FROM `' . $this->prefix . $table . '`'); $statement->execute(); return $statement->fetchAll(); }
        catch (\PDOException $e) {
            if ($optional && (str_contains($e->getMessage(), 'no such table') || ($e->errorInfo[1] ?? null) === 1146)) { return []; }
            throw new \RuntimeException('Cannot read Typecho tables. Check the table prefix and SELECT permissions.');
        }
    }
    public static function attachmentPath(string $text): ?string
    {
        return preg_match('/s:4:"path";s:\d+:"([^"]*)";/', $text, $m) ? $m[1] : null;
    }
    public function items(): array
    {
        $metas = array_column($this->rows('metas'), null, 'mid'); $relations = []; $comments = []; $fields = [];
        foreach ($this->rows('relationships') as $r) { if (isset($metas[$r['mid']])) { $relations[$r['cid']][] = $metas[$r['mid']]; } }
        foreach ($this->rows('comments') as $c) { $comments[$c['cid']][] = $c; }
        foreach ($this->rows('fields', true) as $f) { if (in_array($f['name'], ['thumb', 'thumbnail', 'cover', 'banner', 'img', 'image'], true)) { $fields[$f['cid']] ??= $f['str_value']; } }
        $items = [];
        foreach ($this->rows('contents') as $row) {
            $type = $row['type'];
            if ($type === 'attachment') {
                $path = self::attachmentPath((string) $row['text']);
                if ($path !== null) { $this->attachments[(string) $row['cid']] = $path; }
                continue;
            }
            if (in_array($type, ['post_draft', 'page_draft'], true) && (int) $row['parent'] > 0) { $this->skipped++; continue; }
            if (!in_array($type, ['post', 'page', 'post_draft', 'page_draft'], true)) { continue; }
            $kind = str_starts_with($type, 'page') ? 'page' : 'post'; $warnings = [];
            $md = (string) $row['text']; $markdown = str_starts_with($md, '<!--markdown-->');
            if ($markdown) { $md = substr($md, 15); }
            $summary = HtmlToMarkdown::summary($md);
            $md = $markdown ? HtmlToMarkdown::inline(ReferenceLinks::convert($md), $warnings) : HtmlToMarkdown::convert($md, $warnings);
            $status = $row['status'] === 'publish' && !str_ends_with($type, '_draft') ? 'published' : 'draft';
            if (in_array($row['status'], ['hidden', 'private', 'waiting'], true)) { $warnings[] = 'Was ' . $row['status'] . '; imported as draft.'; }
            if (trim((string) ($row['password'] ?? '')) !== '') { $status = 'draft'; $warnings[] = 'Was password protected; imported as draft.'; }
            $terms = $relations[$row['cid']] ?? []; usort($terms, fn ($a, $b) => [(int) $a['order'], (int) $a['mid']] <=> [(int) $b['order'], (int) $b['mid']]);
            $category = null; $description = ''; $tags = [];
            foreach ($terms as $term) {
                if ($term['type'] === 'category' && $category === null) { $category = $term['name']; $description = $term['description']; }
                else { $tags[] = $term['name']; if ($term['type'] === 'category') { $warnings[] = 'Extra categories converted to tags.'; } }
            }
            $slug = (string) $row['slug'];
            if (!Writer::validSlug($slug)) { $slug = \App\Services\ContentEditor::slugFrom($row['title'], $kind . '-' . $row['cid']); }
            $list = $comments[$row['cid']] ?? []; usort($list, fn ($a, $b) => (int) $a['coid'] <=> (int) $b['coid']);
            foreach ($list as &$c) { $c['is_owner'] = (int) $c['authorId'] > 0 && ((int) $c['authorId'] === (int) $row['authorId'] || (int) $c['authorId'] === (int) $c['ownerId']); } unset($c);
            $items[] = ['kind' => $kind, 'group' => $kind . ':' . $row['cid'], 'locale' => $this->target, 'slug' => $slug, 'title' => $row['title'],
                'summary' => $summary, 'markdown' => $md, 'status' => $status, 'date' => gmdate('Y-m-d H:i:s', (int) $row['created']),
                'updated' => gmdate('Y-m-d H:i:s', (int) $row['modified']), 'category' => $category, 'category_description' => $description,
                'tags' => $tags, 'cover' => $fields[$row['cid']] ?? null, 'pinned' => false, 'comments_enabled' => (bool) $row['allowComment'],
                'sort_order' => (int) $row['order'], 'comments' => $list, 'source' => (string) $row['cid'], 'warnings' => $warnings];
        }
        return $items;
    }
    public function image(string $url, array $item): ?array
    {
        $url = $this->attachments[$url] ?? $url;
        $path = rawurldecode((string) (parse_url($url, PHP_URL_PATH) ?? ''));
        if (!preg_match('~(?:^|/)usr/uploads/(.+)$~', $path, $m)) { return preg_match('~^(https?:)?//~i', $url) ? null : [$url, null]; }
        if (parse_url($url, PHP_URL_HOST) && $this->site !== '' && strtolower((string) parse_url($url, PHP_URL_HOST)) !== strtolower((string) parse_url($this->site, PHP_URL_HOST))) { return null; }
        if ($this->uploads) {
            foreach ($this->uploads->names() as $name) {
                if ($name === $m[1] || str_ends_with($name, '/usr/uploads/' . $m[1]) || $name === 'usr/uploads/' . $m[1] || $name === 'uploads/' . $m[1]) { return [$name, $this->uploads->read($name, \App\Services\MediaLibrary::MAX_BYTES)]; }
            }
        }
        return [$url, null];
    }
}
