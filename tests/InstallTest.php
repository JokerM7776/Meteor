<?php
test('Installer: SQLite WAL, English-only seed, password hash and safe rerun', function () {
    $root = appFixture(false);
    $path = $root . '/storage/test-install-' . bin2hex(random_bytes(6)) . '.sqlite';
    $environment = array_merge(getenv(), ['DB_DRIVER' => 'sqlite', 'DB_PATH' => $path,
        'ADMIN_USER' => 'test-admin', 'ADMIN_PASS' => 'a-long-test-password']);
    $install = static function () use ($root, $environment): array {
        $process = proc_open([PHP_BINARY, $root . '/bin/install.php'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, $environment);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        return [proc_close($process), $output];
    };
    try {
        [$status, $output] = $install(); same(0, $status); contains('Meteor installed', $output);
        $db = new Core\Database(['driver' => 'sqlite', 'path' => $path]);
        same('wal', $db->one('PRAGMA journal_mode')['journal_mode']);
        same(['en'], array_column($db->all('SELECT * FROM locales'), 'code'));
        $user = $db->one('SELECT * FROM users');
        same('test-admin', $user['username']); ok(password_verify($environment['ADMIN_PASS'], $user['password_hash']));
        [$status, $output] = $install(); same(1, $status); contains('not empty', $output);
        same(1, count($db->all('SELECT * FROM users')));
        unset($db);
    } finally {
        Core\ReleasePackage::removeTree($root);
        foreach ([$path, $path . '-wal', $path . '-shm'] as $file) { if (is_file($file)) { unlink($file); } }
    }
});
