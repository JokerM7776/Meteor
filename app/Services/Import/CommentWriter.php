<?php
declare(strict_types=1);
namespace App\Services\Import;
use Core\Database;

final class CommentWriter
{
    public function __construct(private Database $db, private Report $report) {}
    public function write(array $item, int $post): void
    {
        $parents = []; $secret = $this->db->one('SELECT value FROM settings WHERE `key` = ? AND locale_scope = ?', ['app_secret', ''])['value'] ?? '';
        foreach ($item['comments'] as $c) {
            if ($c['type'] !== 'comment') { $this->report->count('comments', 'skipped'); continue; }
            $status = match ($c['status']) { 'approved' => 'approved', 'spam' => 'spam', default => 'pending' };
            $this->report->commentStatuses[$status]++; $this->report->count('comments');
            if ($this->report->check) { continue; }
            $body = preg_replace('~<br\s*/?>~i', "\n", (string) $c['text']);
            $body = preg_replace('~</(?:p|div|blockquote)>~i', "\n\n", $body);
            $body = trim(html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $url = (string) $c['url']; if (!preg_match('~^https?://~i', $url)) { $url = ''; }
            $parents[$c['coid']] = $this->db->insert('INSERT INTO comments (post_id, locale, parent_id, kind, author_name, author_email, author_url, body, status, is_owner, ip_hash, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$post, $item['locale'], $parents[$c['parent']] ?? null, 'comment', mb_substr(trim($c['author']) ?: 'Anonymous', 0, 100),
                    mb_substr((string) $c['mail'], 0, 191), mb_substr($url, 0, 2048), $body, $status, (int) $c['is_owner'],
                    $secret !== '' && $c['ip'] !== '' ? hash_hmac('sha256', $c['ip'], $secret) : '', gmdate('Y-m-d H:i:s', (int) $c['created'])]);
        }
    }
}
