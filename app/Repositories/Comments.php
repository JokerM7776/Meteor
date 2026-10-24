<?php
declare(strict_types=1);
namespace App\Repositories;
use Core\Database;

/** Comments and received webmentions. Each post language has its own discussion. */
final class Comments
{
    public const MAX_BODY = 5000;
    public const REACTIONS = ['like', 'repost', 'bookmark', 'mention'];

    public function __construct(private Database $db) {}

    /** Approved items of one post in one language: threads (with one level of replies) and reactions. */
    public function discussion(int $post, string $locale): array
    {
        $rows = $this->db->all("SELECT * FROM comments WHERE post_id = ? AND locale = ? AND status = 'approved' ORDER BY created_at, id", [$post, $locale]);
        $threads = []; $reactions = [];
        foreach ($rows as $row) {
            if (self::isReaction($row)) { $reactions[] = $row; }
            elseif ($row['parent_id'] === null) { $threads[(int) $row['id']] = $row + ['replies' => []]; }
        }
        foreach ($rows as $row) {
            if ($row['parent_id'] !== null && !self::isReaction($row) && isset($threads[(int) $row['parent_id']])) { $threads[(int) $row['parent_id']]['replies'][] = $row; }
        }
        $count = count($threads) + array_sum(array_map(fn ($t) => count($t['replies']), $threads));
        return ['threads' => array_values($threads), 'reactions' => $reactions, 'count' => $count];
    }

    public static function isReaction(array $row): bool { return $row['kind'] === 'webmention' && in_array($row['mention_type'], self::REACTIONS, true); }

    public function find(int $id): ?array { return $this->db->one('SELECT * FROM comments WHERE id = ?', [$id]); }

    public function pendingCount(): int { return (int) ($this->db->one("SELECT COUNT(*) AS n FROM comments WHERE status = 'pending'")['n'] ?? 0); }

    /** Comment text as HTML: escaped, paragraphs and line breaks kept, http(s) links made clickable. */
    public static function html(string $body): string
    {
        $html = '';
        foreach (preg_split('/\n\s*\n/', trim(str_replace(["\r\n", "\r"], "\n", $body))) ?: [] as $paragraph) {
            $text = preg_replace_callback('~https?://[^\s<>"\']+~i', static function (array $m): string {
                $url = $m[0]; $tail = '';
                while ($url !== '' && preg_match('/[.,;:!?)]$/', $url) && !(str_ends_with($url, ')') && substr_count($url, '(') >= substr_count($url, ')'))) { $tail = substr($url, -1) . $tail; $url = substr($url, 0, -1); }
                return '<a href="' . $url . '" rel="nofollow ugc noopener">' . $url . '</a>' . $tail;
            }, \e($paragraph)) ?? \e($paragraph);
            $html .= '<p>' . nl2br($text, false) . '</p>';
        }
        return $html;
    }
}
