<?php
function appFixture(bool $installed = true): string
{
    $source = dirname(__DIR__);
    $root = $source . '/storage/test-app-' . bin2hex(random_bytes(8));
    mkdir($root, 0700, true);
    foreach (['app', 'bin', 'config', 'core', 'database', 'lang', 'public', 'src', 'views'] as $directory) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source . '/' . $directory, FilesystemIterator::SKIP_DOTS)) as $file) {
            $path = substr($file->getPathname(), strlen($source) + 1);
            if ($file->isFile() && Core\ReleasePackage::managed($path)) { Core\ReleasePackage::write($root . '/' . $path, file_get_contents($file->getPathname())); }
        }
    }
    foreach (['bootstrap.php', 'index.php', '.htaccess', 'VERSION', 'README.md'] as $path) { copy($source . '/' . $path, $root . '/' . $path); }
    file_put_contents($root . '/VERSION', "0.1.0\n");
    mkdir($root . '/storage', 0700); mkdir($root . '/public/uploads', 0755, true);
    if ($installed) { file_put_contents($root . '/.env', ''); file_put_contents($root . '/storage/installed.lock', 'test'); }
    return $root;
}
function fixtureRequest(string $root, string $uri, string $method = 'GET', array $data = [], string $cookie = '', array $server = []): array
{
    $body = http_build_query($data);
    $environment = array_merge(getenv(), ['DB_DRIVER' => 'sqlite', 'DB_PATH' => $root . '/storage/meteor.sqlite',
        'REDIRECT_STATUS' => '200', 'SCRIPT_FILENAME' => $root . '/public/index.php', 'SERVER_PROTOCOL' => 'HTTP/1.1',
        'REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri, 'QUERY_STRING' => '', 'HTTP_HOST' => 'meteor.example',
        'HTTP_COOKIE' => $cookie, 'CONTENT_TYPE' => 'application/x-www-form-urlencoded', 'CONTENT_LENGTH' => (string) strlen($body)], $server);
    $process = proc_open([dirname(PHP_BINARY) . '/php-cgi', '-d', 'session.save_path=' . $root . '/storage'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, $environment);
    fwrite($pipes[0], $body); fclose($pipes[0]); $response = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); same(0, proc_close($process)); same('', $error);
    return explode("\r\n\r\n", $response, 2);
}
