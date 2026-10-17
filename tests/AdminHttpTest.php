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
        ok(!str_contains($headers, '503'), $html);
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
        foreach (['/admin', '/admin/posts', '/admin/pages', '/admin/editor', '/admin/taxonomy', '/admin/languages', '/admin/settings', '/admin/security', '/admin/updates', '/admin/logout', '/admin/security/passkeys/options', '/admin/security/passkeys', '/admin/posts/1/delete', '/admin/unknown'] as $path) {
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
        $c->request('/admin/settings?locale=en', 'POST', ['csrf_token' => $token, 'locale' => 'en', 'site_title' => 'My HTTP Site', 'copyright' => 'Copyright test', 'strings' => ['read_more' => 'Read it'], 'custom_css' => 'body {color:red}']);
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
