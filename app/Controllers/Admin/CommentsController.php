<?php
declare(strict_types=1);
namespace App\Controllers\Admin;
use App\Repositories\Comments;
use App\Services\{CommentSettings, Webmentions};
use Core\{Database, I18n};

/** /admin/comments: moderation table, owner replies, comment and webmention settings. */
final class CommentsController
{
    public const PER_PAGE = 30;
    public const STATUSES = ['pending' => 'Pending', 'approved' => 'Approved', 'spam' => 'Spam', 'all' => 'All'];
    private const ACTIONS = ['approve' => 'approved', 'pending' => 'pending', 'spam' => 'spam', 'delete' => null];

    public function __construct(private Database $db, private I18n $i18n, private \Closure $render, private \Closure $go, private array $user) {}

    public function handle(string $path, bool $post): ?string
    {
        if ($path === '/admin/comments') {
            if ($post) { return $this->moderate(); }
            return ($_GET['tab'] ?? '') === 'settings' ? $this->settings() : $this->index();
        }
        if ($path === '/admin/comments/settings' && $post) {
            try { (new CommentSettings($this->db))->save($_POST); $message = 'Changes saved.'; }
            catch (\InvalidArgumentException $e) { $_SESSION['old']['comments'] = $_POST; $message = $e->getMessage(); }
            return ($this->go)('/admin/comments?tab=settings', $message);
        }
        if ($path === '/admin/comments/process' && $post) {
            $done = (new Webmentions($this->db, $GLOBALS['config']['app_url']))->process(20);
            return ($this->go)('/admin/comments?tab=settings', $done === 0 ? 'No webmentions were waiting.' : $done . ' webmention' . ($done === 1 ? '' : 's') . ' processed.');
        }
        if (preg_match('~^/admin/comments/(\d{1,10})/reply$~D', $path, $m)) { return $this->reply((int) $m[1], $post); }
        return null;
    }

    private function status(): string { $s = $_GET['status'] ?? 'pending'; return is_string($s) && isset(self::STATUSES[$s]) ? $s : 'pending'; }

    private function index(): string
    {
        $status = $this->status();
        $counts = array_column($this->db->all('SELECT status, COUNT(*) AS n FROM comments GROUP BY status'), 'n', 'status');
        $counts['all'] = array_sum($counts);
        $total = (int) ($status === 'all' ? $counts['all'] : ($counts[$status] ?? 0));
        $page = max(1, min((int) ($_GET['page'] ?? 1), max(1, (int) ceil($total / self::PER_PAGE))));
        $where = $status === 'all' ? '' : 'WHERE c.status = ?';
        // LIMIT/OFFSET are integers built here, never request text.
        $rows = $this->db->all("SELECT c.*, t.title AS post_title, p.slug AS post_slug, parent.author_name AS parent_name FROM comments c
            JOIN posts p ON p.id = c.post_id LEFT JOIN post_translations t ON t.post_id = c.post_id AND t.locale = c.locale
            LEFT JOIN comments parent ON parent.id = c.parent_id $where ORDER BY c.created_at DESC, c.id DESC LIMIT " . self::PER_PAGE . ' OFFSET ' . (($page - 1) * self::PER_PAGE),
            $status === 'all' ? [] : [$status]);
        return ($this->render)('comments', 'Comments', ['tab' => $status, 'rows' => $rows, 'counts' => $counts, 'page' => $page, 'total' => $total,
            'enabled' => (new CommentSettings($this->db))->enabled()]);
    }

