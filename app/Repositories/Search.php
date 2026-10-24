<?php
declare(strict_types=1);
namespace App\Repositories;
use Core\Database;

/**
 * Site search over published posts and pages in one language. Portable LIKE queries (every word must
 * match the title, summary or text), ranked in PHP. Fine for blog-sized sites; no index to maintain.
 */
final class Search
{
    public const MAX_QUERY = 100;
    private const MAX_TERMS = 6;
    private const LIMIT = 50;

    public function __construct(private Database $db) {}

    /** Words to look for: unique, at most six, each up to 64 characters. */
    public static function terms(string $query): array
    {
        $words = preg_split('/\s+/u', mb_strtolower(trim($query), 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return array_slice(array_values(array_unique(array_map(fn ($w) => mb_substr($w, 0, 64), $words))), 0, self::MAX_TERMS);
    }

    public function find(string $locale, string $query): array
    {
        $terms = self::terms($query);
        if (!$terms) { return []; }
        $results = [];
        foreach ([false, true] as $page) {
            [$table, $translations, $foreign] = $page ? ['pages', 'page_translations', 'page_id'] : ['posts', 'post_translations', 'post_id'];
            $sql = "SELECT p.id, p.slug, t.title, t.summary, t.content_md" . ($page ? ', NULL AS published_at' : ', p.published_at')
                . " FROM $table p JOIN $translations t ON t.$foreign = p.id WHERE t.locale = ? AND t.status = 'published' AND p.is_published = 1";
            $params = [$locale];
            if (!$page) { $sql .= ' AND p.published_at <= ?'; $params[] = gmdate('Y-m-d H:i:s'); }
            foreach ($terms as $term) {
                // SQLite's LIKE ignores case for ASCII only, so other words are checked in PHP below.
                if ($this->db->driver() === 'sqlite' && preg_match('/[^\x00-\x7f]/', $term)) { continue; }
                $sql .= " AND (t.title LIKE ? ESCAPE '!' OR t.summary LIKE ? ESCAPE '!' OR t.content_md LIKE ? ESCAPE '!')";
                $like = '%' . strtr($term, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
                array_push($params, $like, $like, $like);
            }
            foreach ($this->db->all($sql . ' LIMIT 500', $params) as $row) {
                if ($page && in_array($row['slug'], Pages::RESERVED, true)) { continue; }
                // LIKE is case-insensitive for ASCII only; check every word again in PHP for other scripts.
                $text = self::plain($row['content_md']);
                $body = mb_strtolower($row['content_md'], 'UTF-8');
                $haystack = mb_strtolower($row['title'] . "\n" . $row['summary'], 'UTF-8') . "\n" . $body;
                $score = 0;
                foreach ($terms as $term) {
                    if (!str_contains($haystack, $term)) { continue 2; }
                    $score += (str_contains(mb_strtolower($row['title'], 'UTF-8'), $term) ? 5 : 0)
                        + (str_contains(mb_strtolower($row['summary'], 'UTF-8'), $term) ? 2 : 0)
                        + min(5, substr_count($body, $term));
                }
                $results[] = ['type' => $page ? 'page' : 'post', 'slug' => $row['slug'], 'title' => $row['title'], 'published_at' => $row['published_at'],
                    'score' => $score, 'snippet' => self::snippet($text !== '' ? $text : $row['summary'], $terms)];
            }
        }
        usort($results, fn ($a, $b) => [$b['score'], (string) $b['published_at']] <=> [$a['score'], (string) $a['published_at']]);
        return array_slice($results, 0, self::LIMIT);
    }

    /** Markdown source reduced to readable text for snippets. */
    public static function plain(string $markdown): string
    {
        $text = preg_replace(['/\[\^[^\]\s]+\]:?/', '/\s*\{#[^}]*\}/', '/^\s*\|?(?:\s*:?-+:?\s*\|)+(?:\s*:?-+:?)?\s*$|^\s*[-*_](?:\s*[-*_]){2,}\s*$/m', '/^\s*(`{3,}|~{3,}).*$/m', '/!\[([^\]]*)\]\([^)]*\)/', '/\[([^\]]*)\]\([^)]*\)/', '/^\s{0,3}(#{1,6}|>|[-+*]|\d+\.)\s+/m', '/[*_`~=|^]+/'], ['', '', '', '', '$1', '$1', '', ''], $markdown) ?? '';
        $text = (string) preg_replace_callback('/:([a-z0-9_+-]+):/', fn ($m) => \Core\Emoji::CODES[$m[1]] ?? $m[0], $text);
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /** Escaped HTML: about 180 characters around the first match, with every word wrapped in <mark>. */
    public static function snippet(string $text, array $terms): string
    {
        $lower = mb_strtolower($text, 'UTF-8');
        $first = null;
        foreach ($terms as $term) { $at = mb_strpos($lower, $term, 0, 'UTF-8'); if ($at !== false && ($first === null || $at < $first)) { $first = $at; } }
        $start = max(0, ($first ?? 0) - 60);
        $piece = mb_substr($text, $start, 180, 'UTF-8');
        // Split the raw text on the words first, then escape each part, so entities are never cut.
        $parts = preg_split('/(' . implode('|', array_map(fn ($t) => preg_quote($t, '/'), $terms)) . ')/iu', $piece, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$piece];
        $html = '';
        foreach ($parts as $n => $part) { $html .= $n % 2 ? '<mark>' . \e($part) . '</mark>' : \e($part); }
        return ($start > 0 ? '… ' : '') . $html . ($start + 180 < mb_strlen($text, 'UTF-8') ? ' …' : '');
    }
}
