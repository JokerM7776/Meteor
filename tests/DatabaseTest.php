<?php
test('Database: schema, CRUD, prepared values and now expression', function () {
    $db = database(); locale($db);
    same(1, $db->one('PRAGMA foreign_keys')['foreign_keys']);
    $id = $db->insert('INSERT INTO users (username, password_hash) VALUES (?, ?)', ["name' OR 1=1 --", 'hash']);
    same(1, $id);
    same("name' OR 1=1 --", $db->one('SELECT username FROM users WHERE id = ?', [$id])['username']);
    $db->query('UPDATE users SET username = ? WHERE id = ?', ['renamed', $id]);
    same('renamed', $db->all('SELECT * FROM users')[0]['username']);
    ok((bool) $db->one('SELECT ' . $db->now() . ' AS current_time')['current_time']);
    $db->query('DELETE FROM users WHERE id = ?', [$id]); same(null, $db->one('SELECT * FROM users'));
});
test('Database: upsert and transaction commit/rollback', function () {
    $db = database(); locale($db);
    $db->upsert('ui_strings', ['locale' => 'en', 'key' => 'home', 'value' => 'A'], ['locale', 'key']);
    $db->upsert('ui_strings', ['locale' => 'en', 'key' => 'home', 'value' => 'B'], ['locale', 'key']);
    same('B', $db->one('SELECT value FROM ui_strings')['value']);
    $db->upsert('settings', ['key' => 'site_title', 'locale' => null, 'value' => 'Before'], ['key', 'locale_scope']);
    $db->upsert('settings', ['key' => 'site_title', 'locale' => null, 'value' => 'After'], ['key', 'locale_scope']);
    same('After', $db->one('SELECT value FROM settings')['value']);
    same(42, $db->transaction(function ($db) { $db->query('INSERT INTO tags (slug) VALUES (?)', ['first']); return 42; }));
    throws(fn () => $db->transaction(function ($db) { $db->query('INSERT INTO tags (slug) VALUES (?)', ['second']); throw new RuntimeException('rollback'); }));
    same(1, count($db->all('SELECT * FROM tags')));
    throws(fn () => $db->upsert('tags; DROP TABLE users', ['slug' => 'bad'], ['slug']), InvalidArgumentException::class);
});
test('Database: unique keys, checks, optional global settings and cascades', function () {
    $db = database(); locale($db);
    $category = $db->insert('INSERT INTO categories (slug) VALUES (?)', ['category']);
    $post = $db->insert('INSERT INTO posts (slug, base_locale, category_id) VALUES (?, ?, ?)', ['post', 'en', $category]);
    $translation = ['post_id' => $post, 'locale' => 'en', 'title' => 'Title', 'summary' => '', 'content_md' => '', 'content_html' => '', 'toc_html' => ''];
    $db->upsert('post_translations', $translation, ['post_id', 'locale']);
    throws(fn () => $db->query('INSERT INTO post_translations (post_id, locale, title, summary, content_md, content_html, toc_html) VALUES (?, ?, ?, ?, ?, ?, ?)', array_values($translation)), PDOException::class);
    throws(fn () => $db->query('UPDATE post_translations SET status = ?', ['invalid']), PDOException::class);
    throws(fn () => $db->query('DELETE FROM locales WHERE code = ?', ['en']), PDOException::class);
    $db->query('INSERT INTO settings (`key`, value) VALUES (?, ?)', ['title', 'Global']);
    throws(fn () => $db->query('INSERT INTO settings (`key`, value) VALUES (?, ?)', ['title', 'Duplicate']), PDOException::class);
    $db->query('INSERT INTO settings (`key`, locale, value) VALUES (?, ?, ?)', ['title', 'en', 'Localized']);
    $db->query('DELETE FROM categories WHERE id = ?', [$category]);
    same(null, $db->one('SELECT category_id FROM posts')['category_id']);
    $tag = $db->insert('INSERT INTO tags (slug) VALUES (?)', ['tag']);
    $db->query('INSERT INTO post_tags (post_id, tag_id) VALUES (?, ?)', [$post, $tag]);
    $db->query('DELETE FROM posts WHERE id = ?', [$post]);
    same([], $db->all('SELECT * FROM post_translations')); same([], $db->all('SELECT * FROM post_tags'));
    $user = $db->insert('INSERT INTO users (username, password_hash) VALUES (?, ?)', ['admin', 'hash']);
    $db->query('INSERT INTO passkeys (user_id, credential_id, credential_hash, public_key, algorithm, name) VALUES (?, ?, ?, ?, ?, ?)', [$user, 'credential', hash('sha256', 'credential'), 'pem', -7, 'device']);
    $db->query('DELETE FROM users WHERE id = ?', [$user]); same([], $db->all('SELECT * FROM passkeys'));
    $db->query('DELETE FROM locales WHERE code = ?', ['en']);
    same(1, count($db->all('SELECT * FROM settings')));
});
test('Database: MySQL statement and column parity with loaded SQLite schema', function () {
    $db = database(); $mysql = [];
    foreach (schemaStatements('mysql') as $statement) {
        if (preg_match('/^CREATE TABLE (\w+) \((.*)\) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin$/sD', $statement, $m)) {
            preg_match_all('/^    `?(\w+)`?\s+(?:INTEGER|BIGINT|VARCHAR|CHAR|DATETIME|TEXT|LONGTEXT)\b/m', $m[2], $columns);
            $mysql[$m[1]] = $columns[1];
            ok(!preg_match('/\bENUM\b/i', $statement));
        } else { ok((bool) preg_match('/^CREATE INDEX \w+ ON \w+ \([\w, ]+\)$/D', $statement), 'Unrecognized MySQL statement'); }
    }
    $tables = $db->all("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
    same(15, count($tables)); same(count($tables), count($mysql));
    foreach ($tables as $table) {
        $columns = array_column($db->all('PRAGMA table_xinfo(' . $table['name'] . ')'), 'name');
        same($columns, $mysql[$table['name']] ?? []);
    }
});
