<?php
declare(strict_types=1);
// "Keep me signed in": one row per remembered browser. The cookie holds the selector and a
// secret whose SHA-256 is stored here; a password change (session_version) cancels every row.
return static function (\Core\Database $db): void {
    $mysql = $db->driver() === 'mysql';
    $bin = $mysql ? ' COLLATE utf8mb4_bin' : '';
    $db->query('CREATE TABLE IF NOT EXISTS remember_tokens (
        id INTEGER PRIMARY KEY ' . ($mysql ? 'AUTO_INCREMENT' : 'AUTOINCREMENT') . ",
        user_id INTEGER NOT NULL,
        selector CHAR(24)$bin NOT NULL UNIQUE,
        validator_hash CHAR(64)$bin NOT NULL,
        session_version INTEGER NOT NULL,
        user_agent VARCHAR(255) NOT NULL DEFAULT '',
        expires_at DATETIME NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_used_at DATETIME,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )" . ($mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : ''));
};