    private function moderate(): string
    {
        $row = is_string($_POST['row'] ?? null) ? $_POST['row'] : '';
        [$action, $ids] = preg_match('/^([a-z]+):(\d{1,10})$/D', $row, $m) ? [$m[1], [(int) $m[2]]]
            : [(string) ($_POST['action'] ?? ''), array_values(array_unique(array_map('intval', array_filter(is_array($_POST['ids'] ?? null) ? $_POST['ids'] : [], 'is_string'))))];
        $back = '/admin/comments?' . http_build_query(['status' => $this->status(), 'page' => max(1, (int) ($_GET['page'] ?? 1))]);
        if (!array_key_exists($action, self::ACTIONS)) { return ($this->go)($back, 'Choose an action.'); }
        $ids = array_filter($ids, fn ($id) => $id > 0);
        if (!$ids) { return ($this->go)($back, 'Select at least one comment.'); }
        $marks = implode(', ', array_fill(0, count($ids), '?'));
        if ($action === 'delete') { $this->db->query("DELETE FROM comments WHERE id IN ($marks)", $ids); }
        else { $this->db->query("UPDATE comments SET status = ? WHERE id IN ($marks)", [self::ACTIONS[$action], ...$ids]); }
        $label = ['approve' => 'approved', 'pending' => 'moved to pending', 'spam' => 'marked as spam', 'delete' => 'deleted'][$action];
        return ($this->go)($back, count($ids) . ' comment' . (count($ids) === 1 ? '' : 's') . ' ' . $label . '.');
    }

    private function reply(int $id, bool $post): string
    {
        $comment = (new Comments($this->db))->find($id);
        if (!$comment || Comments::isReaction($comment)) { http_response_code(404); return ($this->render)('message', 'Not found', ['message' => 'This comment no longer exists or cannot be answered.']); }
        $error = '';
        if ($post) {
            $body = is_string($_POST['body'] ?? null) ? trim($_POST['body']) : '';
            if ($body === '' || mb_strlen($body) > Comments::MAX_BODY) { $error = 'Write a reply of up to 5,000 characters.'; }
            else {
                $settings = new \App\Repositories\Settings($this->db, $this->i18n);
                $name = $settings->get('author_name', $comment['locale']) ?: $settings->get('site_title', $comment['locale']) ?: $this->user['username'];
                $this->db->transaction(function () use ($comment, $body, $name) {
                    // Answering a held comment publishes it too.
                    $this->db->query("UPDATE comments SET status = 'approved' WHERE id IN (?, ?) AND status = 'pending'", [$comment['id'], $comment['parent_id'] ?? $comment['id']]);
                    $this->db->insert("INSERT INTO comments (post_id, locale, parent_id, kind, author_name, author_url, body, status, is_owner, created_at) VALUES (?, ?, ?, 'comment', ?, ?, ?, 'approved', 1, ?)",
                        [$comment['post_id'], $comment['locale'], $comment['parent_id'] ?? $comment['id'], mb_substr($name, 0, 100), absolute_url('/' . $comment['locale'] . '/'), $body, gmdate('Y-m-d H:i:s')]);
                });
                return ($this->go)('/admin/comments?status=approved', 'Reply published.');
            }
        }
        $postRow = $this->db->one('SELECT p.slug, t.title FROM posts p LEFT JOIN post_translations t ON t.post_id = p.id AND t.locale = ? WHERE p.id = ?', [$comment['locale'], $comment['post_id']]);
        return ($this->render)('comment-reply', 'Reply to comment', ['comment' => $comment, 'post' => $postRow, 'error' => $error, 'body' => (string) ($_POST['body'] ?? '')]);
    }

    private function settings(): string
    {
        $settings = new CommentSettings($this->db);
        $old = $_SESSION['old']['comments'] ?? []; unset($_SESSION['old']['comments']);
        $queue = array_column($this->db->all("SELECT direction, COUNT(*) AS n FROM webmentions WHERE status = 'queued' GROUP BY direction"), 'n', 'direction');
        return ($this->render)('comments', 'Comments', ['tab' => 'settings', 'values' => $old + $settings->values(), 'captchaName' => \Core\Captcha::PROVIDERS[(new \App\Services\CaptchaSettings($this->db))->values()['captcha_provider']]['name'], 'captchaReady' => $settings->provider() !== null,
            'queue' => $queue, 'lastRun' => $this->db->one('SELECT value FROM settings WHERE `key` = ? AND locale_scope = ?', ['webmention_last_run', ''])['value'] ?? '',
            'root' => dirname(__DIR__, 3)]);
    }
}
