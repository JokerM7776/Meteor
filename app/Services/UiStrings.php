<?php
declare(strict_types=1);
namespace App\Services;
use Core\{Database, LanguagePack};

/**
 * Interface text per language. A language's own text is its site edit (ui_strings) or else its
 * language file; English also falls back to lang/en.php. Site edits that equal the base text are
 * not stored, so improvements in shipped or downloaded language files keep flowing in.
 */
final class UiStrings
{
    public const GROUPS = [
        'Layout & navigation' => ['skip_content', 'site_name', 'menu', 'language', 'home', 'posts', 'tags', 'about', 'rss', 'sitemap', 'site', 'opens_new_tab', 'breadcrumb', 'pagination', 'color_scheme', 'scheme_auto', 'scheme_light', 'scheme_dark', 'announcements', 'announcement_position', 'back_to_top'],
        'Footer' => ['read_in', 'feeds', 'language_rss'],
        'Home' => ['pinned', 'latest', 'view_all_posts', 'read_archive', 'empty_posts', 'browse_categories', 'about_author', 'read', 'also_available', 'more_languages', 'list_two', 'list_many'],
        'Articles & pages' => ['published', 'reading_minutes', 'on_this_page', 'translation', 'available_translations', 'category', 'more_posts', 'previous', 'next', 'footnotes', 'footnote_back'],
        'Search' => ['search', 'search_label', 'search_button', 'search_results', 'search_empty', 'search_hint'],
        'Comments' => ['comments', 'mentions', 'comment_leave', 'comment_name', 'comment_email', 'comment_website', 'comment_text', 'comment_submit', 'comment_reply', 'comment_replying', 'comment_cancel_reply', 'comment_pending', 'comment_published', 'comment_owner', 'comment_via', 'comment_error_name', 'comment_error_text', 'comment_error_email', 'comment_error_url', 'comment_error_rate', 'comment_error_check', 'comment_error_expired', 'comment_check_title', 'comment_check_text', 'comment_check_noscript', 'comments_closed', 'mention_like', 'mention_repost', 'mention_bookmark', 'mention_mention', 'back_to_post'],
        'Archive' => ['archive', 'archive_description', 'all', 'view_all', 'show_less', 'newer', 'older'],
        'Page not found' => ['not_found', 'not_found_heading', 'not_found_description', 'back_home', 'browse_archive'],
    ];
    private array $builtins;

    public function __construct(private Database $db, private ?string $packDirectory = null, private ?string $downloadDirectory = null)
    {
        $this->builtins = require dirname(__DIR__, 2) . '/lang/en.php';
    }

    public function builtins(): array { return $this->builtins; }

    /** Text a language has before site edits: its language files, plus lang/en.php for English. */
    public function base(string $code): array
    {
        $pack = LanguagePack::strings($code, $this->builtins, $this->packDirectory, $this->downloadDirectory);
        return $code === 'en' ? $pack + $this->builtins : $pack;
    }

    /** Site edits per language. */
    public function overrides(): array
    {
        $rows = [];
        foreach ($this->db->all('SELECT locale, `key`, value FROM ui_strings') as $row) { $rows[$row['locale']][$row['key']] = $row['value']; }
        return $rows;
    }

    /** The language's own text by key (missing keys are absent). */
    public function own(string $code, ?array $overrides = null): array
    {
        $overrides ??= $this->overrides();
        return array_intersect_key(($overrides[$code] ?? []) + $this->base($code), $this->builtins);
    }

    /**
     * Saves posted cells ($input[code][key]). Cells that are not posted stay as they are.
     * An empty cell, or one equal to the base text, removes the site edit.
     */
    public function save(array $input): void
    {
        $locales = array_column($this->db->all('SELECT code, name FROM locales'), 'name', 'code');
        $overrides = $this->overrides();
        $changes = [];
        foreach ($input as $code => $values) {
            $code = (string) $code;
            if (!isset($locales[$code]) || !is_array($values)) { throw new \InvalidArgumentException('Unknown language.'); }
            $base = $this->base($code);
            $own = $this->own($code, $overrides);
            foreach ($values as $key => $value) {
                if (!isset($this->builtins[$key])) { throw new \InvalidArgumentException('Unknown interface text “' . $key . '”.'); }
                $value = trim(is_string($value) ? $value : '');
                // Unchanged cells are left alone, even if older data would not pass today's checks.
                if ($value !== '' && $value === ($own[$key] ?? null)) { continue; }
                if ($value !== '' && !LanguagePack::validText($value, $this->builtins[$key])) {
                    $tokens = LanguagePack::placeholders($this->builtins[$key]);
                    throw new \InvalidArgumentException($key . ' · ' . $locales[$code] . ': ' . ($tokens
                        ? 'keep the placeholder' . (count($tokens) > 1 ? 's ' : ' ') . implode(' ', $tokens) . ' and use up to ' . LanguagePack::MAX_TEXT . ' characters.'
                        : 'remove the {braces} and use up to ' . LanguagePack::MAX_TEXT . ' characters.'));
                }
                $changes[] = [$code, (string) $key, $value === '' || $value === ($base[$key] ?? null) ? null : $value];
            }
        }
        $this->db->transaction(function () use ($changes) {
            foreach ($changes as [$code, $key, $value]) {
                $this->db->query('DELETE FROM ui_strings WHERE locale = ? AND `key` = ?', [$code, $key]);
                if ($value !== null) { $this->db->query('INSERT INTO ui_strings (locale, `key`, value) VALUES (?, ?, ?)', [$code, $key, $value]); }
            }
        });
    }

    /** The language file for one language: its own text, in the order of lang/en.php. */
    public function export(string $code): string
    {
        $locale = $this->db->one('SELECT code, name, native_name, direction FROM locales WHERE code = ?', [$code]);
        if (!$locale) { throw new \InvalidArgumentException('Unknown language.'); }
        $own = $this->own($code);
        return LanguagePack::encode($locale, array_intersect_key(array_replace($this->builtins, $own), $own));
    }

    /**
     * Imports a language file. A new language is added as Off. Returns a message for the admin.
     * Names and direction of an existing language change only when $updateNames is true.
     */
    public function import(string $json, bool $updateNames = false): string
    {
        $pack = LanguagePack::parse($json, $this->builtins);
        $existing = $this->db->one('SELECT * FROM locales WHERE code = ?', [$pack['code']]);
        $this->db->transaction(function () use ($pack, $existing, $updateNames) {
            if (!$existing) {
                $max = (int) ($this->db->one('SELECT MAX(sort_order) AS n FROM locales')['n'] ?? 0);
                $this->db->query('INSERT INTO locales (code, name, native_name, direction, status, sort_order) VALUES (?, ?, ?, ?, ?, ?)',
                    [$pack['code'], $pack['name'], $pack['native_name'], $pack['direction'], 'off', $max + 1]);
            } elseif ($updateNames) {
                $this->db->query('UPDATE locales SET name = ?, native_name = ?, direction = ? WHERE code = ?', [$pack['name'], $pack['native_name'], $pack['direction'], $pack['code']]);
            }
        });
        $this->save([$pack['code'] => $pack['strings']]);
        $message = 'Imported ' . count($pack['strings']) . (count($pack['strings']) === 1 ? ' interface text into ' : ' interface texts into ') . ($existing['name'] ?? $pack['name']) . ($existing ? '.' : ' (added as Off).');
        if ($pack['skipped']) { $message .= ' Skipped ' . $pack['skipped'] . ' unknown or invalid ' . ($pack['skipped'] === 1 ? 'entry.' : 'entries.'); }
        return $message;
    }
}
