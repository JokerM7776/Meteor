<?php
declare(strict_types=1);
// The bundled Chinese file is now lang/zh-hans.json, leaving room for zh-hant. A `zh` language that
// is unused (Off, no content) becomes zh-hans. A `zh` in use keeps its code and URLs and reads the
// zh-hans file (LanguagePack::RENAMED).
return static function (\Core\Database $db): void {
    $known = array_filter(explode(',', $db->one("SELECT value FROM settings WHERE `key` = 'language_packs_known' AND locale_scope = ''")['value'] ?? ''));
    if (in_array('zh', $known, true)) { \App\Services\LanguagePacks::remember($db, ['zh-hans']); }
    $zh = $db->one("SELECT * FROM locales WHERE code = 'zh'");
    if (!$zh || $db->one("SELECT code FROM locales WHERE code = 'zh-hans'")) { return; }
    $used = $zh['status'] !== 'off' || (int) $zh['is_default'] === 1;
    foreach (['post_translations', 'page_translations', 'category_translations', 'tag_translations', 'menu_item_labels'] as $table) {
        $used = $used || (bool) $db->one("SELECT locale FROM $table WHERE locale = 'zh'");
    }
    foreach (['posts', 'pages'] as $table) { $used = $used || (bool) $db->one("SELECT id FROM $table WHERE base_locale = 'zh'"); }
    if ($used) { return; }
    $db->query('INSERT INTO locales (code, name, native_name, status, is_default, sort_order, direction) VALUES (?, ?, ?, ?, ?, ?, ?)',
        ['zh-hans', $zh['name'], $zh['native_name'], 'off', 0, (int) $zh['sort_order'], $zh['direction']]);
    $db->query("UPDATE ui_strings SET locale = 'zh-hans' WHERE locale = 'zh'");
    $db->query("UPDATE settings SET locale = 'zh-hans' WHERE locale = 'zh'");
    $db->query("DELETE FROM locales WHERE code = 'zh'");
};
