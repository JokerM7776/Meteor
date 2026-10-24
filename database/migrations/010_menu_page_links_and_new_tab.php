<?php
declare(strict_types=1);
// The automatic "Pages" menu item ("Show in navigation" + "Navigation label") becomes ordinary page
// links in its place, so pages can be ordered with the other items. Navigation labels become link
// labels. Menu items can open in a new tab. Safe to re-run.
return static function (\Core\Database $db): void {
    if (!$db->hasColumn('menu_items', 'new_tab')) { $db->query('ALTER TABLE menu_items ADD COLUMN new_tab INTEGER NOT NULL DEFAULT 0 CHECK (new_tab IN (0, 1))'); }
    if (!$db->hasColumn('pages', 'show_in_nav')) { return; }
    $navLabels = $db->hasColumn('page_translations', 'nav_label');
    foreach ($db->all("SELECT * FROM menu_items WHERE kind = 'pages' ORDER BY id") as $auto) {
        $items = $db->all('SELECT id, page_id FROM menu_items WHERE menu = ? ORDER BY sort_order, id', [$auto['menu']]);
        $linked = array_map('intval', array_filter(array_column($items, 'page_id')));
        $order = [];
        foreach ($items as $item) {
            if ((int) $item['id'] !== (int) $auto['id']) { $order[] = (int) $item['id']; continue; }
            foreach ($db->all('SELECT id FROM pages WHERE show_in_nav = 1 ORDER BY sort_order, id') as $page) {
                if (in_array((int) $page['id'], $linked, true)) { continue; }
                $order[] = $db->insert('INSERT INTO menu_items (menu, kind, page_id, sort_order, is_visible) VALUES (?, ?, ?, ?, ?)', [$auto['menu'], 'link', $page['id'], 0, $auto['is_visible']]);
            }
        }
        $db->query('DELETE FROM menu_items WHERE id = ?', [$auto['id']]);
        foreach ($order as $position => $id) { $db->query('UPDATE menu_items SET sort_order = ? WHERE id = ?', [$position, $id]); }
    }
    if (!$navLabels) { return; }
    // Page links used the navigation label when their own label was empty; keep what visitors saw.
    $rows = $db->all("SELECT i.id AS item_id, t.locale, t.nav_label FROM menu_items i JOIN page_translations t ON t.page_id = i.page_id
        WHERE i.page_id IS NOT NULL AND t.nav_label IS NOT NULL AND t.nav_label <> ''");
    foreach ($rows as $row) {
        if (!$db->one('SELECT item_id FROM menu_item_labels WHERE item_id = ? AND locale = ?', [$row['item_id'], $row['locale']])) {
            $db->query('INSERT INTO menu_item_labels (item_id, locale, label) VALUES (?, ?, ?)', [$row['item_id'], $row['locale'], mb_substr($row['nav_label'], 0, 80)]);
        }
    }
};
