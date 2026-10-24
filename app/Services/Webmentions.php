<?php
declare(strict_types=1);
namespace App\Services;
use App\Repositories\Posts;
use Core\{Database, SafeHttp};

/**
 * Webmention (W3C Recommendation) receiving and sending through one queue. Incoming mentions are
 * accepted with 202 and verified later; outgoing ones are queued when a post is published. The
 * queue runs after the response (PHP-FPM, LiteSpeed), from bin/cron.php, or from the admin.
 */
final class Webmentions
{
    private const MAX_ATTEMPTS = 5;
    private const RATE_LIMIT = 60;
    private const MAX_TARGETS = 50;
    private SafeHttp $http;
    private CommentSettings $settings;

    public function __construct(private Database $db, private string $appUrl, ?SafeHttp $http = null)
    {
        $this->http = $http ?? new SafeHttp();
        $this->settings = new CommentSettings($db);
        $this->appUrl = rtrim($appUrl, '/');
    }

    public function endpoint(): string { return $this->appUrl . '/webmention'; }

    /** Runs $work after the response has been sent, when the server can do that; otherwise the queue waits. */
    public static function later(\Closure $work): void
    {
        register_shutdown_function(static function () use ($work): void {
            if (function_exists('fastcgi_finish_request')) { fastcgi_finish_request(); }
            elseif (function_exists('litespeed_finish_request')) { litespeed_finish_request(); }
            else { return; }
            ignore_user_abort(true);
            try { $work(); } catch (\Throwable $error) { error_log((string) $error); }
        });
    }

    /** POST /webmention. Returns [HTTP status, plain-text message]. */
    public function receive(mixed $source, mixed $target, string $ip): array
    {
        if (!$this->settings->receivesWebmentions()) { return [404, 'Webmentions are not accepted here.']; }
        if (!is_string($source) || !is_string($target) || !$this->validUrl($source) || !$this->validUrl($target)) { return [400, 'Send "source" and "target" as http(s) URLs.']; }
        if (self::normalize($source) === self::normalize($target)) { return [400, 'Source and target must differ.']; }
        if (!$this->targetPost($target)) { return [400, 'The target is not a post on this site that accepts webmentions.']; }
        $hash = CommentSettings::ipHash($this->db, $ip);
        $since = gmdate('Y-m-d H:i:s', time() - 3600);
        if ($hash !== '' && (int) $this->db->one("SELECT COUNT(*) AS n FROM webmentions WHERE direction = 'in' AND ip_hash = ? AND updated_at >= ?", [$hash, $since])['n'] >= self::RATE_LIMIT) {
            return [429, 'Too many webmentions. Try again later.'];
        }
        $this->queue('in', $source, $target, gmdate('Y-m-d H:i:s'), $hash);
        return [202, 'Accepted. The mention will be verified, then moderated.'];
    }

    /** Queues mentions for the links of a published post translation (and for links removed since last time). */
    public function queueOutgoing(int $postId, string $locale): int
    {
        if (!$this->settings->sendsWebmentions()) { return 0; }
        $row = $this->db->one("SELECT p.slug, p.published_at, t.content_html FROM posts p JOIN post_translations t ON t.post_id = p.id
            JOIN locales l ON l.code = t.locale WHERE p.id = ? AND t.locale = ? AND p.is_published = 1 AND t.status = 'published' AND l.status = 'live'", [$postId, $locale]);
        if (!$row || $row['published_at'] === null) { return 0; }
        $source = $this->appUrl . '/' . $locale . '/posts/' . rawurlencode($row['slug']);
        $host = strtolower((string) parse_url($this->appUrl, PHP_URL_HOST));
        $targets = [];
        preg_match_all('/<a\s[^>]*href="([^"]+)"/i', $row['content_html'], $links);
        foreach ($links[1] as $href) {
            $href = html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($this->validUrl($href) && strtolower((string) parse_url($href, PHP_URL_HOST)) !== $host) { $targets[self::normalize($href)] = preg_replace('/#.*$/', '', $href); }
        }
        foreach ($this->db->all("SELECT target FROM webmentions WHERE direction = 'out' AND source = ?", [$source]) as $old) { $targets[self::normalize($old['target'])] ??= $old['target']; }
        $when = max(gmdate('Y-m-d H:i:s'), $row['published_at']);
        foreach (array_slice($targets, 0, self::MAX_TARGETS) as $target) { $this->queue('out', $source, $target, $when); }
        return min(count($targets), self::MAX_TARGETS);
    }

    private function queue(string $direction, string $source, string $target, string $when, string $ipHash = ''): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $this->db->upsert('webmentions', ['direction' => $direction, 'source' => $source, 'target' => $target, 'pair_hash' => hash('sha256', "$direction\n$source\n$target"),
            'status' => 'queued', 'attempts' => 0, 'result' => '', 'ip_hash' => $ipHash, 'next_try_at' => $when, 'updated_at' => $now], ['pair_hash']);
    }

