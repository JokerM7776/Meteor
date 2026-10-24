<?php
declare(strict_types=1);
// Announcements: short messages shown in a bar above the header. locale_codes is a
// space-separated list of language codes; empty means every language.
return static function (\Core\Database $db): void {
    $mysql = $db->driver() === 'mysql';
    $db->query('CREATE TABLE IF NOT EXISTS announcements (
        id INTEGER PRIMARY KEY ' . ($mysql ? 'AUTO_INCREMENT' : 'AUTOINCREMENT') . ",
        title VARCHAR(191) NOT NULL DEFAULT '',
        message TEXT NOT NULL,
        button_text VARCHAR(80) NOT NULL DEFAULT '',
        button_url VARCHAR(2048) NOT NULL DEFAULT '',
        locale_codes VARCHAR(1000) NOT NULL DEFAULT '',
        is_visible INTEGER NOT NULL DEFAULT 1 CHECK (is_visible IN (0, 1)),
        sort_order INTEGER NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    )" . ($mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : ''));
};
