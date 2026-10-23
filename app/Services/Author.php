<?php
declare(strict_types=1);
namespace App\Services;
use App\Repositories\Settings;
use Core\Database;

/**
 * The site owner's profile, shown as a representative h-card (microformats2) in the footer
 * and on the home page. Name and bio are per language; avatar and profile links are shared.
 */
final class Author
{
    public const TEXT = [
        'author_name' => ['Name', 'Your name or pen name.', 191, false],
        'author_bio' => ['Short bio', 'One or two sentences about you.', 600, true],
    ];
    public const MAX_LINKS = 8;

    public function __construct(private Database $db) {}

    /** The profile in one language, or null when it has no name. */
    public static function card(Settings $settings, string $locale): ?array
    {
        $name = $settings->get('author_name', $locale);
        if ($name === '') { return null; }
        $avatar = $settings->global('author_avatar');
        return ['name' => $name, 'bio' => $settings->get('author_bio', $locale),
            'avatar' => SiteIcon::validPath($avatar) ? $avatar : '', 'links' => self::links($settings->global('author_links'))];
    }

    /** "Label | https://…" or a bare URL per line. A bare URL is labelled with its host name. */
    public static function links(string $text): array
    {
        $links = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') { continue; }
            [$label, $url] = str_contains($line, '|') ? array_map('trim', explode('|', $line, 2)) : ['', $line];
            if (!preg_match('~^(?:https?://[^/?#\s]|mailto:)~i', $url) || preg_match('/[\p{C}\s\\\\]/u', $url) || strlen($url) > 2048) { return []; }
            if ($label === '') { $label = str_starts_with(strtolower($url), 'mailto:') ? substr($url, 7) : preg_replace('/^www\./i', '', (string) parse_url($url, PHP_URL_HOST)); }
            $links[] = ['label' => $label, 'url' => $url, 'email' => str_starts_with(strtolower($url), 'mailto:')];
        }
        return $links;
    }

    public function save(array $input): void
    {
        $locales = array_column($this->db->all('SELECT code FROM locales'), 'code');
        $text = is_array($input['text'] ?? null) ? $input['text'] : [];
        foreach ($text as $key => $values) {
            if (!isset(self::TEXT[$key]) || !is_array($values)) { throw new \InvalidArgumentException('Unknown profile text.'); }
            foreach ($values as $code => $value) {
                if (!in_array((string) $code, $locales, true)) { throw new \InvalidArgumentException('Unknown language.'); }
                $value = trim(is_string($value) ? $value : '');
                if (!mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > self::TEXT[$key][2] || (!self::TEXT[$key][3] && preg_match('/\p{Cc}/u', $value))) {
                    throw new \InvalidArgumentException(self::TEXT[$key][0] . ' (' . $code . '): use up to ' . self::TEXT[$key][2] . ' characters.');
                }
            }
        }
        $links = trim(str_replace("\r\n", "\n", (string) ($input['author_links'] ?? '')));
        $lines = array_filter(array_map('trim', explode("\n", $links)), fn ($line) => $line !== '');
        if (count($lines) > self::MAX_LINKS) { throw new \InvalidArgumentException('Profile links: use at most ' . self::MAX_LINKS . ' links.'); }
        if ($lines && count(self::links($links)) !== count($lines)) { throw new \InvalidArgumentException('Profile links: put one link per line, starting with https://, http:// or mailto:, optionally as “Label | URL”.'); }
        $current = $this->db->one("SELECT value FROM settings WHERE `key` = 'author_avatar' AND locale_scope = ''")['value'] ?? '';
        $avatar = array_key_exists('author_avatar', $input) ? $this->avatar(is_string($input['author_avatar']) ? trim($input['author_avatar']) : '', $current) : $current;
        $this->db->transaction(function () use ($text, $links, $avatar) {
            foreach ($text as $key => $values) {
                foreach ($values as $code => $value) {
                    $this->db->query('DELETE FROM settings WHERE `key` = ? AND locale_scope = ?', [$key, (string) $code]);
                    if (trim((string) $value) !== '') { $this->db->upsert('settings', ['key' => $key, 'locale' => (string) $code, 'value' => trim((string) $value)], ['key', 'locale_scope']); }
                }
            }
            foreach (['author_links' => $links, 'author_avatar' => $avatar] as $key => $value) {
                $this->db->upsert('settings', ['key' => $key, 'locale' => null, 'value' => $value], ['key', 'locale_scope']);
            }
        });
    }

    private function avatar(string $url, string $current): string
    {
        if ($url === '' || $url === $current) { return $url; }
        $name = substr($url, strlen(MediaLibrary::DIRECTORY));
        if (str_starts_with($url, MediaLibrary::DIRECTORY) && SiteIcon::validPath($url) && $this->db->one('SELECT id FROM media WHERE file_name = ?', [$name])) { return $url; }
        throw new \InvalidArgumentException('Avatar: choose a PNG, JPG, WebP or GIF image from the media library.');
    }
}
