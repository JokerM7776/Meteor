<?php
declare(strict_types=1);
namespace App\Services;
use App\Repositories\Comments;
use Core\{Captcha, Database};

/**
 * Public comment form without JavaScript. Spam checks: a hidden honeypot field, a signed form token
 * that must be at least a few seconds old, a per-visitor rate limit, a link limit, and an optional
 * CAPTCHA shown on its own page after the visitor presses Send.
 */
final class CommentForm
{
    public const MIN_SECONDS = 3;
    public const MAX_AGE = 172800;
    private const RATE_LIMIT = 5;
    private const RATE_WINDOW = 600;
    private const MAX_LINKS = 2;
    private CommentSettings $settings;
    /** @var ?callable */
    private $send;

    public function __construct(private Database $db, ?callable $send = null) { $this->settings = new CommentSettings($db); $this->send = $send; }

    public function settings(): CommentSettings { return $this->settings; }

    public function token(int $post, string $locale, ?int $time = null): string
    {
        $time ??= time();
        return $time . '.' . hash_hmac('sha256', "comment|$post|$locale|$time", CommentSettings::secret($this->db));
    }

    /** Seconds since the form was rendered, or null for a token that is not ours. */
    private function age(string $token, int $post, string $locale): ?int
    {
        if (!preg_match('/^(\d{1,12})\.[0-9a-f]{64}$/D', $token, $m) || !hash_equals($this->token($post, $locale, (int) $m[1]), $token)) { return null; }
        return time() - (int) $m[1];
    }

    /** Trimmed form values, also used to fill the form again after a problem. */
    public static function values(array $input): array
    {
        $text = fn (string $key, int $max) => is_string($input[$key] ?? null) && mb_check_encoding($input[$key], 'UTF-8') ? mb_substr(trim($input[$key]), 0, $max) : '';
        return ['name' => $text('name', 101), 'email' => $text('email', 192), 'url' => $text('url', 2049),
            'body' => $text('body', Comments::MAX_BODY + 1), 'parent_id' => (int) ($input['parent_id'] ?? 0), 'token' => $text('token', 100)];
    }

    /**
     * One of: ['result' => 'saved', 'status' => …, 'id' => …], ['result' => 'error', 'error' => key, 'field' => …],
     * ['result' => 'check'] (show the CAPTCHA page), or ['result' => 'dropped'] (looks automated; answer as if held).
     * Every result carries 'values'.
     */
    public function submit(array $post, string $locale, array $input, string $ip): array
    {
        $values = self::values($input);
        // Errors refill the form with a fresh token; the CAPTCHA page keeps the original one.
        $result = function (string $result, array $extra = []) use (&$values, $post, $locale): array {
            return ['result' => $result, 'values' => ($result === 'error' ? ['token' => $this->token((int) $post['id'], $locale)] : []) + $values] + $extra;
        };
        if (trim((string) ($input['homepage'] ?? '')) !== '') { return $result('dropped'); }
        $age = $this->age($values['token'], (int) $post['id'], $locale);
        if ($age !== null && $age < self::MIN_SECONDS) { return $result('dropped'); }
        if ($age === null || $age > self::MAX_AGE) { return $result('error', ['error' => 'comment_error_expired', 'field' => 'body']); }
        $values['email'] = mb_strtolower($values['email']);
        $error = match (true) {
            $values['name'] === '' || mb_strlen($values['name']) > 100 || preg_match('/[\x00-\x1f\x7f]/', $values['name']) => ['comment_error_name', 'name'],
            $values['email'] !== '' && (strlen($values['email']) > 191 || filter_var($values['email'], FILTER_VALIDATE_EMAIL) === false) => ['comment_error_email', 'email'],
            $values['url'] !== '' && !self::validUrl($values['url']) => ['comment_error_url', 'url'],
            $values['body'] === '' || mb_strlen($values['body']) > Comments::MAX_BODY => ['comment_error_text', 'body'],
            default => null,
        };
        if ($error) { return $result('error', ['error' => $error[0], 'field' => $error[1]]); }
        $since = gmdate('Y-m-d H:i:s', time() - self::RATE_WINDOW);
        $hash = CommentSettings::ipHash($this->db, $ip);
        if ($hash !== '' && (int) $this->db->one('SELECT COUNT(*) AS n FROM comments WHERE ip_hash = ? AND created_at >= ?', [$hash, $since])['n'] >= self::RATE_LIMIT) {
            return $result('error', ['error' => 'comment_error_rate', 'field' => 'body']);
        }
        if ($captcha = $this->settings->captcha()) {
            $answer = $input[$captcha['field']] ?? null;
            if (!is_string($answer)) { return $result('check'); }
            if (!(new Captcha($captcha['provider'], $captcha['secret'], $this->send))->passes($answer, $ip)) { return $result('check', ['error' => 'comment_error_check']); }
        }
        // Replies attach to a top-level comment of the same discussion (one level deep).
        $parent = $values['parent_id'] > 0 ? $this->db->one("SELECT id, parent_id FROM comments WHERE id = ? AND post_id = ? AND locale = ? AND status = 'approved'", [$values['parent_id'], $post['id'], $locale]) : null;
        $parentId = $parent ? (int) ($parent['parent_id'] ?? $parent['id']) : null;
        $id = $this->db->insert('INSERT INTO comments (post_id, locale, parent_id, kind, author_name, author_email, author_url, body, status, ip_hash, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$post['id'], $locale, $parentId, 'comment', $values['name'], $values['email'], $values['url'], $values['body'], $status = $this->status($values), $hash, gmdate('Y-m-d H:i:s')]);
        return $result('saved', ['id' => $id, 'status' => $status]);
    }

    private function status(array $values): string
    {
        if (preg_match_all('~https?://~i', $values['body'] . ' ' . $values['url']) > self::MAX_LINKS) { return 'pending'; }
        return match ($this->settings->values()['comments_moderation']) {
            'open' => 'approved',
            'returning' => $values['email'] !== '' && $this->db->one("SELECT id FROM comments WHERE author_email = ? AND status = 'approved' AND kind = 'comment' AND is_owner = 0", [$values['email']]) ? 'approved' : 'pending',
            default => 'pending',
        };
    }

    public static function validUrl(string $url): bool
    {
        return strlen($url) <= 2048 && !preg_match('/[\x00-\x20\x7f"<>\\\\]/', $url) && preg_match('~^https?://[^/?#]+~i', $url) === 1
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}
