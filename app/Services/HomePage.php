<?php
declare(strict_types=1);
namespace App\Services;
use App\Repositories\Settings;
use Core\Database;

/** What the home page shows, in which order, plus its intro text and buttons. */
final class HomePage
{
    /** Section => [name, description, shown by default]. */
    public const SECTIONS = [
        'intro' => ['Intro', 'Heading, introduction and buttons.', true],
        'pinned' => ['Pinned posts', 'Posts marked “Pinned”, as large cards.', true],
        'latest' => ['Latest posts', 'The newest posts as a list.', true],
        'categories' => ['Categories', 'Links to every category with its number of posts.', false],
        'author' => ['Author', 'Your photo, name, bio and profile links (set them on the Author tab).', false],
    ];
    public const TEXT = [
        'home_eyebrow' => ['Small line above the heading', 'Optional, e.g. “Notes on design and code”.', 191, false],
        'home_heading' => ['Heading', 'Empty uses the site title.', 191, false],
        'home_intro' => ['Introduction', 'Optional. Also the description for search engines.', 2000, true],
    ];
    public const BUTTONS = [1 => ['/{locale}/posts', 'read_archive'], 2 => ['/{locale}/rss.xml', 'language_rss']];
    public const COUNTS = [3, 5, 8, 10, 15, 20];

    public function __construct(private Database $db) {}

    /** Sections in display order: key => shown (bool). */
    public static function sections(Settings $settings): array
    {
        $sections = [];
        foreach (explode(',', $settings->global('home_sections')) as $part) {
            [$key, $shown] = array_pad(explode(':', trim($part), 2), 2, '1');
            if (isset(self::SECTIONS[$key]) && !isset($sections[$key])) { $sections[$key] = $shown === '1'; }
        }
        foreach (self::SECTIONS as $key => [, , $default]) { $sections[$key] ??= $default; }
        return $sections;
    }

    public static function latestCount(Settings $settings): int
    {
        $count = (int) $settings->global('home_latest_count', '5');
        return in_array($count, self::COUNTS, true) ? $count : 5;
    }

    /** Buttons to show in one language: [label, url]. */
    public static function buttons(Settings $settings, string $locale, callable $translate): array
    {
        $buttons = [];
        foreach (self::BUTTONS as $n => [$url, $key]) {
            if ($settings->global('home_button_' . $n . '_show', '1') !== '1') { continue; }
            $target = str_replace('{locale}', $locale, $settings->global('home_button_' . $n . '_url', $url));
            $label = $settings->get('home_button_' . $n . '_label', $locale, $translate($key));
            if ($label !== '' && SiteLinks::validUrl($target)) { $buttons[] = ['label' => $label, 'url' => $target, 'external' => (bool) preg_match('~^https?://~i', $target)]; }
        }
        return $buttons;
    }

    public function save(array $input): void
    {
        $locales = array_column($this->db->all('SELECT code FROM locales'), 'code');
        $order = is_array($input['sections'] ?? null) ? $input['sections'] : [];
        $parts = [];
        foreach ($order as $key => $data) {
            if (!isset(self::SECTIONS[$key]) || !is_array($data)) { throw new \InvalidArgumentException('Unknown home page section.'); }
            $parts[(int) ($data['order'] ?? 0) * 10 + count($parts)] = $key . ':' . ((string) ($data['show'] ?? '0') === '1' ? '1' : '0');
        }
        ksort($parts);
        $count = (int) ($input['home_latest_count'] ?? 5);
        if (!in_array($count, self::COUNTS, true)) { throw new \InvalidArgumentException('Latest posts: choose a number from the list.'); }
        $text = is_array($input['text'] ?? null) ? $input['text'] : [];
        $labels = is_array($input['buttons'] ?? null) ? $input['buttons'] : [];
        $globals = ['home_latest_count' => (string) $count, 'home_decoration' => (string) ($input['home_decoration'] ?? '0') === '1' ? '1' : '0'];
        if ($parts) { $globals['home_sections'] = implode(',', $parts); }
        $localized = [];
        foreach ($text as $key => $values) {
            if (!isset(self::TEXT[$key]) || !is_array($values)) { throw new \InvalidArgumentException('Unknown home page text.'); }
            foreach ($values as $code => $value) { $localized[$key][$this->locale((string) $code, $locales)] = $this->text($value, self::TEXT[$key][2], self::TEXT[$key][3], self::TEXT[$key][0] . ' (' . $code . ')'); }
        }
        foreach ($labels as $n => $button) {
            if (!isset(self::BUTTONS[$n]) || !is_array($button)) { throw new \InvalidArgumentException('Unknown home page button.'); }
            $url = trim((string) ($button['url'] ?? ''));
            if ($url !== '' && !SiteLinks::validUrl($url)) { throw new \InvalidArgumentException('Button ' . $n . ': use a URL starting with /, #, https://, http:// or mailto:'); }
            $globals['home_button_' . $n . '_url'] = $url === '' ? self::BUTTONS[$n][0] : $url;
            $globals['home_button_' . $n . '_show'] = (string) ($button['show'] ?? '0') === '1' ? '1' : '0';
            foreach (is_array($button['labels'] ?? null) ? $button['labels'] : [] as $code => $value) {
                $localized['home_button_' . $n . '_label'][$this->locale((string) $code, $locales)] = $this->text($value, 80, false, 'Button ' . $n . ' (' . $code . ')');
            }
        }
        $this->db->transaction(function () use ($globals, $localized) {
            foreach ($globals as $key => $value) { $this->db->upsert('settings', ['key' => $key, 'locale' => null, 'value' => $value], ['key', 'locale_scope']); }
            foreach ($localized as $key => $values) {
                foreach ($values as $code => $value) {
                    $this->db->query('DELETE FROM settings WHERE `key` = ? AND locale_scope = ?', [$key, $code]);
                    if ($value !== '') { $this->db->upsert('settings', ['key' => $key, 'locale' => $code, 'value' => $value], ['key', 'locale_scope']); }
                }
            }
        });
    }

    private function locale(string $code, array $locales): string
    {
        if (!in_array($code, $locales, true)) { throw new \InvalidArgumentException('Unknown language.'); }
        return $code;
    }

    private function text(mixed $value, int $max, bool $multiline, string $where): string
    {
        $value = trim(is_string($value) ? $value : '');
        if (!mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > $max || (!$multiline && preg_match('/\p{Cc}/u', $value))) {
            throw new \InvalidArgumentException($where . ': use up to ' . $max . ' characters' . ($multiline ? '.' : ' on one line.'));
        }
        return $value;
    }
}
