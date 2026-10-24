<?php
final class AdminHttpClient
{
    public Core\Database $db;
    public string $cookie = '';
    public string $directory;
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
        // The editor shows the publish date; the date survives a validation error; Extended Markdown can start ticked.
        [, $html] = $c->request('/admin/editor?post=1&locale=en'); contains('type="datetime-local" name="date" step="60" value="' . str_replace(' ', 'T', substr($c->db->one('SELECT published_at FROM posts')['published_at'], 0, 16)) . '"', $html);
        $c->request('/admin/editor?post=1&locale=en', 'POST', array_replace($bad, ['date' => '2012-12-12T12:12']));
        [, $html] = $c->request('/admin/editor?post=1&locale=en&kind=post&from=en'); contains('name="date" step="60" value="2012-12-12T12:12"', $html);
        $c->request('/admin/editor?post=1&locale=en', 'POST', $values + ['date' => '2012-12-12T12:12']);
        [, $html] = $c->request('/en/posts/http-post'); contains('datetime="2012-12-12 12:12:00"', $html);
        [, $html] = $c->request('/admin/editor?kind=page'); contains('Date (UTC)', $html); contains('name="date" step="60" value=""', $html); ok(!preg_match('~name="markdown_extended"[^>]*checked~', $html), 'Off by default');
        [, $html] = $c->request('/admin/settings'); contains('Enable Extended Markdown', $html);
        $c->request('/admin/settings', 'POST', ['csrf_token' => $token, 'markdown_extended_default' => '1']);
        same('1', $c->db->one("SELECT value FROM settings WHERE `key` = 'markdown_extended_default'")['value']);
        [, $html] = $c->request('/admin/editor?kind=page'); ok((bool) preg_match('~name="markdown_extended"[^>]*checked~', $html), 'New pages start extended');
        [, $html] = $c->request('/admin/editor?post=1&locale=en'); ok(!preg_match('~name="markdown_extended"[^>]*checked~', $html), 'Saved posts keep their choice');
        $c->request('/admin/settings', 'POST', ['csrf_token' => $token, 'markdown_extended_default' => '0']);
        [, $html] = $c->request('/admin/editor'); ok(!preg_match('~name="markdown_extended"[^>]*checked~', $html));
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
function mediaRow(Core\Database $db, string $mime = 'image/png'): string
{
    $name = bin2hex(random_bytes(16)) . '.' . (['image/png' => 'png', 'image/svg+xml' => 'svg', 'image/avif' => 'avif'][$mime] ?? 'png');
    $db->query('INSERT INTO media (file_name, original_name, mime_type, search_text) VALUES (?, ?, ?, ?)', [$name, 'x', $mime, 'x']);
    return App\Services\MediaLibrary::url($name);
}
test('Admin HTTP: post cover from the media library, legacy covers and validation', function () {
    $c = new AdminHttpClient(); $legacy = '/uploads/' . bin2hex(random_bytes(16)) . '.png';
    try {
        $token = $c->token(); $c->request('/admin/login', 'POST', ['csrf_token' => $token, 'username' => 'editor', 'password' => 'a long password']); $token = $c->token('/admin/editor');
        [, $html] = $c->request('/admin/editor'); contains('name="cover_image"', $html); contains('data-media-field', $html); contains('id="media-picker"', $html); ok(!str_contains($html, 'type="file" name="cover"'));
        $url = mediaRow($c->db);
        $values = ['csrf_token' => $token, 'slug' => 'cover-test', 'title' => 'Cover', 'status' => 'draft'];
        $c->request('/admin/editor', 'POST', $values + ['cover_image' => $url]);
        same($url, $c->db->one('SELECT cover_image FROM posts')['cover_image']);
        $library = new App\Services\MediaLibrary($c->db, $c->root . '/public');
        same(1, $library->usage($c->db->one('SELECT * FROM media')));
        foreach (['/uploads/media/' . str_repeat('b', 32) . '.png', 'https://example.com/x.png', '/uploads/media/../../x.png'] as $bad) {
            [$h] = $c->request('/admin/editor?post=1', 'POST', $values + ['cover_image' => $bad]); contains('303', $h);
            same($url, $c->db->one('SELECT cover_image FROM posts')['cover_image']);
        }
        [, $html] = $c->request('/admin/editor?' . http_build_query(['post' => 1, 'locale' => 'en', 'kind' => 'post', 'from' => 'en'])); contains('Cover: choose an image from the media library.', $html);
        // An older cover stored straight in /uploads stays while unchanged and is removed when replaced.
        file_put_contents($c->root . '/public' . $legacy, 'x'); $c->db->query('UPDATE posts SET cover_image = ?', [$legacy]);
        $c->request('/admin/editor?post=1', 'POST', $values + ['cover_image' => $legacy]); same($legacy, $c->db->one('SELECT cover_image FROM posts')['cover_image']); ok(is_file($c->root . '/public' . $legacy));
        $c->request('/admin/editor?post=1', 'POST', $values); same($legacy, $c->db->one('SELECT cover_image FROM posts')['cover_image']);
        $c->request('/admin/editor?post=1', 'POST', $values + ['cover_image' => $url]); same($url, $c->db->one('SELECT cover_image FROM posts')['cover_image']); ok(!is_file($c->root . '/public' . $legacy));
        $c->request('/admin/editor?post=1', 'POST', $values + ['cover_image' => '']); same(null, $c->db->one('SELECT cover_image FROM posts')['cover_image']);
    } finally { if (is_file($c->root . '/public' . $legacy)) { unlink($c->root . '/public' . $legacy); } $c->close(); }
});
test('Admin HTTP: site icon from the media library and settings validation PRG', function () {
    $c = new AdminHttpClient(); $legacy = '/uploads/' . bin2hex(random_bytes(16)) . '.png';
    try {
        $token = $c->token(); $c->request('/admin/login', 'POST', ['csrf_token' => $token, 'username' => 'editor', 'password' => 'a long password']);
        $token = $c->token('/admin/settings'); $values = ['csrf_token' => $token, 'locale' => 'en'];
        $c->request('/admin/settings', 'POST', $values + ['text' => ['site_title' => ['en' => 'My & Site']]]);
        [, $html] = $c->request('/admin/settings');
        foreach (array_keys(App\Services\AdminSettings::TEXT) as $key) { contains('name="text[' . $key . '][en]"', $html); }
        contains('name="site_icon"', $html); contains('id="media-picker"', $html); ok(!str_contains($html, 'remove_site_icon'));
        $icon = mediaRow($c->db);
        $c->request('/admin/settings', 'POST', $values + ['site_icon' => $icon]);
        same($icon, $c->db->one("SELECT value FROM settings WHERE `key` = 'site_icon'")['value']); ok(App\Services\SiteIcon::validPath($icon));
        same(1, (new App\Services\MediaLibrary($c->db, $c->root . '/public'))->usage($c->db->one('SELECT * FROM media')));
        [, $html] = $c->request('/admin/settings'); contains('src="' . $icon . '"', $html); contains('My &amp; Site Admin', $html); contains('My &amp; Site</span></a>', $html);
        $cookie = $c->cookie; $c->cookie = '';
        [, $login] = $c->request('/admin/login'); contains('src="' . $icon . '"', $login); contains('My &amp; Site', $login); $c->cookie = $cookie;
        foreach ([mediaRow($c->db, 'image/avif'), '/uploads/media/' . str_repeat('c', 32) . '.png', '/assets/favicon.svg'] as $bad) {
            [$h] = $c->request('/admin/settings', 'POST', $values + ['site_icon' => $bad]); contains('303', $h);
            [, $html] = $c->request('/admin/settings'); contains('Site icon: choose a PNG, JPG, WebP or GIF image from the media library.', $html);
            same($icon, $c->db->one("SELECT value FROM settings WHERE `key` = 'site_icon'")['value']);
        }
        // An older icon uploaded straight to /uploads is deleted when it is replaced.
        file_put_contents($c->root . '/public' . $legacy, 'x'); $c->db->query("UPDATE settings SET value = ? WHERE `key` = 'site_icon'", [$legacy]);
        $c->request('/admin/settings', 'POST', $values + ['site_icon' => $icon]); ok(!is_file($c->root . '/public' . $legacy));
        $c->request('/admin/settings', 'POST', $values + ['site_icon' => '']); same(null, $c->db->one("SELECT value FROM settings WHERE `key` = 'site_icon'"));
        $c->request('/admin/settings', 'POST', $values + ['text' => ['site_tagline' => ['en' => 'Keep <this>', 'nope' => 'x']]]);
        [, $html] = $c->request('/admin/settings?locale=en'); contains('Unknown language.', $html); contains('value="Keep &lt;this&gt;"', $html);
        same(null, $c->db->one("SELECT value FROM settings WHERE `key` = 'site_tagline'"));
    } finally { if (is_file($c->root . '/public' . $legacy)) { unlink($c->root . '/public' . $legacy); } $c->close(); }
});
test('Admin HTTP: header & footer page, settings sheet and taxonomy rows', function () {
    $c = new AdminHttpClient();
    try {
        $token = $c->token(); $c->request('/admin/login', 'POST', ['csrf_token' => $token, 'username' => 'editor', 'password' => 'a long password']);
        $token = $c->token('/admin/menus');
        [$h, $html] = $c->request('/admin/menus'); contains('200 OK', $h);
        contains('href="/admin/menus" aria-current="page"', $html); contains('<span lang="ja"', $html); contains('ja · preview', $html);
        contains('href="/admin/menus?tab=footer" class=', $html); ok(!str_contains($html, 'name="footer_menu_heading[ja]"'), 'Footer belongs to its own tab');
        [, $html] = $c->request('/admin/menus?tab=footer'); contains('href="/admin/menus?tab=footer" class="-mb-px', $html); contains('aria-current="page">Footer', $html);
        contains('name="footer_menu_heading[ja]"', $html); ok(!str_contains($html, 'name="header_show_switcher"')); same(8, (int) $c->db->one('SELECT COUNT(*) AS n FROM menu_items')['n']);
        $posts = $c->db->one("SELECT id FROM menu_items WHERE menu = 'header' AND kind = 'posts'")['id'];
        [$h] = $c->request('/admin/menus', 'POST', ['csrf_token' => $token, 'menu' => 'header', 'header_show_switcher' => '0',
            'items' => [$posts => ['sort_order' => '9', 'visible' => '1', 'labels' => ['en' => 'Journal']], 'new' => ['url' => '/{locale}/now', 'labels' => ['en' => 'Now', 'ja' => '']]]]);
        contains('303', $h);
        [, $html] = $c->request('/en/'); preg_match('~<header\b.*?</header>~s', $html, $header);
        contains('href="/en/now">Now', $header[0]); contains('>Journal</a>', $header[0]); ok(!str_contains($header[0], 'aria-label="Language"'));
        [$h] = $c->request('/admin/menus?tab=footer', 'POST', ['csrf_token' => $token, 'menu' => 'footer', 'items' => ['new' => ['url' => 'javascript:alert(1)', 'labels' => ['en' => 'Keep <me>']]]]);
        contains('Location: /admin/menus?tab=footer', $h);
        [, $html] = $c->request('/admin/menus?tab=footer'); contains('Footer menu, new link: use a URL', $html); contains('value="javascript:alert(1)"', $html); contains('value="Keep &lt;me&gt;"', $html);
        same(null, $c->db->one("SELECT id FROM menu_items WHERE url LIKE 'javascript%'"));
        // Site text: one cell changes, the other language stays.
        $token = $c->token('/admin/settings');
        $c->request('/admin/settings', 'POST', ['csrf_token' => $token, 'locale' => 'en', 'text' => ['site_title' => ['ja' => 'メテオ']]]);
        $c->request('/admin/settings', 'POST', ['csrf_token' => $token, 'locale' => 'en', 'text' => ['site_title' => ['en' => 'Meteor EN']]]);
        same(['en' => 'Meteor EN', 'ja' => 'メテオ'], array_column($c->db->all("SELECT locale, value FROM settings WHERE `key` = 'site_title' ORDER BY locale"), 'value', 'locale'));
        [, $html] = $c->request('/admin/settings'); contains('name="text[footer_text][ja]"', $html); contains('value="メテオ"', $html);
        // Taxonomy: each row is its own form.
        $token = $c->token('/admin/taxonomy');
        $c->request('/admin/taxonomy', 'POST', ['csrf_token' => $token, 'kind' => 'tag', 'id' => '0', 'action' => 'save', 'slug' => 'php', 'names' => ['en' => 'PHP', 'ja' => 'PHP 日本']]);
        $c->request('/admin/taxonomy', 'POST', ['csrf_token' => $token, 'kind' => 'category', 'id' => '0', 'action' => 'save', 'slug' => 'notes', 'sort_order' => '2', 'names' => ['en' => 'Notes']]);
        $tag = (int) $c->db->one('SELECT id FROM tags')['id'];
        [, $html] = $c->request('/admin/taxonomy');
        contains('form="term-category-new"', $html); ok(!str_contains($html, 'term-tag-'), 'Tags belong to their own tab'); contains('Tags<span class="ml-1.5 font-mono text-xs font-normal text-slate-500 dark:text-slate-400">1</span>', $html);
        [, $html] = $c->request('/admin/taxonomy?tab=tags');
        contains('<form id="term-tag-' . $tag . '"', $html); contains('form="term-tag-' . $tag . '" name="names[ja]"', $html); ok(!str_contains($html, 'term-category-'));
        contains('action="/admin/taxonomy?tab=tags"', $html);
        ok(!str_contains($html, 'Delete and detach'));
        [$h] = $c->request('/admin/taxonomy?tab=tags&q=php', 'POST', ['csrf_token' => $token, 'kind' => 'tag', 'id' => (string) $tag, 'action' => 'save', 'slug' => 'php-lang', 'names' => ['en' => 'PHP language']]);
        contains('Location: /admin/taxonomy?tab=tags&q=php', $h);
        same('PHP 日本', $c->db->one("SELECT name FROM tag_translations WHERE locale = 'ja'")['name']); same('php-lang', $c->db->one('SELECT slug FROM tags')['slug']);
        [, $html] = $c->request('/admin/taxonomy?q=notes'); contains('value="notes"', $html); ok(!str_contains($html, 'value="php-lang"'));
        [, $html] = $c->request('/admin/taxonomy/tag/' . $tag . '/delete'); contains('Delete tag “PHP language”?', $html); same(1, (int) $c->db->one('SELECT COUNT(*) AS n FROM tags')['n']);
        [$h] = $c->request('/admin/taxonomy/tag/' . $tag . '/delete', 'POST', ['csrf_token' => $token]); contains('Location: /admin/taxonomy?tab=tags', $h);
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
        contains('Layout &amp; navigation</span>', $html); contains('name="strings[zh][posts]"', $html); contains('value="文章"', $html); contains('90 missing', $html);
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
        [, $html] = $c->request('/admin/languages'); contains('href="/admin/languages/export?code=zh" download', $html); contains('name="language_file"', $html); contains('90 / 90', $html);
        $pack = json_encode(['meteor_language_pack' => 1, 'code' => 'fr', 'name' => 'French', 'native_name' => 'Français', 'direction' => 'ltr', 'strings' => ['posts' => 'Articles']]);
        $c->request('/admin/languages', 'POST', ['csrf_token' => $token, 'action' => 'import'], false, null, ['field' => 'language_file', 'filename' => 'fr.json', 'bytes' => $pack]);
        [, $html] = $c->request('/admin/languages'); contains('Imported 1 interface text into French (added as Off).', $html);
        same('off', $c->db->one("SELECT status FROM locales WHERE code = 'fr'")['status']); same('Articles', $c->db->one("SELECT value FROM ui_strings WHERE locale = 'fr'")['value']);
        $c->request('/admin/languages', 'POST', ['csrf_token' => $token, 'action' => 'import'], false, null, ['field' => 'language_file', 'filename' => 'x.json', 'bytes' => '<?php echo 1;']);
        [, $html] = $c->request('/admin/languages'); contains('not a valid Meteor language file', $html);
    } finally { $c->close(); }
});
test('Admin HTTP: one site-wide CAPTCHA on Security, used by the sign-in page and comments', function () {
    $c = new AdminHttpClient();
    try {
        [, $html] = $c->request('/admin/login'); ok(!str_contains($html, 'cf-turnstile') && !str_contains($html, 'g-recaptcha'), 'Off by default');
        $token = $c->token(); $c->request('/admin/login', 'POST', ['csrf_token' => $token, 'username' => 'editor', 'password' => 'a long password']);
        $token = $c->token('/admin/security');
        [, $html] = $c->request('/admin/security'); contains('id="captcha-heading"', $html); contains('name="turnstile_site_key"', $html); contains('name="recaptcha_site_key"', $html); contains('name="captcha_provider" value="turnstile" checked', $html);
        $keys = ['turnstile_enabled' => '0', 'turnstile_site_key' => '0x4AAAAAAAsitekey', 'turnstile_secret_key' => '0x4AAAAAAAsecret'];
        $c->request('/admin/security', 'POST', ['csrf_token' => $token, 'action' => 'captcha', 'recaptcha_secret_key' => '6LcRECAPTCHAsecretvalue000', 'current_password' => 'wrong'] + $keys);
        [, $html] = $c->request('/admin/security'); contains('Current password is incorrect.', $html); ok(!str_contains($html, '0x4AAAAAAAsecret'), 'Secret must not be kept in the form'); ok(!str_contains($html, '6LcRECAPTCHAsecretvalue000'));
        // Choosing reCAPTCHA without its keys saves nothing, not even the Turnstile keys.
        $c->request('/admin/security', 'POST', ['csrf_token' => $token, 'action' => 'captcha', 'captcha_provider' => 'recaptcha', 'current_password' => 'a long password'] + $keys);
        [, $html] = $c->request('/admin/security'); contains('Enter the Google reCAPTCHA v2 site key and secret key', $html); contains('name="captcha_provider" value="recaptcha" checked', $html);
        same(null, $c->db->one("SELECT value FROM settings WHERE `key` = 'turnstile_secret_key'"));
        $c->request('/admin/security', 'POST', ['csrf_token' => $token, 'action' => 'captcha', 'captcha_provider' => 'recaptcha', 'recaptcha_site_key' => '6LcRECAPTCHAsitekeyvalue00', 'recaptcha_secret_key' => '6LcRECAPTCHAsecretvalue000', 'current_password' => 'a long password'] + $keys);
        same('0x4AAAAAAAsecret', $c->db->one("SELECT value FROM settings WHERE `key` = 'turnstile_secret_key'")['value']);
        same('6LcRECAPTCHAsecretvalue000', $c->db->one("SELECT value FROM settings WHERE `key` = 'recaptcha_secret_key'")['value']);
        [, $html] = $c->request('/admin/comments?tab=settings'); contains('Site CAPTCHA: Google reCAPTCHA v2 · keys saved ✓', $html);
        [, $html] = $c->request('/admin/security'); contains('value="0x4AAAAAAAsitekey"', $html); contains('value="6LcRECAPTCHAsitekeyvalue00"', $html); contains('Saved · leave empty to keep', $html); ok(!str_contains($html, '0x4AAAAAAAsecret')); ok(!str_contains($html, 'secretvalue000'));
        // Turn the sign-in check on directly: saving through the form would ask the provider to confirm the secret.
        $c->db->upsert('settings', ['key' => 'turnstile_enabled', 'locale' => null, 'value' => '1'], ['key', 'locale_scope']);
        $c->request('/admin/logout', 'POST', ['csrf_token' => $token]);
        [$h, $html] = $c->request('/admin/login'); contains('class="g-recaptcha min-h-[78px]" data-sitekey="6LcRECAPTCHAsitekeyvalue00"', $html); contains('<script src="https://www.google.com/recaptcha/api.js"', $html); ok(!str_contains($html, 'cf-turnstile'));
        contains("script-src 'self' https://www.google.com https://www.gstatic.com; frame-src https://www.google.com;", $h); same(1, substr_count($h, 'Content-Security-Policy'));
        // The same site, switched to Turnstile.
        $c->db->upsert('settings', ['key' => 'captcha_provider', 'locale' => null, 'value' => 'turnstile'], ['key', 'locale_scope']);
        [$h, $html] = $c->request('/admin/login'); contains('data-sitekey="0x4AAAAAAAsitekey" data-theme="auto"', $html); contains('<script src="https://challenges.cloudflare.com/turnstile/v0/api.js"', $html);
        contains("script-src 'self' https://challenges.cloudflare.com; frame-src https://challenges.cloudflare.com;", $h);
        $token = $c->token();
        [$h] = $c->request('/admin/login', 'POST', ['csrf_token' => $token, 'username' => 'editor', 'password' => 'a long password']); contains('Location: /admin/login', $h);
        [, $html] = $c->request('/admin/login'); contains('Complete the security check and try again.', $html);
        same(0, (int) $c->db->one('SELECT COUNT(*) AS n FROM login_attempts')['n']);
        [$h] = $c->request('/admin/passkey/login/options', 'POST', [], true, $token); contains('200 OK', $h);
    } finally { $c->close(); }
});
test('Admin HTTP: keep me signed in survives a lost session until sign-out or a new password', function () {
    $c = new AdminHttpClient();
    try {
        [, $html] = $c->request('/admin/login'); contains('name="remember" value="1"', $html);
        // Without the box there is no remember cookie.
        $token = $c->token(); [$h] = $c->request('/admin/login', 'POST', ['csrf_token' => $token, 'username' => 'editor', 'password' => 'a long password']);
        ok(!str_contains($h, 'meteor_admin_test_remember'));
        $c->cookie = ''; $token = $c->token();
        [$h] = $c->request('/admin/login', 'POST', ['csrf_token' => $token, 'username' => 'editor', 'password' => 'a long password', 'remember' => '1']);
        ok((bool) preg_match('/Set-Cookie: (meteor_admin_test_remember=([0-9a-f]{24})%3A[0-9a-f]{64}); expires=[^;]+; Max-Age=2592000; path=\/; secure; HttpOnly; SameSite=Lax/', $h, $m), $h);
        [$remember, $selector] = [$m[1], $m[2]];
        $row = $c->db->one('SELECT * FROM remember_tokens'); same($selector, $row['selector']); ok(!str_contains($remember, $row['validator_hash']));
        // The session is gone (expired or cleaned up): the cookie signs in again, also from a public page.
        $c->cookie = $remember; [$h] = $c->request('/admin/posts'); contains('200 OK', $h);
        $c->cookie = $remember; [$h] = $c->request('/ja/'); contains('200 OK', $h); contains('Cache-Control: private, no-store', $h);
        $c->cookie = 'meteor_admin_test_remember=' . $selector . '%3A' . str_repeat('0', 64); [$h] = $c->request('/admin/posts'); contains('Location: /admin/login', $h);
        same(0, (int) $c->db->one('SELECT COUNT(*) AS n FROM remember_tokens')['n'], 'A wrong secret deletes the token');
        // A new password forgets other browsers but keeps this one.
        $c->cookie = ''; $token = $c->token();
        [$h] = $c->request('/admin/login', 'POST', ['csrf_token' => $token, 'username' => 'editor', 'password' => 'a long password', 'remember' => '1']);
        preg_match('/Set-Cookie: (meteor_admin_test_remember=[^;]+)/', $h, $m); $first = $m[1];
        $c->cookie = ''; $token = $c->token();
        [$h] = $c->request('/admin/login', 'POST', ['csrf_token' => $token, 'username' => 'editor', 'password' => 'a long password', 'remember' => '1']);
        preg_match('/Set-Cookie: (meteor_admin_test_remember=[^;]+)/', $h, $m); $second = $m[1];
        $session = $c->cookie; $c->cookie = $session . '; ' . $second; $token = $c->token('/admin/security');
        [$h] = $c->request('/admin/security', 'POST', ['csrf_token' => $token, 'action' => 'password', 'current_password' => 'a long password', 'new_password' => 'another long password', 'new_password_confirm' => 'another long password']);
        ok((bool) preg_match('/Set-Cookie: (meteor_admin_test_remember=[0-9a-f]{24}%3A[^;]+); expires/', $h, $m), 'Reissued'); $third = $m[1];
        same(1, (int) $c->db->one('SELECT COUNT(*) AS n FROM remember_tokens')['n']);
        $c->cookie = $first; [$h] = $c->request('/admin/posts'); contains('Location: /admin/login', $h);
        $c->cookie = $third; [$h] = $c->request('/admin/posts'); contains('200 OK', $h);
        // Signing out forgets this browser too.
        $c->cookie .= '; ' . $third; $token = $c->token('/admin/posts');
        [$h] = $c->request('/admin/logout', 'POST', ['csrf_token' => $token]); contains('meteor_admin_test_remember=deleted', $h);
        same(0, (int) $c->db->one('SELECT COUNT(*) AS n FROM remember_tokens')['n']);
        $c->cookie = $third; [$h] = $c->request('/admin/posts'); contains('Location: /admin/login', $h);
    } finally { $c->close(); }
});
test('Admin HTTP: signed-in devices, signing out one or every other device, and bin/sign-out.php', function () {
    $c = new AdminHttpClient();
    try {
        // Sign in three times: two remembered browsers (A, B) and one plain session (C).
        $signIn = function (bool $remember, string $agent) use ($c): array {
            $c->cookie = ''; $token = $c->token();
            putenv('HTTP_USER_AGENT=' . $agent);
            [$h] = $c->request('/admin/login', 'POST', ['csrf_token' => $token, 'username' => 'editor', 'password' => 'a long password'] + ($remember ? ['remember' => '1'] : []));
            preg_match('/Set-Cookie: (meteor_admin_test_remember=[^;]+)/', $h, $m);
            return [$c->cookie, $m[1] ?? ''];
        };
        [$sessionA, $rememberA] = $signIn(true, 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1');
        [$sessionB, $rememberB] = $signIn(true, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36 Edg/130.0.0.0');
        [$sessionC] = $signIn(false, 'curl/8');
        ok($rememberA !== '' && $rememberB !== '');
        $c->cookie = $sessionB . '; ' . $rememberB; $token = $c->token('/admin/security');
        [, $html] = $c->request('/admin/security'); contains('Signed-in devices', $html); contains('Safari on iPhone', $html); contains('Edge on Windows</span> <span class="font-mono text-xs font-normal text-orange-800 dark:text-orange-300">this device', $html); same(1, substr_count($html, 'this device')); same(1, substr_count($html, 'value="sign_out_device"'));
        contains('php bin/sign-out.php', $html);
        // Signing out A from B ends A's open session at once, not only its cookie.
        $idA = (int) $c->db->one('SELECT id FROM remember_tokens ORDER BY id LIMIT 1')['id'];
        [$h] = $c->request('/admin/security', 'POST', ['csrf_token' => $token, 'action' => 'sign_out_device', 'id' => $idA]); contains('303', $h);
        [, $html] = $c->request('/admin/security'); contains('That device was signed out.', $html);
        $c->cookie = $sessionA; [$h] = $c->request('/admin/posts'); contains('Location: /admin/login', $h);
        $c->cookie = $rememberA; [$h] = $c->request('/admin/posts'); contains('Location: /admin/login', $h);
        $c->cookie = $sessionC; [$h] = $c->request('/admin/posts'); contains('200 OK', $h);
        // "Sign out everywhere else" also ends C, which never ticked the box; B stays signed in and remembered.
        $c->cookie = $sessionB . '; ' . $rememberB; $token = $c->token('/admin/security');
        [$h] = $c->request('/admin/security', 'POST', ['csrf_token' => $token, 'action' => 'sign_out_others']);
        ok((bool) preg_match('/Set-Cookie: (meteor_admin_test_remember=[0-9a-f]{24}%3A[^;]+); expires/', $h, $m), 'B is remembered again'); $rememberB = $m[1];
        same(1, (int) $c->db->one('SELECT COUNT(*) AS n FROM remember_tokens')['n']);
        $sessionB = $c->cookie;
        $c->cookie = $sessionC; [$h] = $c->request('/admin/posts'); contains('Location: /admin/login', $h);
        $c->cookie = $sessionB; [$h] = $c->request('/admin/posts'); contains('200 OK', $h);
        // From the server shell: every device, this one included.
        $env = array_merge(getenv(), ['DB_DRIVER' => 'sqlite', 'DB_PATH' => $c->directory . '/db.sqlite']);
        $run = function (array $args) use ($c, $env): array {
            $process = proc_open([PHP_BINARY, 'bin/sign-out.php', ...$args], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $c->root, $env);
            $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
            return [proc_close($process), $out];
        };
        [$code, $out] = $run(['nobody']); same(1, $code); contains('No admin named "nobody".', $out);
        [$code, $out] = $run([]); same(0, $code); contains('1 admin was signed out on every device.', $out);
        same(0, (int) $c->db->one('SELECT COUNT(*) AS n FROM remember_tokens')['n']);
        $c->cookie = $sessionB; [$h] = $c->request('/admin/posts'); contains('Location: /admin/login', $h);
        $c->cookie = $rememberB; [$h] = $c->request('/admin/posts'); contains('Location: /admin/login', $h);
        [$code, $out] = $run(['EDITOR']); same(0, $code); contains('"editor" was signed out', $out);
    } finally { putenv('HTTP_USER_AGENT'); $c->close(); }
});
test('Admin HTTP: author tab toggles the card at the end of posts', function () {
    $c = new AdminHttpClient();
    try {
        $token = $c->token(); $c->request('/admin/login', 'POST', ['csrf_token' => $token, 'username' => 'editor', 'password' => 'a long password']);
        [, $html] = $c->request('/admin/author');
        contains('name="post_author_card" value="0"', $html); ok(!preg_match('~name="post_author_card" value="1"[^>]*checked~', $html), 'Off by default');
        $token = $c->token('/admin/author');
        $c->request('/admin/author', 'POST', ['csrf_token' => $token, 'text' => ['author_name' => ['en' => 'Ada']], 'author_links' => '', 'post_author_card' => '1']);
        same('1', $c->db->one("SELECT value FROM settings WHERE `key` = 'post_author_card'")['value']);
        [, $html] = $c->request('/admin/author'); ok((bool) preg_match('~name="post_author_card" value="1"[^>]*checked~', $html), 'Checked after save');
        $c->request('/admin/author', 'POST', ['csrf_token' => $token, 'author_links' => '', 'post_author_card' => '0']);
        same('0', $c->db->one("SELECT value FROM settings WHERE `key` = 'post_author_card'")['value']);
    } finally { $c->close(); }
});
