<?php
declare(strict_types=1);
// Menu items can point at a page (menu_items.page_id). The old built-in "About" item becomes a link
// to a real, editable About page (created as a draft when missing). Interface text that used to hold
// the home heading, home intro and footer text moves to the site settings, which now own them.
return static function (\Core\Database $db): void {
    $columns = $db->driver() === 'mysql'
        ? array_column($db->all('SHOW COLUMNS FROM menu_items'), 'Field')
        : array_column($db->all('PRAGMA table_info(menu_items)'), 'name');
    if (!in_array('page_id', $columns, true)) { $db->query('ALTER TABLE menu_items ADD COLUMN page_id INTEGER NULL'); }
    if ($db->one("SELECT id FROM menu_items WHERE kind = 'about'")) {
        $page = \App\Services\AboutPage::ensure($db);
        if ($page === null) { $db->query("DELETE FROM menu_items WHERE kind = 'about'"); }
        else { $db->query("UPDATE menu_items SET kind = 'link', url = NULL, page_id = ? WHERE kind = 'about'", [$page]); }
    }
    foreach (['home_heading' => 'home_heading', 'home_intro' => 'home_intro', 'footer_description' => 'footer_text'] as $string => $setting) {
        foreach ($db->all('SELECT locale, value FROM ui_strings WHERE `key` = ?', [$string]) as $row) {
            if (!$db->one('SELECT id FROM settings WHERE `key` = ? AND locale_scope = ?', [$setting, $row['locale']])) {
                $db->upsert('settings', ['key' => $setting, 'locale' => $row['locale'], 'value' => $row['value']], ['key', 'locale_scope']);
            }
        }
        $db->query('DELETE FROM ui_strings WHERE `key` = ?', [$string]);
    }
};
