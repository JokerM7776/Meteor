<?php
declare(strict_types=1);
// Extended Markdown per post/page, comments (form comments and received webmentions) with one level
// of replies, and the webmention queue (incoming to verify, outgoing to send). Safe to re-run.
return static function (\Core\Database $db): void {
    $mysql = $db->driver() === 'mysql';
    foreach (['posts' => ['markdown_extended' => 0, 'comments_enabled' => 1], 'pages' => ['markdown_extended' => 0]] as $table => $columns) {
        foreach ($columns as $column => $default) {
            if (!$db->hasColumn($table, $column)) { $db->query("ALTER TABLE $table ADD COLUMN $column INTEGER NOT NULL DEFAULT $default CHECK ($column IN (0, 1))"); }
        }
    }
    $id = 'id INTEGER PRIMARY KEY ' . ($mysql ? 'AUTO_INCREMENT' : 'AUTOINCREMENT');
    $engine = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
    $db->query("CREATE TABLE IF NOT EXISTS comments (
        $id,
        post_id INTEGER NOT NULL,
        locale VARCHAR(35)" . ($mysql ? ' COLLATE utf8mb4_bin' : '') . " NOT NULL,
        parent_id INTEGER,
        kind VARCHAR(10) NOT NULL DEFAULT 'comment' CHECK (kind IN ('comment', 'webmention')),
        mention_type VARCHAR(10) NOT NULL DEFAULT '',
        author_name VARCHAR(100) NOT NULL,
        author_email VARCHAR(191) NOT NULL DEFAULT '',
        author_url VARCHAR(2048) NOT NULL DEFAULT '',
        body TEXT NOT NULL,
        source_url VARCHAR(2048) NOT NULL DEFAULT '',
        status VARCHAR(10) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'spam')),
        is_owner INTEGER NOT NULL DEFAULT 0 CHECK (is_owner IN (0, 1)),
        ip_hash CHAR(64) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
        FOREIGN KEY (locale) REFERENCES locales(code) ON DELETE CASCADE,
        FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE
    )$engine");
    $db->query("CREATE TABLE IF NOT EXISTS webmentions (
        $id,
        direction VARCHAR(3) NOT NULL CHECK (direction IN ('in', 'out')),
        source VARCHAR(2048) NOT NULL,
        target VARCHAR(2048) NOT NULL,
        pair_hash CHAR(64) NOT NULL UNIQUE,
        status VARCHAR(10) NOT NULL DEFAULT 'queued' CHECK (status IN ('queued', 'done', 'failed')),
        attempts INTEGER NOT NULL DEFAULT 0,
        result VARCHAR(255) NOT NULL DEFAULT '',
        ip_hash CHAR(64) NOT NULL DEFAULT '',
        next_try_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    )$engine");
    $indexes = ['comments_post' => 'comments (post_id, locale, status)', 'comments_status' => 'comments (status, created_at)',
        'comments_ip' => 'comments (ip_hash, created_at)', 'webmentions_queue' => 'webmentions (status, next_try_at)', 'webmentions_ip' => 'webmentions (ip_hash, created_at)'];
    foreach ($indexes as $name => $definition) {
        if ($mysql) {
            if (!$db->one('SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND index_name = ?', [$name])) { $db->query("CREATE INDEX $name ON $definition"); }
        } else { $db->query("CREATE INDEX IF NOT EXISTS $name ON $definition"); }
    }
};