    /** Works through due queue items. Returns how many were handled. */
    public function process(int $limit = 20): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $this->db->upsert('settings', ['key' => 'webmention_last_run', 'locale' => null, 'value' => $now], ['key', 'locale_scope']);
        $done = 0;
        foreach ($this->db->all("SELECT * FROM webmentions WHERE status = 'queued' AND next_try_at <= ? ORDER BY next_try_at, id LIMIT " . max(1, $limit), [$now]) as $row) {
            // Claim the row so a cron run and an after-response run never handle it twice.
            $claim = $this->db->query("UPDATE webmentions SET next_try_at = ? WHERE id = ? AND status = 'queued' AND next_try_at = ?", [gmdate('Y-m-d H:i:s', time() + 600), $row['id'], $row['next_try_at']]);
            if ($claim->rowCount() !== 1) { continue; }
            try {
                [$status, $result] = $row['direction'] === 'in' ? $this->verify($row) : $this->send($row);
                $this->db->query('UPDATE webmentions SET status = ?, result = ?, attempts = attempts + 1, updated_at = ? WHERE id = ?', [$status, mb_substr($result, 0, 255), gmdate('Y-m-d H:i:s'), $row['id']]);
            } catch (\RuntimeException $error) {
                $attempts = (int) $row['attempts'] + 1;
                $this->db->query('UPDATE webmentions SET status = ?, result = ?, attempts = ?, next_try_at = ?, updated_at = ? WHERE id = ?',
                    [$attempts >= self::MAX_ATTEMPTS ? 'failed' : 'queued', mb_substr($error->getMessage(), 0, 255), $attempts, gmdate('Y-m-d H:i:s', time() + 300 * 2 ** $attempts), gmdate('Y-m-d H:i:s'), $row['id']]);
            }
            $done++;
        }
        return $done;
    }

    /** Incoming: fetch the source, check that it links to the target, then add, update or remove the mention. */
    private function verify(array $row): array
    {
        $post = $this->targetPost($row['target']);
        if (!$post) { return ['failed', 'Target no longer accepts webmentions.']; }
        $response = $this->http->get($row['source'], ['Accept: text/html, */*;q=0.5']);
        if ($response['status'] >= 500 || $response['status'] === 429) { throw new \RuntimeException('Source answered ' . $response['status'] . '.'); }
        $html = $response['status'] >= 200 && $response['status'] < 300 ? $response['body'] : '';
        $existing = $this->db->one("SELECT id FROM comments WHERE kind = 'webmention' AND source_url = ? AND post_id = ? AND locale = ?", [$row['source'], $post['id'], $post['locale']]);
        if ($html === '' || !$this->linksTo($html, $response['url'], $row['target'])) {
            if ($existing) { $this->db->query('DELETE FROM comments WHERE id = ?', [$existing['id']]); }
            return ['done', $response['status'] === 410 ? 'Source deleted; mention removed.' : 'Source does not link to the target.'];
        }
        $entry = self::entry($html, $response['url'], $row['target']);
        if ($existing) {
            $this->db->query('UPDATE comments SET mention_type = ?, author_name = ?, author_url = ?, body = ? WHERE id = ?', [$entry['type'], $entry['name'], $entry['url'], $entry['body'], $existing['id']]);
            return ['done', 'Mention updated.'];
        }
        $mode = $this->settings->values()['comments_moderation'];
        $host = strtolower((string) parse_url($row['source'], PHP_URL_HOST));
        $known = $mode === 'returning' && $this->db->one("SELECT id FROM comments WHERE kind = 'webmention' AND status = 'approved' AND (source_url LIKE ? OR source_url LIKE ?)", ['http://' . $host . '/%', 'https://' . $host . '/%']);
        $this->db->insert('INSERT INTO comments (post_id, locale, kind, mention_type, author_name, author_url, body, source_url, status, ip_hash, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$post['id'], $post['locale'], 'webmention', $entry['type'], $entry['name'], $entry['url'], $entry['body'], $row['source'], $mode === 'open' || $known ? 'approved' : 'pending', $row['ip_hash'], gmdate('Y-m-d H:i:s')]);
        return ['done', 'Mention received.'];
    }

    /** Outgoing: discover the target's endpoint and notify it. */
    private function send(array $row): array
    {
        $endpoint = $this->discover($row['target']);
        if ($endpoint === null) { return ['done', 'The target has no webmention endpoint.']; }
        $response = $this->http->post($endpoint, ['source' => $row['source'], 'target' => $row['target']]);
        if ($response['status'] >= 500 || $response['status'] === 429) { throw new \RuntimeException('Endpoint answered ' . $response['status'] . '.'); }
        return $response['status'] >= 200 && $response['status'] < 300 ? ['done', 'Sent (' . $response['status'] . ').'] : ['failed', 'Endpoint refused it (' . $response['status'] . ').'];
    }

    /** Endpoint from the HTTP Link header first, then the first <link> or <a> with rel="webmention". */
    public function discover(string $target): ?string
    {
        $response = $this->http->get($target, ['Accept: text/html, */*;q=0.5']);
        if ($response['status'] < 200 || $response['status'] >= 300) { return null; }
        foreach ($response['headers']['link'] ?? [] as $header) {
            foreach (preg_split('/,(?=\s*<)/', $header) ?: [] as $link) {
                if (preg_match('/^\s*<([^>]*)>(.*)$/s', $link, $m) && preg_match('/;\s*rel\s*=\s*(?:"([^"]*)"|([^\s";,]+))/i', $m[2], $rel)
                    && in_array('webmention', preg_split('/\s+/', strtolower(($rel[1] ?? '') !== '' ? $rel[1] : ($rel[2] ?? ''))), true)) {
                    return SafeHttp::resolve($response['url'], $m[1]);
                }
            }
        }
        if (!str_contains(strtolower($response['headers']['content-type'][0] ?? 'text/html'), 'html')) { return null; }
        $xpath = self::xpath($response['body']);
        foreach ($xpath?->query('//link[@href] | //a[@href]') ?? [] as $node) {
            if (in_array('webmention', preg_split('/\s+/', strtolower(trim($node->getAttribute('rel')))), true)) { return SafeHttp::resolve($response['url'], $node->getAttribute('href')); }
        }
        return null;
    }

    /** The published post (id, locale) a target URL points to, if it accepts webmentions. */
    private function targetPost(string $target): ?array
    {
        $parts = parse_url($target);
        $site = parse_url($this->appUrl);
        if (strtolower($parts['host'] ?? '') !== strtolower($site['host'] ?? '') || ($parts['port'] ?? null) !== ($site['port'] ?? null)) { return null; }
        if (!preg_match('~^/([^/]+)/posts/([^/]+)/?$~D', rawurldecode($parts['path'] ?? ''), $m)) { return null; }
        if (!$this->db->one("SELECT code FROM locales WHERE code = ? AND status = 'live'", [$m[1]])) { return null; }
        $post = (new Posts($this->db))->find($m[1], $m[2]);
        return $post && (int) $post['comments_enabled'] === 1 && $this->settings->enabled() ? ['id' => (int) $post['id'], 'locale' => $m[1]] : null;
    }

    private function validUrl(string $url): bool { return CommentForm::validUrl($url); }

    /** Comparable form of a URL: lower-case scheme and host, no fragment, no trailing slash. */
    public static function normalize(string $url): string
    {
        $url = preg_replace('/#.*$/', '', trim($url));
        return rtrim((string) preg_replace_callback('~^([a-z][a-z0-9+.-]*://[^/?#]+)~i', fn ($m) => strtolower($m[1]), $url), '/');
    }

    private function linksTo(string $html, string $base, string $target): bool
    {
        $wanted = preg_replace('~^https?://~i', '', self::normalize($target));
        preg_match_all('/\s(?:href|src)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $html, $m, PREG_SET_ORDER);
        foreach ($m as $attribute) {
            $raw = ($attribute[3] ?? '') !== '' ? $attribute[3] : (($attribute[2] ?? '') !== '' ? $attribute[2] : $attribute[1]);
            $value = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (preg_replace('~^https?://~i', '', self::normalize(SafeHttp::resolve($base, $value))) === $wanted) { return true; }
        }
        return false;
    }

    private static function xpath(string $html): ?\DOMXPath
    {
        if (!class_exists(\DOMDocument::class) || trim($html) === '') { return null; }
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors(); libxml_use_internal_errors($previous);
        return new \DOMXPath($document);
    }

    /** Type, author and text of the source page, from microformats2 (h-entry, h-card) when present. */
    public static function entry(string $html, string $base, string $target): array
    {
        $host = (string) parse_url($base, PHP_URL_HOST);
        $entry = ['type' => 'mention', 'name' => $host, 'url' => '', 'body' => ''];
        $xpath = self::xpath($html);
        if (!$xpath) { return $entry; }
        $class = fn (string $name) => "contains(concat(' ', normalize-space(@class), ' '), ' $name ')";
        $text = fn (?\DOMNode $node) => $node ? trim((string) preg_replace('/\s+/u', ' ', $node->textContent)) : '';
        $root = $xpath->query("//*[{$class('h-entry')}]")->item(0);
        $title = $text($xpath->query('//title')->item(0));
        if (!$root) { $entry['body'] = mb_substr($title, 0, 300); return $entry; }
        $wanted = self::normalize($target);
        foreach (['u-in-reply-to' => 'reply', 'u-like-of' => 'like', 'u-repost-of' => 'repost', 'u-bookmark-of' => 'bookmark'] as $property => $type) {
            foreach ($xpath->query(".//*[{$class($property)}]", $root) as $node) {
                $href = $node->getAttribute('href') ?: $text($node);
                if (self::normalize(SafeHttp::resolve($base, $href)) === $wanted) { $entry['type'] = $type; break 2; }
            }
        }
        $author = $xpath->query(".//*[{$class('p-author')}]", $root)->item(0) ?? $xpath->query("//*[{$class('h-card')}]")->item(0);
        if ($author) {
            $name = $xpath->query(".//*[{$class('p-name')}]", $author)->item(0);
            $entry['name'] = mb_substr($text($name ?? $author) ?: $host, 0, 100);
            $link = $author->getAttribute('href') ?: ($xpath->query(".//*[{$class('u-url')}]/@href", $author)->item(0)?->nodeValue ?? '');
            $url = $link !== '' ? SafeHttp::resolve($base, $link) : '';
            $entry['url'] = CommentForm::validUrl($url) ? $url : '';
        }
        $content = $xpath->query(".//*[{$class('e-content')}] | .//*[{$class('p-content')}]", $root)->item(0)
            ?? $xpath->query(".//*[{$class('p-summary')}]", $root)->item(0) ?? $xpath->query(".//*[{$class('p-name')}]", $root)->item(0);
        $entry['body'] = mb_substr($text($content) ?: $title, 0, 1000);
        return $entry;
    }
}
