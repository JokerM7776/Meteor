<?php
declare(strict_types=1);
namespace App\Repositories;
use Core\Database;

/** Header and footer menus: built-in items (hide/reorder only), links to pages and custom links. */
final class Menus
{
    public const MENUS = ['header', 'footer'];
    public const LIMIT = 30;
    /** Built-in kinds per menu, in default order, with their default visibility. 'about' is a link to the About page. */
    public const DEFAULTS = [
        'header' => ['posts' => 1, 'tags' => 1, 'about' => 1, 'pages' => 1, 'rss' => 1, 'sitemap' => 0],
        'footer' => ['about' => 1, 'sitemap' => 1, 'posts' => 0, 'tags' => 0, 'rss' => 0, 'pages' => 0],
    ];
    /** Path pattern of each built-in kind; {locale} is the current language code. */
    public const PATHS = ['posts' => '/{locale}/posts', 'tags' => '/{locale}/tags', 'rss' => '/{locale}/rss.xml', 'sitemap' => '/sitemap.xml'];

    public function __construct(private Database $db) {}

    public function ensureDefaults(): void
    {
        foreach (self::DEFAULTS as $menu => $kinds) {
            if ($this->db->one('SELECT id FROM menu_items WHERE menu = ?', [$menu])) { continue; }
            $order = 0;
            $about = $this->db->one("SELECT id FROM pages WHERE slug = 'about'");
            foreach ($kinds as $kind => $visible) {
                if ($kind === 'about') {
                    if ($about) { $this->db->query('INSERT INTO menu_items (menu, kind, page_id, sort_order, is_visible) VALUES (?, ?, ?, ?, ?)', [$menu, 'link', $about['id'], $order++, $visible]); }
                    continue;
                }
                $this->db->query('INSERT INTO menu_items (menu, kind, sort_order, is_visible) VALUES (?, ?, ?, ?)', [$menu, $kind, $order++, $visible]);
            }
        }
    }

    /** Rows in display order with their labels; the in-memory defaults when the menu has no rows yet. */
    public function rows(string $menu): array
    {
        $rows = $this->db->all('SELECT * FROM menu_items WHERE menu = ? ORDER BY sort_order, id', [$menu]);
        if (!$rows) {
            $order = 0;
            foreach (self::DEFAULTS[$menu] ?? [] as $kind => $visible) {
                if ($kind === 'about') { continue; }
                $rows[] = ['id' => 0, 'menu' => $menu, 'kind' => $kind, 'url' => null, 'page_id' => null, 'sort_order' => $order++, 'is_visible' => $visible, 'labels' => []];
            }
            return $rows;
        }
        $labels = [];
        foreach ($this->db->all('SELECT l.item_id, l.locale, l.label FROM menu_item_labels l JOIN menu_items i ON i.id = l.item_id WHERE i.menu = ?', [$menu]) as $label) {
            $labels[$label['item_id']][$label['locale']] = $label['label'];
        }
        foreach ($rows as &$row) { $row['labels'] = $labels[$row['id']] ?? []; }
        return $rows;
    }

    /**
     * Resolved links for one menu in one language.
     * $context: locale, default, preview (bool), active, navPages, pages (all published pages in this
     * language), path (request path), translate (callable key => text).
     */
    public function links(string $menu, array $context): array
    {
        $rows = array_filter($this->rows($menu), fn ($row) => (int) $row['is_visible'] === 1);
        $locale = $context['locale'];
        $navPages = $context['navPages'] ?? [];
        $pagesShown = in_array('pages', array_column($rows, 'kind'), true);
        $published = array_column($context['pages'] ?? [], null, 'id');
        $inNav = array_column($navPages, 'id');
        $links = [];
        foreach ($rows as $row) {
            $kind = $row['kind'];
            if ($kind === 'pages') {
                foreach ($navPages as $page) {
                    $links[] = ['label' => $page['nav_label'] ?: $page['title'], 'url' => '/' . $locale . '/' . rawurlencode($page['slug']),
                        'external' => false, 'current' => ($context['active'] ?? '') === $page['slug']];
                }
                continue;
            }
            if ($kind === 'rss' && !empty($context['preview'])) { continue; }
            if ($kind === 'link' && !empty($row['page_id'])) {
                // Shown once the page is published in this language, and not twice when "Pages" lists it too.
                $page = $published[$row['page_id']] ?? null;
                if (!$page || ($pagesShown && in_array($page['id'], $inNav))) { continue; }
                $links[] = ['label' => ($row['labels'][$locale] ?? '') ?: ($page['nav_label'] ?: $page['title']), 'url' => '/' . $locale . '/' . rawurlencode($page['slug']),
                    'external' => false, 'current' => ($context['active'] ?? '') === $page['slug']];
                continue;
            }
            if ($kind === 'link') {
                $label = $row['labels'][$locale] ?? $row['labels'][$context['default']] ?? '';
                $url = str_replace('{locale}', $locale, (string) $row['url']);
                if ($label === '' || !\App\Services\SiteLinks::validUrl($url)) { continue; }
                $links[] = ['label' => $label, 'url' => $url, 'external' => (bool) preg_match('~^https?://~i', $url),
                    'current' => str_starts_with($url, '/') && $url === ($context['path'] ?? '')];
                continue;
            }
            if (!isset(self::PATHS[$kind])) { continue; }
            $links[] = ['label' => $row['labels'][$locale] ?? ($context['translate'])($kind),
                'url' => str_replace('{locale}', $locale, self::PATHS[$kind]), 'external' => false,
                'current' => ($context['active'] ?? '') === $kind];
        }
        return $links;
    }
}
