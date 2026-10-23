<?php
declare(strict_types=1);
namespace App\Services;
use App\Repositories\Menus;
use Core\Database;

/** Saves one menu table plus that section's toggles. Rows and languages absent from the input stay as they are. */
final class MenuEditor
{
    public const TOGGLES = [
        'header' => ['header_show_icon' => 'Show icon', 'header_show_title' => 'Show site title', 'header_show_switcher' => 'Show language switcher'],
        'footer' => ['footer_show_languages' => 'Show “Read in” column', 'footer_show_feeds' => 'Show “Feeds” column', 'footer_show_site' => 'Show the menu column', 'footer_show_copyright' => 'Show the copyright line'],
    ];
    public const BRANDS = ['site' => 'Site: icon, title, tagline and footer text', 'author' => 'Author card: photo, name, bio and profile links', 'none' => 'Nothing'];
    public const NAMES = ['posts' => 'Posts', 'tags' => 'Tags', 'pages' => 'Pages', 'rss' => 'RSS feed', 'sitemap' => 'Sitemap'];

    public function __construct(private Database $db) {}

    public function save(string $menu, array $input): void
    {
        if (!in_array($menu, Menus::MENUS, true)) { throw new \InvalidArgumentException('Unknown menu.'); }
        $menus = new Menus($this->db);
        $menus->ensureDefaults();
        $name = ucfirst($menu) . ' menu';
        $locales = array_column($this->db->all('SELECT code FROM locales'), 'code');
        $rows = $menus->rows($menu);
        $position = array_flip(array_column($rows, 'id'));
        $byId = array_column($rows, null, 'id');
        $items = is_array($input['items'] ?? null) ? $input['items'] : [];

        $toggles = [];
        foreach (self::TOGGLES[$menu] as $key => $label) {
            if (array_key_exists($key, $input)) { $toggles[$key] = (string) $input[$key] === '1' ? '1' : '0'; }
        }
        if ($menu === 'header') {
            $current = array_column($this->db->all('SELECT `key`, value FROM settings WHERE locale_scope = ?', ['']), 'value', 'key');
            $effective = $toggles + $current;
            if (($effective['header_show_icon'] ?? '1') === '0' && ($effective['header_show_title'] ?? '1') === '0') {
                throw new \InvalidArgumentException('Show the icon, the site title, or both in the header.');
            }
        }

        $this->db->transaction(function () use ($menu, $input, $items, $locales, $byId, $position, $name, $toggles, $rows) {
            foreach ($toggles as $key => $value) {
                $this->db->upsert('settings', ['key' => $key, 'locale' => null, 'value' => $value], ['key', 'locale_scope']);
            }
            if ($menu === 'footer' && array_key_exists('footer_brand', $input)) {
                $brand = (string) $input['footer_brand'];
                if (!isset(self::BRANDS[$brand])) { throw new \InvalidArgumentException('Footer: choose what the footer shows on the left.'); }
                $this->db->upsert('settings', ['key' => 'footer_brand', 'locale' => null, 'value' => $brand], ['key', 'locale_scope']);
            }
            if ($menu === 'footer' && is_array($input['footer_menu_heading'] ?? null)) {
                foreach ($input['footer_menu_heading'] as $locale => $heading) {
                    $this->setting('footer_menu_heading', $this->locale((string) $locale, $locales), $this->text($heading, 80, 'Menu column heading'));
                }
            }
            $count = count($rows);
            foreach ($items as $id => $data) {
                if ($id === 'new' || !is_array($data)) { continue; }
                $row = $byId[(int) $id] ?? null;
                if (!$row || (string) (int) $id !== (string) $id) { throw new \InvalidArgumentException($name . ': unknown item. Reload the page and try again.'); }
                $where = $name . ', row ' . ($position[$row['id']] + 1);
                $link = $row['kind'] === 'link';
                $pageLink = $link && !empty($row['page_id']);
                if (!empty($data['remove'])) {
                    if (!$link) { throw new \InvalidArgumentException($where . ': built-in items can be hidden but not removed.'); }
                    $this->db->query('DELETE FROM menu_items WHERE id = ?', [$row['id']]); $count--;
                    continue;
                }
                $changes = [];
                if (array_key_exists('sort_order', $data)) { $changes['sort_order'] = $this->order($data['sort_order'], $where); }
                if (array_key_exists('visible', $data)) { $changes['is_visible'] = (string) $data['visible'] === '1' ? 1 : 0; }
                if ($link && !$pageLink && array_key_exists('url', $data)) { $changes['url'] = $this->url($data['url'], $where); }
                foreach ($changes as $column => $value) {
                    $this->db->query("UPDATE menu_items SET $column = ? WHERE id = ?", [$value, $row['id']]);
                }
                if ($row['kind'] !== 'pages') { $this->labels((int) $row['id'], $data['labels'] ?? [], $locales, $where); }
                if ($link && !$pageLink && !$this->db->one('SELECT item_id FROM menu_item_labels WHERE item_id = ?', [$row['id']])) {
                    throw new \InvalidArgumentException($where . ': enter a label in at least one language.');
                }
            }
            $new = is_array($items['new'] ?? null) ? $items['new'] : [];
            $labels = is_array($new['labels'] ?? null) ? $new['labels'] : [];
            $pageId = (int) ($new['page_id'] ?? 0);
            if ($pageId > 0 || trim((string) ($new['url'] ?? '')) !== '' || implode('', array_map(fn ($v) => trim((string) $v), $labels)) !== '') {
                $where = $name . ', new link';
                if ($count >= Menus::LIMIT) { throw new \InvalidArgumentException($name . ': use at most ' . Menus::LIMIT . ' items.'); }
                if ($pageId > 0 && !$this->db->one('SELECT id FROM pages WHERE id = ?', [$pageId])) { throw new \InvalidArgumentException($where . ': that page no longer exists.'); }
                $url = $pageId > 0 ? null : $this->url($new['url'] ?? '', $where);
                $max = (int) ($this->db->one('SELECT MAX(sort_order) AS n FROM menu_items WHERE menu = ?', [$menu])['n'] ?? 0);
                $order = array_key_exists('sort_order', $new) && trim((string) $new['sort_order']) !== '' ? $this->order($new['sort_order'], $where) : $max + 1;
                $id = $this->db->insert('INSERT INTO menu_items (menu, kind, url, page_id, sort_order, is_visible) VALUES (?, ?, ?, ?, ?, ?)',
                    [$menu, 'link', $url, $pageId > 0 ? $pageId : null, $order, (string) ($new['visible'] ?? '1') === '0' ? 0 : 1]);
                $this->labels($id, $labels, $locales, $where);
                if ($pageId === 0 && !$this->db->one('SELECT item_id FROM menu_item_labels WHERE item_id = ?', [$id])) {
                    throw new \InvalidArgumentException($where . ': enter a label in at least one language.');
                }
            }
            foreach ($this->db->all('SELECT id FROM menu_items WHERE menu = ? ORDER BY sort_order, id', [$menu]) as $index => $item) {
                $this->db->query('UPDATE menu_items SET sort_order = ? WHERE id = ?', [$index, $item['id']]);
            }
        });
    }

