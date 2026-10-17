<?php
test('HTTP via CGI: CSP, content types, redirects, HEAD and cache validators', function () {
    $root = dirname(__DIR__);
    $path = $root . '/storage/test-http-' . bin2hex(random_bytes(6)) . '.sqlite';
    $db = new Core\Database(['driver' => 'sqlite', 'path' => $path]);
    foreach (schemaStatements('sqlite') as $statement) { $db->query($statement); }
    (new App\Services\DemoSeeder($db))->run(true);
    $db->upsert('settings', ['key' => 'custom_css', 'locale' => null, 'value' => 'body { color: red; }'], ['key', 'locale_scope']);
    $sessionPath = $root . '/storage/test-session-' . bin2hex(random_bytes(6));
    mkdir($sessionPath, 0700);
    $environment = array_merge(getenv(), ['DB_DRIVER' => 'sqlite', 'DB_PATH' => $path, 'APP_URL' => 'https://meteor.example',
        'REDIRECT_STATUS' => '200', 'SCRIPT_FILENAME' => $root . '/public/index.php', 'SERVER_PROTOCOL' => 'HTTP/1.1',
        'SESSION_NAME' => 'meteor_http_test', 'SERVER_NAME' => 'meteor.example', 'SERVER_PORT' => '443', 'HTTPS' => 'on']);
    try {
        $request = static function (string $path, string $method = 'GET', string $etag = '', string $cookie = '') use ($root, $environment, $sessionPath): array {
            $env = array_merge($environment, ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $path,
                'QUERY_STRING' => (string) parse_url($path, PHP_URL_QUERY), 'HTTP_IF_NONE_MATCH' => $etag, 'HTTP_COOKIE' => $cookie]);
            $process = proc_open([dirname(PHP_BINARY) . '/php-cgi', '-d', 'session.save_path=' . $sessionPath],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, $env);
            fclose($pipes[0]);
            $response = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]); same(0, proc_close($process)); same('', $errors);
            [$headers, $body] = explode("\r\n\r\n", $response, 2);
            if (!str_contains($headers, 'Status:')) { $headers = "Status: 200 OK\r\n" . $headers; }
            return [$headers, $body];
        };
        [$headers, $body] = $request('/en/');
        contains('200 OK', $headers); contains("script-src 'none'", $headers);
        contains('text/html; charset=UTF-8', $headers); ok(!str_contains($body, '<script'));
        [$headers, $body] = $request('/en/posts', 'HEAD'); contains('200 OK', $headers); same('', $body);
        [$headers] = $request('/en'); contains('301 Moved Permanently', $headers); contains('Location: /en/', $headers);
        [$headers] = $request('/en/posts/?page=2'); contains('Location: /en/posts?page=2', $headers);
        [$headers] = $request('/en/rss.xml'); contains('application/rss+xml; charset=UTF-8', $headers);
        [$headers] = $request('/sitemap.xml'); contains('application/xml; charset=UTF-8', $headers);
        [$headers, $body] = $request('/zh/missing'); contains('404 Not Found', $headers); contains('页面不存在', $body);
        [$headers] = $request('/ms/'); contains('404 Not Found', $headers);
        $db->query('INSERT INTO users (username, password_hash) VALUES (?, ?)', ['http-admin', 'unused-test-hash']);
        $sessionId = bin2hex(random_bytes(16));
        file_put_contents($sessionPath . '/sess_' . $sessionId, 'user_id|i:1;session_version|i:1;');
        $cookie = 'meteor_http_test=' . $sessionId;
        [$headers, $body] = $request('/ms/', 'GET', '', $cookie);
        contains('200 OK', $headers); contains('X-Robots-Tag: noindex, nofollow', $headers);
        contains('Cache-Control: private, no-store', $headers); contains('<html lang="ms"', $body);
        $db->query('UPDATE users SET session_version = ? WHERE id = ?', [2, 1]);
        [$headers] = $request('/ms/', 'GET', '', $cookie); contains('404 Not Found', $headers);
        [$headers, $body] = $request('/custom.css'); contains('text/css; charset=UTF-8', $headers); contains('Cache-Control: public, max-age=300', $headers);
        same('body { color: red; }', $body);
        preg_match('/ETag: ([^\r]+)/', $headers, $match);
        [$headers, $body] = $request('/custom.css', 'GET', $match[1]);
        contains('304 Not Modified', $headers); same('', $body);
    } finally {
        foreach (glob($sessionPath . '/*') as $file) { unlink($file); }
        rmdir($sessionPath);
        unset($db);
        foreach ([$path, $path . '-wal', $path . '-shm'] as $file) { if (is_file($file)) { unlink($file); } }
    }
});
