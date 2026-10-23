<?php
declare(strict_types=1);
// Header and footer menus. Converts the earlier settings (nav_show_*, nav_* labels,
// header_links / footer_links as "Label | URL" lines) into menu rows, then removes them.
return static function (\Core\Database $db): void {
    if ($db->driver() === 'mysql') {
        $db->query("CREATE TABLE IF NOT EXISTS menu_items (
            id INTEGER PRIMARY KEY AUTO_INCREMENT,
            menu VARCHAR(10) NOT NULL CHECK (menu IN ('header', 'footer')),
            kind VARCHAR(10) NOT NULL CHECK (kind IN ('posts', 'tags', 'about', 'pages', 'rss', 'sitemap', 'link')),
            url VARCHAR(2048),
            sort_order INTEGER NOT NULL DEFAULT 0,
            is_visible INTEGER NOT NULL DEFAULT 1 CHECK (is_visible IN (0, 1)),
            INDEX menu_items_menu_order (menu, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->query('CREATE TABLE IF NOT EXISTS menu_item_labels (
            item_id INTEGER NOT NULL,
            locale VARCHAR(35) COLLATE utf8mb4_bin NOT NULL,
            label VARCHAR(191) NOT NULL,
            PRIMARY KEY (item_id, locale),
            FOREIGN KEY (item_id) REFERENCES menu_items(id) ON DELETE CASCADE,
            FOREIGN KEY (locale) REFERENCES locales(code) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    } else {
        $db->query("CREATE TABLE IF NOT EXISTS menu_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            menu VARCHAR(10) NOT NULL CHECK (menu IN ('header', 'footer')),
            kind VARCHAR(10) NOT NULL CHECK (kind IN ('posts', 'tags', 'about', 'pages', 'rss', 'sitemap', 'link')),
            url VARCHAR(2048),
            sort_order INTEGER NOT NULL DEFAULT 0,
            is_visible INTEGER NOT NULL DEFAULT 1 CHECK (is_visible IN (0, 1))
        )");
        $db->query('CREATE TABLE IF NOT EXISTS menu_item_labels (
            item_id INTEGER NOT NULL,
            locale VARCHAR(35) NOT NULL,
            label VARCHAR(191) NOT NULL,
            PRIMARY KEY (item_id, locale),
            FOREIGN KEY (item_id) REFERENCES menu_items(id) ON DELETE CASCADE,
            FOREIGN KEY (locale) REFERENCES locales(code) ON DELETE CASCADE
        )');
        $db->query('CREATE INDEX IF NOT EXISTS menu_items_menu_order ON menu_items (menu, sort_order)');
    }
    // Rows already exist: a previous run got this far, so do not convert twice.
    if ($db->one('SELECT id FROM menu_items')) { return; }

    $settings = $db->all('SELECT `key`, locale_scope, value FROM settings');
    $value = static function (string $key, string $scope) use ($settings): ?string {
        foreach ($settings as $row) { if ($row['key'] === $key && $row['locale_scope'] === $scope) { return $row['value']; } }
        return null;
    };
    $default = $db->one('SELECT code FROM locales WHERE is_default = 1')['code'] ?? 'en';
    $locales = array_column($db->all('SELECT code FROM locales'), 'code');
    $defaults = [
        'header' => ['posts' => 1, 'tags' => 1, 'about' => 1, 'pages' => 1, 'rss' => 1, 'sitemap' => 0],
        'footer' => ['about' => 1, 'sitemap' => 1, 'posts' => 0, 'tags' => 0, 'rss' => 0, 'pages' => 0],
    ];
    $label = static function (int $item, string $locale, string $text) use ($db): void {
        $text = mb_substr(trim($text), 0, 80);
        if ($text !== '') { $db->upsert('menu_item_labels', ['item_id' => $item, 'locale' => $locale, 'label' => $text], ['item_id', 'locale']); }
    };
    foreach ($defaults as $menu => $kinds) {
        $order = 0; $ids = [];
        foreach ($kinds as $kind => $visible) {
            if ($menu === 'header' && in_array($kind, ['posts', 'tags', 'about', 'rss'], true) && $value('nav_show_' . $kind, '') === '0') { $visible = 0; }
            $ids[$kind] = $db->insert('INSERT INTO menu_items (menu, kind, sort_order, is_visible) VALUES (?, ?, ?, ?)', [$menu, $kind, $order++, $visible]);
        }
        foreach (['posts', 'tags', 'about', 'rss'] as $kind) {
            if ($menu === 'footer' && $kind !== 'about') { continue; }
            foreach ($locales as $locale) {
                $text = $value('nav_' . $kind, $locale) ?? ($locale === $default ? $value('nav_' . $kind, '') : null);
                if ($text !== null) { $label($ids[$kind], $locale, $text); }
            }
        }
        // Custom links, one row per distinct URL, one label per language.
        $byUrl = [];
        foreach ($locales as $locale) {
            foreach (preg_split('/\r\n|\n|\r/', $value($menu . '_links', $locale) ?? '') as $line) {
                [$text, $url] = array_map('trim', array_pad(explode('|', $line, 2), 2, ''));
                if ($text === '' || !\App\Services\SiteLinks::validUrl($url) || count($byUrl) >= 24) { continue; }
                $byUrl[$url] ??= $db->insert('INSERT INTO menu_items (menu, kind, url, sort_order, is_visible) VALUES (?, ?, ?, ?, 1)', [$menu, 'link', $url, $order++]);
                $label($byUrl[$url], $locale, $text);
            }
        }
    }
    $db->query("DELETE FROM settings WHERE `key` IN ('nav_show_posts', 'nav_show_tags', 'nav_show_about', 'nav_show_rss',
        'nav_posts', 'nav_tags', 'nav_about', 'nav_rss', 'header_links', 'footer_links')");
};
