<?php
declare(strict_types=1);
// Media library: uploaded images with random file names, a category and tags.
return static function (\Core\Database $db): void {
    if ($db->driver() === 'mysql') {
        $db->query("CREATE TABLE IF NOT EXISTS media (
            id INTEGER PRIMARY KEY AUTO_INCREMENT,
            file_name VARCHAR(80) COLLATE utf8mb4_bin NOT NULL UNIQUE,
            thumb_name VARCHAR(80) COLLATE utf8mb4_bin,
            original_name VARCHAR(255) NOT NULL,
            mime_type VARCHAR(50) NOT NULL,
            width INTEGER NOT NULL DEFAULT 0,
            height INTEGER NOT NULL DEFAULT 0,
            size_bytes INTEGER NOT NULL DEFAULT 0,
            alt_text VARCHAR(255) NOT NULL DEFAULT '',
            category VARCHAR(191) NOT NULL DEFAULT '',
            search_text TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX media_created (created_at, id),
            INDEX media_category (category)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->query('CREATE TABLE IF NOT EXISTS media_tags (
            media_id INTEGER NOT NULL,
            tag VARCHAR(191) COLLATE utf8mb4_bin NOT NULL,
            PRIMARY KEY (media_id, tag),
            INDEX media_tags_tag (tag),
            FOREIGN KEY (media_id) REFERENCES media(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        return;
    }
    $db->query("CREATE TABLE IF NOT EXISTS media (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        file_name VARCHAR(80) NOT NULL UNIQUE,
        thumb_name VARCHAR(80),
        original_name VARCHAR(255) NOT NULL,
        mime_type VARCHAR(50) NOT NULL,
        width INTEGER NOT NULL DEFAULT 0,
        height INTEGER NOT NULL DEFAULT 0,
        size_bytes INTEGER NOT NULL DEFAULT 0,
        alt_text VARCHAR(255) NOT NULL DEFAULT '',
        category VARCHAR(191) NOT NULL DEFAULT '',
        search_text TEXT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    $db->query('CREATE TABLE IF NOT EXISTS media_tags (
        media_id INTEGER NOT NULL,
        tag VARCHAR(191) NOT NULL,
        PRIMARY KEY (media_id, tag),
        FOREIGN KEY (media_id) REFERENCES media(id) ON DELETE CASCADE
    )');
    $db->query('CREATE INDEX IF NOT EXISTS media_created ON media (created_at, id)');
    $db->query('CREATE INDEX IF NOT EXISTS media_category ON media (category)');
    $db->query('CREATE INDEX IF NOT EXISTS media_tags_tag ON media_tags (tag)');
};