    private function labels(int $id, mixed $labels, array $locales, string $where): void
    {
        if (!is_array($labels)) { return; }
        foreach ($labels as $locale => $label) {
            $locale = $this->locale((string) $locale, $locales);
            $label = $this->text($label, 80, $where . ', label');
            $this->db->query('DELETE FROM menu_item_labels WHERE item_id = ? AND locale = ?', [$id, $locale]);
            if ($label !== '') { $this->db->query('INSERT INTO menu_item_labels (item_id, locale, label) VALUES (?, ?, ?)', [$id, $locale, $label]); }
        }
    }

    private function setting(string $key, string $locale, string $value): void
    {
        $this->db->query('DELETE FROM settings WHERE `key` = ? AND locale_scope = ?', [$key, $locale]);
        if ($value !== '') { $this->db->upsert('settings', ['key' => $key, 'locale' => $locale, 'value' => $value], ['key', 'locale_scope']); }
    }

    private function locale(string $locale, array $locales): string
    {
        if (!in_array($locale, $locales, true)) { throw new \InvalidArgumentException('Unknown language.'); }
        return $locale;
    }

    private function text(mixed $value, int $max, string $where): string
    {
        $value = trim(is_string($value) ? $value : '');
        if (!mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > $max || preg_match('/\p{Cc}/u', $value)) { throw new \InvalidArgumentException($where . ': use up to ' . $max . ' characters on one line.'); }
        return $value;
    }

    private function order(mixed $value, string $where): int
    {
        $value = trim(is_string($value) ? $value : '');
        if (!preg_match('/^-?\d{1,4}$/D', $value)) { throw new \InvalidArgumentException($where . ': order must be a whole number between -9999 and 9999.'); }
        return (int) $value;
    }

    private function url(mixed $value, string $where): string
    {
        $url = trim(is_string($value) ? $value : '');
        if (!SiteLinks::validUrl($url)) { throw new \InvalidArgumentException($where . ': use a URL starting with /, #, https://, http:// or mailto:'); }
        return $url;
    }
}
