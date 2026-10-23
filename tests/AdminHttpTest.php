<?php
final class AdminHttpClient
{
    public Core\Database $db;
    public string $cookie = '';
    private string $directory;
    public string $root;
    public function __construct()
    {
        $this->root = appFixture();
        $this->directory = $this->root . '/storage/test-admin-' . bin2hex(random_bytes(6)); mkdir($this->directory, 0700);
        $this->db = new Core\Database(['driver' => 'sqlite', 'path' => $this->directory . '/db.sqlite']);
        foreach (schemaStatements('sqlite') as $statement) { $this->db->query($statement); }
        locale($this->db); locale($this->db, 'ja', 'preview', 0, 1);
        $this->db->query('INSERT INTO users (username, password_hash) VALUES (?, ?)', ['editor', password_hash('a long password', PASSWORD_DEFAULT)]);
    }
    public function request(string $uri, string $method = 'GET', array $data = [], bool $json = false, ?string $token = null, ?array $upload = null): array
    {
        $body = $json ? json_encode($data) : http_build_query($data);
        $contentType = $json ? 'application/json' : 'application/x-www-form-urlencoded';
        if ($upload !== null) {
            $boundary = 'Meteor' . bin2hex(random_bytes(12)); $body = '';
            foreach ($data as $key => $value) { $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"$key\"\r\n\r\n$value\r\n"; }
            $field = $upload['field'] ?? 'cover'; $filename = $upload['filename'] ?? 'cover.png';
            $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"$field\"; filename=\"$filename\"\r\nContent-Type: application/octet-stream\r\n\r\n" . $upload['bytes'] . "\r\n--$boundary--\r\n";
            $contentType = 'multipart/form-data; boundary=' . $boundary;
        }
        $environment = array_merge(getenv(), ['DB_DRIVER' => 'sqlite', 'DB_PATH' => $this->directory . '/db.sqlite', 'APP_URL' => 'https://meteor.example', 'WEBAUTHN_RP_ID' => 'meteor.example',
            'REDIRECT_STATUS' => '200', 'SCRIPT_FILENAME' => $this->root . '/public/index.php', 'SERVER_PROTOCOL' => 'HTTP/1.1', 'REMOTE_ADDR' => '127.0.0.1',
            'SESSION_NAME' => 'meteor_admin_test', 'SERVER_NAME' => 'meteor.example', 'SERVER_PORT' => '443', 'HTTPS' => 'on',
            'REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri, 'QUERY_STRING' => (string) parse_url($uri, PHP_URL_QUERY),
            'HTTP_COOKIE' => $this->cookie, 'CONTENT_TYPE' => $contentType, 'CONTENT_LENGTH' => $method === 'POST' ? (string) strlen($body) : '0']);
        if ($token !== null) { $environment['HTTP_X_CSRF_TOKEN'] = $token; }
        $process = proc_open([dirname(PHP_BINARY) . '/php-cgi', '-d', 'upload_max_filesize=6M', '-d', 'post_max_size=8M', '-d', 'session.save_path=' . $this->directory], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $this->root, $environment);
        if ($method === 'POST') { fwrite($pipes[0], $body); } fclose($pipes[0]);
        $response = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
        same(0, proc_close($process)); same('', $errors);
        [$headers, $html] = explode("\r\n\r\n", $response, 2);
        if (!str_contains($headers, 'Status:')) { $headers = "Status: 200 OK\r\n" . $headers; }
        if (preg_match('/Set-Cookie: (meteor_admin_test=[^;]+)/', $headers, $m)) { $this->cookie = $m[1]; }
        ok(!preg_match('/^Status: 503/m', $headers), $html);
        return [$headers, $html];
    }
    public function token(string $uri = '/admin/login'): string
    {
        [, $html] = $this->request($uri); preg_match('/name="csrf-token" content="([^"]+)"/', $html, $m); ok(isset($m[1]), 'CSRF meta missing'); return $m[1];
    }
    public function close(): void { foreach (glob($this->directory . '/*') as $file) { unlink($file); } rmdir($this->directory); Core\ReleasePackage::removeTree($this->root); }
}
test('Admin HTTP: anonymous cache, guarded routes, login CSRF, rotation, rate limit and logout', function () {
    $c = new AdminHttpClient();
    try {
        foreach (['/en/', '/en/posts', '/custom.css', '/sitemap.xml', '/ja/', '/missing'] as $path) {
            [$h] = $c->request($path); contains('Cache-Control: public, max-age=300', $h); ok(!str_contains($h, 'Set-Cookie:')); same('', $c->cookie);
        }
        foreach (['/admin', '/admin/posts', '/admin/pages', '/admin/editor', '/admin/taxonomy', '/admin/languages', '/admin/settings', '/admin/security', '/admin/updates', '/admin/logout', '/admin/security/passkeys/options', '/admin/security/passkeys', '/admin/posts/1/delete', '/admin/media', '/admin/media/upload', '/admin/optimization', '/admin/unknown'] as $path) {
            foreach (['GET', 'POST', 'PUT'] as $method) { [$h] = $c->request($path, $method); contains('302 Found', $h); contains('Location: /admin/login', $h); contains("script-src 'self'", $h); contains('X-Robots-Tag: noindex, nofollow', $h); contains('Cache-Control: no-store', $h); }
        }
        $token = $c->token(); $before = $c->cookie;
        [$cookieHeaders] = $c->request('/admin/login');
        contains('Cache-Control: no-store', $cookieHeaders);
        [$h] = $c->request('/admin/login', 'POST', ['username' => 'editor', 'password' => 'a long password']); contains('403 Forbidden', $h);
        [$h] = $c->request('/admin/passkey/login/options', 'POST', [], true); contains('403 Forbidden', $h);
        [$h, $body] = $c->request('/admin/passkey/login/options', 'POST', [], true, $token); contains('200 OK', $h); same(32, strlen(Core\WebAuthn::decode(json_decode($body, true)['challenge'])));
        [$h] = $c->request('/admin/login', 'POST', ['csrf_token' => $token, 'username' => 'editor', 'password' => 'a long password']); contains('303 See Other', $h); contains('Location: /admin/posts', $h); ok($before !== $c->cookie, 'Session must rotate');
        foreach (['/en/', '/ja/', '/custom.css', '/sitemap.xml'] as $path) { [$h] = $c->request($path); contains('Cache-Control: private, no-store', $h); }
        $token = $c->token('/admin/security');
        [$h] = $c->request('/admin/updates', 'POST', ['action' => 'check']); contains('403 Forbidden', $h);
        [$h] = $c->request('/admin/security/passkeys/options', 'POST', [], true); contains('403 Forbidden', $h);
        [$h] = $c->request('/admin/editor', 'POST', ['title' => 'No token']); contains('403 Forbidden', $h);
        [$h] = $c->request('/admin/logout', 'POST', ['csrf_token' => $token]); contains('Location: /admin/login', $h);
        $token = $c->token();
        for ($i = 0; $i < 5; $i++) { $c->request('/admin/login', 'POST', ['csrf_token' => $token, 'username' => 'editor', 'password' => 'wrong']); }
        same(5, (int) $c->db->one('SELECT COUNT(*) AS n FROM login_attempts')['n']);
        [$h] = $c->request('/admin/login', 'POST', ['csrf_token' => $token, 'username' => 'editor', 'password' => 'a long password']); contains('Location: /admin/login', $h);
        [, $html] = $c->request('/admin/login'); contains('Unable to sign in. Check your credentials or try again later.', $html);
        $c->db->query('UPDATE login_attempts SET attempted_at = ?', [gmdate('Y-m-d H:i:s', time() - 901)]);
        [$h] = $c->request('/admin/login', 'POST', ['csrf_token' => $token, 'username' => 'editor', 'password' => 'a long password']); contains('Location: /admin/posts', $h);
        $c->db->query('UPDATE users SET session_version = session_version + 1'); [$h] = $c->request('/admin/posts'); contains('Location: /admin/login', $h);
        [$h] = $c->request('/ja/'); contains('404 Not Found', $h);
    } finally { $c->close(); }
});
test('Admin HTTP: editor PRG validation, publication, language/settings and password change', function () {
    $c = new AdminHttpClient();
    try {
        $token = $c->token(); $c->request('/admin/login', 'POST', ['csrf_token' => $token, 'username' => 'editor', 'password' => 'a long password']);
        $token = $c->token('/admin/editor');
        $values = ['csrf_token' => $token, 'slug' => 'http-post', 'title' => 'HTTP post', 'summary' => 'A summary', 'content_md' => "## Heading\n\n**Saved**", 'is_published' => '1', 'status' => 'draft'];
        [$h] = $c->request('/admin/editor?locale=en', 'POST', $values); contains('303 See Other', $h); contains('post=1', $h);
        [$h] = $c->request('/en/posts/http-post'); contains('404 Not Found', $h);
        $values['status'] = 'published'; $c->request('/admin/editor?post=1&locale=en', 'POST', $values);
        [$h, $html] = $c->request('/en/posts/http-post'); contains('200 OK', $h); contains('<strong>Saved</strong>', $html); ok(!str_contains($html, '<script'));
        $bad = array_replace($values, ['slug' => '../invalid', 'content_md' => '<script>preserved</script>']);
        [$h] = $c->request('/admin/editor?post=1&locale=en', 'POST', $bad); contains('303 See Other', $h);
        preg_match('/Location: ([^\r]+)/', $h, $location); [, $html] = $c->request($location[1]); contains('Slug: use letters', $html); contains('&lt;script&gt;preserved&lt;/script&gt;', $html);
        same('http-post', $c->db->one('SELECT slug FROM posts')['slug']);
        $c->request('/admin/languages', 'POST', ['csrf_token' => $token, 'action' => 'add', 'code' => 'fr', 'name' => 'French', 'native_name' => 'Français']);
        same('preview', $c->db->one('SELECT status FROM locales WHERE code = ?', ['fr'])['status']);
        $c->request('/admin/settings?locale=en', 'POST', ['csrf_token' => $token, 'locale' => 'en', 'text' => ['site_title' => ['en' => 'My HTTP Site'], 'copyright' => ['en' => 'Copyright test']], 'custom_css' => 'body {color:red}']);
        [, $html] = $c->request('/en/'); contains('My HTTP Site', $html); contains('Copyright test', $html);
        $c->request('/admin/security', 'POST', ['csrf_token' => $token, 'action' => 'username', 'username' => 'renamed', 'current_password' => 'a long password']);
        same('renamed', $c->db->one('SELECT username FROM users')['username']);
        $oldCookie = $c->cookie;
        $c->request('/admin/security', 'POST', ['csrf_token' => $token, 'action' => 'password', 'current_password' => 'a long password', 'new_password' => 'a changed password', 'new_password_confirm' => 'a changed password']);
        [$h] = $c->request('/admin/posts'); contains('200 OK', $h); same(2, (int) $c->db->one('SELECT session_version FROM users')['session_version']);
        $c->request('/admin/posts/1/delete', 'POST', ['csrf_token' => $token]); same([], $c->db->all('SELECT * FROM posts'));
    } finally { $c->close(); }
});
test('Admin HTTP: passkey JSON registration/login, rename/remove and ownership', function () {
    $c = new AdminHttpClient();
    try {
        $token = $c->token(); $c->request('/admin/login', 'POST', ['csrf_token' => $token, 'username' => 'editor', 'password' => 'a long password']);
        $token = $c->token('/admin/security');
        $f = webFixture(-7);
        [, $body] = $c->request('/admin/security/passkeys/options', 'POST', [], true, $token); $options = json_decode($body, true);
        $registration = registration($f, ['client' => ['challenge' => $options['challenge']]]); $registration['name'] = 'HTTP laptop';
        [$h, $body] = $c->request('/admin/security/passkeys', 'POST', $registration, true, $token); contains('200 OK', $h); same('/admin/security', json_decode($body, true)['redirect']);
        same('HTTP laptop', $c->db->one('SELECT name FROM passkeys')['name']);
        [$h] = $c->request('/admin/security/passkeys', 'POST', $registration, true, $token); contains('400 Bad Request', $h);
        $c->request('/admin/security', 'POST', ['csrf_token' => $token, 'action' => 'rename', 'id' => 1, 'name' => 'Renamed key']);
        same('Renamed key', $c->db->one('SELECT name FROM passkeys')['name']);
        $c->request('/admin/logout', 'POST', ['csrf_token' => $token]); $token = $c->token(); $oldCookie = $c->cookie;
        [, $body] = $c->request('/admin/passkey/login/options', 'POST', [], true, $token); $options = json_decode($body, true);
        $assertion = assertion($f, 2, ['client' => ['challenge' => $options['challenge']]]);
        [$h, $body] = $c->request('/admin/passkey/login/verify', 'POST', $assertion, true, $token); contains('200 OK', $h); same('/admin/posts', json_decode($body, true)['redirect']); ok($oldCookie !== $c->cookie);
        same(2, (int) $c->db->one('SELECT sign_count FROM passkeys')['sign_count']);
        $token = $c->token('/admin/security'); $c->request('/admin/security', 'POST', ['csrf_token' => $token, 'action' => 'remove', 'id' => 1, 'current_password' => 'wrong']); same(1, count($c->db->all('SELECT id FROM passkeys')));
        $c->request('/admin/security', 'POST', ['csrf_token' => $token, 'action' => 'remove', 'id' => 1, 'current_password' => 'a long password']);
        same([], $c->db->all('SELECT * FROM passkeys')); [, $html] = $c->request('/admin/security'); contains('Password sign-in remains available.', $html);
    } finally { $c->close(); }
});
test('Admin HTTP: MIME/size-checked cover uploads, replacement cleanup and delete', function () {
    $c = new AdminHttpClient(); $files = [];
    try {
        $token = $c->token(); $c->request('/admin/login', 'POST', ['csrf_token' => $token, 'username' => 'editor', 'password' => 'a long password']); $token = $c->token('/admin/editor');
        $values = ['csrf_token' => $token, 'slug' => 'cover-test', 'title' => 'Cover', 'status' => 'draft'];
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6wWQAAAAASUVORK5CYII=');
        $c->request('/admin/editor', 'POST', $values, false, null, ['bytes' => $png]);
        $path = $c->db->one('SELECT cover_image FROM posts')['cover_image']; $files[] = $path; ok(preg_match('~^/uploads/[a-f0-9]{32}\.png$~', $path) === 1); ok(is_file($c->root . '/public' . $path));
        $c->request('/admin/editor?post=1', 'POST', $values, false, null, ['bytes' => $png]);
        $newPath = $c->db->one('SELECT cover_image FROM posts')['cover_image']; $files[] = $newPath; ok($newPath !== $path); ok(!is_file($c->root . '/public' . $path));
        foreach (['<?php echo "bad";', $png . str_repeat('x', 5 * 1024 * 1024)] as $bytes) {
            [$h] = $c->request('/admin/editor?post=1', 'POST', $values, false, null, ['bytes' => $bytes]); contains('303 See Other', $h);
            same($newPath, $c->db->one('SELECT cover_image FROM posts')['cover_image']);
        }
        $c->request('/admin/posts/1/delete', 'POST', ['csrf_token' => $token]); ok(!is_file($c->root . '/public' . $newPath));
    } finally { foreach ($files as $file) { $path = $c->root . '/public' . $file; if (is_file($path)) { unlink($path); } } $c->close(); }
});
test('Admin HTTP: site identity uploads, cleanup and settings validation PRG', function () {
    $c = new AdminHttpClient();
    try {
        $token = $c->token(); $c->request('/admin/login', 'POST', ['csrf_token' => $token, 'username' => 'editor', 'password' => 'a long password']);
        $token = $c->token('/admin/settings'); $values = ['csrf_token' => $token, 'locale' => 'en'];
        $c->request('/admin/settings', 'POST', $values + ['text' => ['site_title' => ['en' => 'My & Site']]]);
        [, $html] = $c->request('/admin/settings'); contains('enctype="multipart/form-data"', $html);
        foreach (array_keys(App\Services\AdminSettings::TEXT) as $key) { contains('name="text[' . $key . '][en]"', $html); }
        foreach (['site_icon', 'remove_site_icon'] as $field) { contains('name="' . $field . '"', $html); }
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6wWQAAAAASUVORK5CYII=');
        $upload = ['field' => 'site_icon', 'filename' => 'icon.png', 'bytes' => $png];
        $c->request('/admin/settings', 'POST', $values, false, null, $upload);
        $path = $c->db->one("SELECT value FROM settings WHERE `key` = 'site_icon'")['value']; ok(App\Services\SiteIcon::validPath($path)); ok(is_file($c->root . '/public' . $path));
        [, $html] = $c->request('/admin/settings'); contains('src="' . $path . '"', $html); contains('My &amp; Site Admin', $html); contains('My &amp; Site</a>', $html);
        $cookie = $c->cookie; $c->cookie = '';
        [, $login] = $c->request('/admin/login'); contains('src="' . $path . '"', $login); contains('My &amp; Site', $login); $c->cookie = $cookie;
        $c->request('/admin/settings', 'POST', $values, false, null, $upload);
        $new = $c->db->one("SELECT value FROM settings WHERE `key` = 'site_icon'")['value']; ok($new !== $path); ok(!is_file($c->root . '/public' . $path));
        foreach (['<svg xmlns="http://www.w3.org/2000/svg"></svg>', 'plain text', $png . str_repeat('x', 1024 * 1024)] as $bytes) {
            [$h] = $c->request('/admin/settings', 'POST', $values, false, null, ['field' => 'site_icon', 'filename' => 'fake.svg', 'bytes' => $bytes]); contains('303', $h);
            [, $html] = $c->request('/admin/settings'); contains('Site icon: upload a valid', $html);
            same($new, $c->db->one("SELECT value FROM settings WHERE `key` = 'site_icon'")['value']);
        }
        $c->request('/admin/settings', 'POST', $values + ['remove_site_icon' => '1']); ok(!is_file($c->root . '/public' . $new)); same(null, $c->db->one("SELECT value FROM settings WHERE `key` = 'site_icon'"));
        $c->request('/admin/settings', 'POST', $values + ['text' => ['site_tagline' => ['en' => 'Keep <this>', 'nope' => 'x']]]);
        [, $html] = $c->request('/admin/settings?locale=en'); contains('Unknown language.', $html); contains('value="Keep &lt;this&gt;"', $html);
        same(null, $c->db->one("SELECT value FROM settings WHERE `key` = 'site_tagline'"));
    } finally { $c->close(); }
});
test('Admin HTTP: header & footer page, settings sheet and taxonomy rows', function () {
    $c = new AdminHttpClient();
    try {
        $token = $c->token(); $c->request('/admin/login', 'POST', ['csrf_token' => $token, 'username' => 'editor', 'password' => 'a long password']);
        $token = $c->token('/admin/menus');
        [$h, $html] = $c->request('/admin/menus'); contains('200 OK', $h);
        contains('href="/admin/menus" aria-current="page"', $html); contains('<span lang="ja"', $html); contains('ja · preview', $html);
        contains('name="footer_menu_heading[ja]"', $html); same(12, (int) $c->db->one('SELECT COUNT(*) AS n FROM menu_items')['n']);
        $posts = $c->db->one("SELECT id FROM menu_items WHERE menu = 'header' AND kind = 'posts'")['id'];
        [$h] = $c->request('/admin/menus', 'POST', ['csrf_token' => $token, 'menu' => 'header', 'header_show_switcher' => '0',
            'items' => [$posts => ['sort_order' => '9', 'visible' => '1', 'labels' => ['en' => 'Journal']], 'new' => ['url' => '/{locale}/now', 'labels' => ['en' => 'Now', 'ja' => '']]]]);
        contains('303', $h);
        [, $html] = $c->request('/en/'); preg_match('~<header\b.*?</header>~s', $html, $header);
        contains('href="/en/now">Now', $header[0]); contains('>Journal</a>', $header[0]); ok(!str_contains($header[0], 'aria-label="Language"'));
        $c->request('/admin/menus', 'POST', ['csrf_token' => $token, 'menu' => 'footer', 'items' => ['new' => ['url' => 'javascript:alert(1)', 'labels' => ['en' => 'Keep <me>']]]]);
        [, $html] = $c->request('/admin/menus'); contains('Footer menu, new link: use a URL', $html); contains('value="javascript:alert(1)"', $html); contains('value="Keep &lt;me&gt;"', $html);
        same(null, $c->db->one("SELECT id FROM menu_items WHERE url LIKE 'javascript%'"));
        // Site text: one cell changes, the other language stays.
        $token = $c->token('/admin/settings');
        $c->request('/admin/settings', 'POST', ['csrf_token' => $token, 'locale' => 'en', 'text' => ['site_title' => ['ja' => 'メテオ']]]);
        $c->request('/admin/settings', 'POST', ['csrf_token' => $token, 'locale' => 'en', 'text' => ['site_title' => ['en' => 'Meteor EN']]]);
        same(['en' => 'Meteor EN', 'ja' => 'メテオ'], array_column($c->db->all("SELECT locale, value FROM settings WHERE `key` = 'site_title' ORDER BY locale"), 'value', 'locale'));
        [, $html] = $c->request('/admin/settings'); contains('name="text[home_intro][ja]"', $html); contains('value="メテオ"', $html);
        // Taxonomy: each row is its own form.
        $token = $c->token('/admin/taxonomy');
        $c->request('/admin/taxonomy', 'POST', ['csrf_token' => $token, 'kind' => 'tag', 'id' => '0', 'action' => 'save', 'slug' => 'php', 'names' => ['en' => 'PHP', 'ja' => 'PHP 日本']]);
        $c->request('/admin/taxonomy', 'POST', ['csrf_token' => $token, 'kind' => 'category', 'id' => '0', 'action' => 'save', 'slug' => 'notes', 'sort_order' => '2', 'names' => ['en' => 'Notes']]);
        $tag = (int) $c->db->one('SELECT id FROM tags')['id'];
        [, $html] = $c->request('/admin/taxonomy');
        contains('<form id="term-tag-' . $tag . '"', $html); contains('form="term-tag-' . $tag . '" name="names[ja]"', $html); contains('form="term-category-new"', $html);
        ok(!str_contains($html, 'Delete and detach'));
        $c->request('/admin/taxonomy?q=php', 'POST', ['csrf_token' => $token, 'kind' => 'tag', 'id' => (string) $tag, 'action' => 'save', 'slug' => 'php-lang', 'names' => ['en' => 'PHP language']]);
        same('PHP 日本', $c->db->one("SELECT name FROM tag_translations WHERE locale = 'ja'")['name']); same('php-lang', $c->db->one('SELECT slug FROM tags')['slug']);
        [, $html] = $c->request('/admin/taxonomy?q=notes'); contains('value="notes"', $html); ok(!str_contains($html, 'value="php-lang"'));
        [, $html] = $c->request('/admin/taxonomy/tag/' . $tag . '/delete'); contains('Delete tag “PHP language”?', $html); same(1, (int) $c->db->one('SELECT COUNT(*) AS n FROM tags')['n']);
        [$h] = $c->request('/admin/taxonomy/tag/' . $tag . '/delete', 'POST', ['csrf_token' => $token]); contains('Location: /admin/taxonomy', $h);
        same(0, (int) $c->db->one('SELECT COUNT(*) AS n FROM tags')['n']);
        [$h] = $c->request('/admin/taxonomy/tag/' . $tag . '/delete'); contains('404', $h);
    } finally { $c->close(); }
});
test('Admin HTTP: interface text table, language file download and import', function () {
    $c = new AdminHttpClient();
    try {
        $c->db->query("INSERT INTO locales (code, name, native_name, status, sort_order) VALUES ('zh', 'Chinese', '简体中文', 'off', 2)");
        $token = $c->token(); $c->request('/admin/login', 'POST', ['csrf_token' => $token, 'username' => 'editor', 'password' => 'a long password']);
        $token = $c->token('/admin/strings');
        [$h, $html] = $c->request('/admin/strings'); contains('200 OK', $h);
        contains('href="/admin/settings" aria-current="page"', $html); contains('href="/admin/strings" class=', $html); contains('data-changed-only', $html);
        contains('Layout &amp; navigation</span>', $html); contains('name="strings[zh][posts]"', $html); contains('value="文章"', $html); contains('43 missing', $html);
        $c->request('/admin/strings', 'POST', ['csrf_token' => $token, 'strings' => ['ja' => ['posts' => '投稿'], 'zh' => ['posts' => '文章']]]);
        same([['locale' => 'ja', 'key' => 'posts', 'value' => '投稿']], $c->db->all('SELECT locale, `key`, value FROM ui_strings'));
        $c->request('/admin/strings?q=minutes', 'POST', ['csrf_token' => $token, 'strings' => ['ja' => ['reading_minutes' => '読了', 'tags' => 'タグ']]]);
        [, $html] = $c->request('/admin/strings?q=minutes'); contains('keep the placeholder {minutes}', $html); contains('value="読了" placeholder="{minutes} min read" aria-label="reading_minutes, ja" data-unsaved', $html);
        ok(!str_contains($html, 'name="strings[ja][posts]"')); same(null, $c->db->one("SELECT value FROM ui_strings WHERE `key` = 'tags'"));
        [, $html] = $c->request('/admin/strings?missing=1&q=nothing-matches'); contains('No interface text matches', $html);
        // Download, then import into a fresh language code.
        [$h, $json] = $c->request('/admin/languages/export?code=ja');
        contains('Content-Type: application/json', $h); contains('attachment; filename="meteor-language-ja.json"', $h); same('投稿', json_decode($json, true)['strings']['posts']);
        [$h] = $c->request('/admin/languages/export?code=nope'); contains('404', $h);
        [, $html] = $c->request('/admin/languages'); contains('href="/admin/languages/export?code=zh" download', $html); contains('name="language_file"', $html); contains('43 / 43', $html);
        $pack = json_encode(['meteor_language_pack' => 1, 'code' => 'fr', 'name' => 'French', 'native_name' => 'Français', 'direction' => 'ltr', 'strings' => ['posts' => 'Articles']]);
        $c->request('/admin/languages', 'POST', ['csrf_token' => $token, 'action' => 'import'], false, null, ['field' => 'language_file', 'filename' => 'fr.json', 'bytes' => $pack]);
        [, $html] = $c->request('/admin/languages'); contains('Imported 1 interface text into French (added as Off).', $html);
        same('off', $c->db->one("SELECT status FROM locales WHERE code = 'fr'")['status']); same('Articles', $c->db->one("SELECT value FROM ui_strings WHERE locale = 'fr'")['value']);
        $c->request('/admin/languages', 'POST', ['csrf_token' => $token, 'action' => 'import'], false, null, ['field' => 'language_file', 'filename' => 'x.json', 'bytes' => '<?php echo 1;']);
        [, $html] = $c->request('/admin/languages'); contains('not a valid Meteor language file', $html);
    } finally { $c->close(); }
});
