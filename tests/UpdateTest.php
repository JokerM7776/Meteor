<?php
use Core\{ReleasePackage, Updater, Migrator, Installer, Version, GitHubUpdates, HttpClient};
function releaseFixture(string $directory, string $version = '0.2.0', array $files = [], array $metadata = []): string
{
    $files = ['VERSION' => $version . "\n", 'app/example.php' => '<?php // new release'] + $files;
    $hashes = array_map(fn ($bytes) => hash('sha256', $bytes), $files);
    $zipPath = $directory . '/release-' . bin2hex(random_bytes(5)) . '.zip';
    $zip = new ZipArchive(); same(true, $zip->open($zipPath, ZipArchive::CREATE));
    foreach ($files as $path => $bytes) { $zip->addFromString('meteor/' . $path, $bytes); }
    $zip->addFromString('meteor/manifest.json', json_encode($metadata + ['name' => 'meteor', 'version' => $version, 'min_php' => '8.2.0', 'built_at' => gmdate('c'), 'files' => $hashes]));
    $zip->close(); return $zipPath;
}
test('Migrator: ordered pending migrations, recorded history, failure transaction and idempotence', function () {
    $root = appFixture(); $db = database();
    try {
        $directory = $root . '/database/migrations';
        $runner = new Migrator($db, $directory);
        same(['001_published_at.php', '002_lowercase_usernames.php', '003_menus.php', '004_language_files_and_updates.php', '005_media.php', '006_page_links_and_site_text.php', '007_chinese_simplified_code.php', '008_announcements.php'], $runner->run()); same([], $runner->run());
        file_put_contents($directory . '/900_test.php', '<?php return static function (\Core\Database $db): void { $db->query("CREATE TABLE migration_probe (id INT)"); };');
        same(['900_test.php'], $runner->run());
        file_put_contents($directory . '/901_fail.php', '<?php return static function (\Core\Database $db): void { $db->query("INSERT INTO migration_probe VALUES (1)"); throw new \RuntimeException("forced"); };');
        throws(fn () => $runner->run());
        same([], $db->all('SELECT * FROM migration_probe'));
        same(9, count($db->all('SELECT * FROM schema_migrations')));
    } finally { ReleasePackage::removeTree($root); }
});
test('Migration 003: creates menu tables and converts the earlier header and footer settings', function () {
    $db = database();
    $db->query('DROP TABLE menu_item_labels'); $db->query('DROP TABLE menu_items');
    locale($db); locale($db, 'zh', 'live', 0, 1);
    $set = function ($key, $value, $locale = null) use ($db) { $db->upsert('settings', compact('key', 'value', 'locale'), ['key', 'locale_scope']); };
    $set('nav_show_tags', '0'); $set('nav_posts', 'Blog', 'en'); $set('nav_posts', '博客', 'zh'); $set('nav_about', 'Me', 'en');
    $set('header_links', "Projects | /en/projects\nBad | javascript:x\nGitHub | https://github.com/x", 'en');
    $set('header_links', "项目 | /en/projects", 'zh'); $set('footer_links', 'Mail | mailto:a@b.com', 'en'); $set('site_title', 'Kept', 'en');
    $migration = require dirname(__DIR__) . '/database/migrations/003_menus.php';
    $migration($db);
    $menus = new App\Repositories\Menus($db);
    $header = $menus->rows('header'); $kinds = array_column($header, null, 'kind');
    same(0, (int) $kinds['tags']['is_visible']); same(1, (int) $kinds['posts']['is_visible']);
    same(['en' => 'Blog', 'zh' => '博客'], $kinds['posts']['labels']);
    $links = array_values(array_filter($header, fn ($row) => $row['kind'] === 'link'));
    same(['/en/projects', 'https://github.com/x'], array_column($links, 'url')); same(['en' => 'Projects', 'zh' => '项目'], $links[0]['labels']);
    $footer = $menus->rows('footer'); same('Me', array_column($footer, null, 'kind')['about']['labels']['en']);
    same('mailto:a@b.com', end($footer)['url']);
    same(['site_title'], array_column($db->all('SELECT `key` FROM settings'), 'key'));
    $migration($db); same(count($header) + count($footer), (int) $db->one('SELECT COUNT(*) AS n FROM menu_items')['n']);
});
test('Installer: service writes private quoted env, lock, lowercase admin and marks migrations', function () {
    $root = appFixture(false);
    try {
        $installer = new Installer($root);
        $password = "literal # \"quoted\" \\ secret";
        $db = $installer->install(['driver' => 'sqlite', 'path' => $root . '/storage/meteor.sqlite', 'password' => $password], 'https://meteor.example', 'A <site>', 'ÉDITOR', 'a long password');
        same(true, $installer->installed()); same(0600, fileperms($root . '/.env') & 0777);
        same($password, Core\Env::load($root . '/.env')['DB_PASS']);
        same('éditor', $db->one('SELECT username FROM users')['username']);
        ok(password_verify('a long password', $db->one('SELECT password_hash FROM users')['password_hash']));
        same(['en' => 'live', 'ms' => 'off', 'zh-hans' => 'off'], array_column($db->all('SELECT code, status FROM locales ORDER BY code'), 'status', 'code'));
        same([], (new Migrator($db, $root . '/database/migrations'))->run());
        throws(fn () => $installer->install(['driver' => 'sqlite', 'path' => $root . '/storage/meteor.sqlite'], 'https://meteor.example', 'Title', 'admin', 'a long password'));
    } finally { ReleasePackage::removeTree($root); }
});
test('Installer HTTP: first-run redirect, requirements, CSRF, connection test and complete installation', function () {
    $root = appFixture(false);
    try {
        [$headers] = fixtureRequest($root, '/admin/updates'); contains('Location: /', $headers); ok(!str_contains($headers, 'Location: /admin'));
        [$headers] = fixtureRequest($root, '/install'); contains('Location: /', $headers);
        [$headers, $html] = fixtureRequest($root, '/'); contains('action="/"', $html);
        contains("script-src 'none'", $headers); ok(!str_contains($html, '<script')); contains('ext-zip', $html);
        preg_match('/Set-Cookie: ([^;]+)/', $headers, $cookie);
        preg_match('/name="csrf_token" value="([^"]+)"/', $html, $token);
        ok(isset($token[1], $cookie[1]));
        [$headers] = fixtureRequest($root, '/', 'POST', ['action' => 'install'], $cookie[1]); contains('403', $headers);
        ok(!preg_match('/name="(host|port)"/', $html));
        $values = ['csrf_token' => $token[1], 'action' => 'test', 'db_driver' => 'sqlite', 'db_path' => 'storage/meteor.sqlite', 'db_password' => 'do-not-echo'];
        [, $html] = fixtureRequest($root, '/', 'POST', $values, $cookie[1]); contains('Connection successful', $html); ok(!str_contains($html, 'do-not-echo'));
        $values = array_replace($values, ['action' => 'install', 'url' => 'https://meteor.example', 'title' => 'Test', 'username' => 'ADMIN', 'password' => 'long-password-here', 'confirm' => 'long-password-here']);
        [$headers] = fixtureRequest($root, '/', 'POST', $values, $cookie[1]); contains('Location: /admin/login', $headers);
        [$headers, $html] = fixtureRequest($root, '/'); ok(!str_contains($html, 'Install Meteor'));
        file_put_contents($root . '/storage/maintenance.flag', 'test');
        [$headers, $html] = fixtureRequest($root, '/en/'); contains('503', $headers); contains('Retry-After: 120', $headers); ok(!str_contains($html, '<script'));
        [$headers] = fixtureRequest($root, '/admin/updates'); contains('Location: /admin/login', $headers);
        [$headers, $html] = fixtureRequest($root, '/admin/login'); contains('Sign in', $html);
    } finally { ReleasePackage::removeTree($root); }
});
test('Installer from .env: first visit installs, removes the admin password, bad settings show the form', function () {
    $root = appFixture(false);
    try {
        file_put_contents($root . '/.env', "APP_URL=https://blog.example\nSITE_TITLE=\"My blog\"\nADMIN_USER=Owner\nADMIN_PASS=wrong\n");
        [$headers, $html] = fixtureRequest($root, '/'); contains('Install from .env failed', $html); contains('Install Meteor', $html);
        file_put_contents($root . '/.env', "APP_URL=https://blog.example\nSITE_TITLE=\"My blog\"\nADMIN_USER=Owner\nADMIN_PASS=\"env long password\"\n");
        [$headers] = fixtureRequest($root, '/'); contains('Location: /admin/login', $headers);
        $env = Core\Env::load($root . '/.env');
        ok(!isset($env['ADMIN_USER']) && !isset($env['ADMIN_PASS'])); same('https://blog.example', $env['APP_URL']); same('blog.example', $env['WEBAUTHN_RP_ID']);
        same(true, (new Installer($root))->installed());
        $db = new Core\Database(['driver' => 'sqlite', 'path' => $root . '/storage/meteor.sqlite']);
        ok(password_verify('env long password', $db->one('SELECT password_hash FROM users WHERE username = ?', ['owner'])['password_hash']));
        same('My blog', $db->one("SELECT value FROM settings WHERE \"key\" = 'site_title'")['value']);
    } finally { ReleasePackage::removeTree($root); }
});
test('Packaging: CLI zip structure, manifest hashes, runtime and secret exclusions', function () {
    $root = appFixture();
    try {
        file_put_contents($root . '/public/uploads/private.txt', 'upload');
        file_put_contents($root . '/storage/private.txt', 'private');
        file_put_contents($root . '/config/.env.private', 'secret'); file_put_contents($root . '/app/.DS_Store', 'finder');
        $process = proc_open([PHP_BINARY, $root . '/bin/package.php'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        fclose($pipes[0]); $out = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); same(0, proc_close($process)); same('', $error);
        $path = trim($out); $zip = new ZipArchive(); same(true, $zip->open($path));
        $manifest = json_decode($zip->getFromName('meteor/manifest.json'), true);
        foreach ($manifest['files'] as $file => $hash) { same($hash, hash('sha256', $zip->getFromName('meteor/' . $file))); }
        foreach (['.env', 'storage/private.txt', 'public/uploads/private.txt', 'config/.env.private', 'tests/run.php', '.github/workflows/release.yml', 'dist/meteor-0.1.0.zip', 'app/.DS_Store'] as $file) { same(false, $zip->locateName('meteor/' . $file)); }
        foreach (['bootstrap.php', 'index.php', '.htaccess', 'public/.htaccess', 'public/index.php', 'views/setup.php', 'config/config.php', 'config/.env.example', 'public/assets/style.css', 'storage/.gitkeep', 'public/uploads/.gitkeep', 'lang/en.php', 'lang/zh-hans.json', 'lang/index.json', 'lang/ms.json', 'database/migrations/003_menus.php', 'database/migrations/004_language_files_and_updates.php'] as $file) { ok($zip->locateName('meteor/' . $file) !== false, $file); }
        for ($i = 0; $i < $zip->numFiles; $i++) { ok(str_starts_with($zip->getNameIndex($i), 'meteor/')); }
        $zip->close(); contains(hash_file('sha256', $path), file_get_contents($path . '.sha256'));
        // A packaged tree is also an installable application.
        $fresh = $root . '/fresh'; mkdir($fresh); $zip->open($path); $zip->extractTo($fresh); $zip->close();
        [, $html] = fixtureRequest($fresh . '/meteor', '/'); contains('Install Meteor', $html);
    } finally { ReleasePackage::removeTree($root); }
});
test('Unzipped folder as web root: rewrite into public/, setup help without rewrite or in a sub-folder', function () {
    $root = appFixture(false);
    try {
        // Root .htaccess maps /x to public/x, so SCRIPT_NAME carries the /public prefix.
        [, $html] = fixtureRequest($root, '/', 'GET', [], '', ['SCRIPT_NAME' => '/public/index.php']); contains('Install Meteor', $html);
        [$headers, $html] = fixtureRequest($root, '/meteor/', 'GET', [], '', ['SCRIPT_NAME' => '/meteor/public/index.php']);
        contains('Status: 503', $headers); contains('sub-folder (<code>/meteor</code>)', $html);
        [$headers, $html] = fixtureRequest($root, '/', 'GET', [], '', ['SCRIPT_FILENAME' => $root . '/index.php', 'SCRIPT_NAME' => '/index.php']);
        contains('Status: 503', $headers); contains("default-src 'none'", $headers); contains('AllowOverride All', $html); ok(!str_contains($html, '<script'));
        $htaccess = file_get_contents($root . '/.htaccess');
        contains('RewriteRule ^(.*)$ public/$1 [L]', $htaccess); contains('Require all denied', $htaccess);
        ok(ReleasePackage::managed('.htaccess') && ReleasePackage::managed('index.php') && !ReleasePackage::managed('.env'));
    } finally { ReleasePackage::removeTree($root); }
});
test('Updater: apply, removed files, migrations, protected data, backup and restore', function () {
    $root = appFixture(); $db = database();
    try {
        file_put_contents($root . '/app/example.php', 'old code');
        file_put_contents($root . '/app/obsolete.php', 'obsolete');
        $oldManifest = json_encode(['files' => ['app/example.php' => hash('sha256', 'old code'), 'app/obsolete.php' => hash('sha256', 'obsolete')]]);
        file_put_contents($root . '/manifest.json', $oldManifest);
        file_put_contents($root . '/.env', 'secret env'); file_put_contents($root . '/storage/private', 'private'); file_put_contents($root . '/public/uploads/photo', 'image');
        $zip = releaseFixture($root, '0.2.0', ['storage/.gitkeep' => '', 'public/uploads/.gitkeep' => '',
            'database/migrations/900_update.php' => '<?php return static function (\Core\Database $db): void { $db->query("CREATE TABLE update_probe (id INT)"); };']);
        $updater = new Updater($root, $db); $updater->apply($zip, false, hash_file('sha256', $zip));
        same('0.2.0', Version::current($root)); same('<?php // new release', file_get_contents($root . '/app/example.php')); ok(!file_exists($root . '/app/obsolete.php'));
        same([], $db->all('SELECT * FROM update_probe'));
        same('secret env', file_get_contents($root . '/.env')); same('private', file_get_contents($root . '/storage/private')); same('image', file_get_contents($root . '/public/uploads/photo'));
        ok(!file_exists($root . '/storage/maintenance.flag')); same([], glob($root . '/storage/updates/staging-*'));
        same(1, count($updater->backups())); $updater->restore($updater->backups()[0]);
        same('0.1.0', Version::current($root)); same('old code', file_get_contents($root . '/app/example.php')); same('obsolete', file_get_contents($root . '/app/obsolete.php')); same($oldManifest, file_get_contents($root . '/manifest.json'));
        ok(!is_file($root . '/database/migrations/900_update.php'));
        contains('success', file_get_contents($root . '/storage/logs/updates.log'));
        for ($i = 0; $i < 6; $i++) { $updater->apply(releaseFixture($root, '0.2.' . $i)); }
        same(5, count($updater->backups()));
    } finally { ReleasePackage::removeTree($root); }
});
test('Updater: forced migration failure restores replaced and removed files and removes new files', function () {
    $root = appFixture(); $db = database();
    try {
        file_put_contents($root . '/app/example.php', 'before');
        file_put_contents($root . '/app/obsolete.php', 'preserve');
        file_put_contents($root . '/manifest.json', json_encode(['files' => ['app/obsolete.php' => hash('sha256', 'preserve')]]));
        $zip = releaseFixture($root, '0.2.0', ['app/new.php' => 'new',
            'database/migrations/003_failure.php' => '<?php return static function (\Core\Database $db): void { throw new \RuntimeException("forced migration failure"); };']);
        throws(fn () => (new Updater($root, $db))->apply($zip));
        same('before', file_get_contents($root . '/app/example.php')); same('preserve', file_get_contents($root . '/app/obsolete.php'));
        same('0.1.0', Version::current($root)); ok(!is_file($root . '/app/new.php')); ok(!is_file($root . '/database/migrations/003_failure.php'));
        ok(!is_file($root . '/storage/maintenance.flag')); same([], glob($root . '/storage/updates/staging-*'));
        contains('forced migration failure', file_get_contents($root . '/storage/logs/updates.log'));
    } finally { ReleasePackage::removeTree($root); }
});
test('Updater: rejects traversal, absolute paths, symlinks, unlisted files, protected paths and checksum mismatch', function () {
    $root = appFixture(); $db = database();
    try {
        $updater = new Updater($root, $db);
        foreach (['meteor/../escape', '/meteor/absolute', 'meteor/evil\\file', 'outside/file', 'meteor/unlisted.php'] as $entry) {
            $path = releaseFixture($root); $zip = new ZipArchive(); $zip->open($path); $zip->addFromString($entry, 'bad'); $zip->close();
            throws(fn () => $updater->apply($path));
        }
        $path = releaseFixture($root); $zip = new ZipArchive(); $zip->open($path); $zip->setExternalAttributesName('meteor/app/example.php', ZipArchive::OPSYS_UNIX, 0120777 << 16); $zip->close();
        throws(fn () => $updater->apply($path));
        foreach (['.env', 'storage/secret', 'public/uploads/file', 'public/UPLOADS/file', 'config/.ENV.private', 'config/.env.private'] as $entry) { throws(fn () => $updater->apply(releaseFixture($root, '0.2.0', [$entry => 'bad']))); }
        $path = releaseFixture($root); throws(fn () => $updater->apply($path, false, str_repeat('0', 64)));
        $zip->open($path); $zip->addFromString('meteor/app/example.php', 'tampered'); $zip->close(); throws(fn () => $updater->apply($path));
        symlink($root . '/storage/private', $root . '/app/link.php');
        throws(fn () => $updater->apply(releaseFixture($root, '0.2.0', ['app/link.php' => 'bad'])));
        same('0.1.0', Version::current($root)); same([], $updater->backups());
    } finally { ReleasePackage::removeTree($root); }
});
test('Updater: version gates, PHP requirement and entry-count limit', function () {
    $root = appFixture(); $db = database();
    try {
        $updater = new Updater($root, $db);
        $older = releaseFixture($root, '0.0.9'); throws(fn () => $updater->apply($older));
        throws(fn () => $updater->apply(releaseFixture($root, '0.2.0', [], ['min_php' => '99.0.0'])));
        $path = releaseFixture($root); $zip = new ZipArchive(); $zip->open($path);
        for ($i = 0; $i < 5000; $i++) { $zip->addEmptyDir('meteor/app/d' . $i); } $zip->close();
        throws(fn () => $updater->apply($path));
        $updater->apply($older, true); same('0.0.9', Version::current($root));
    } finally { ReleasePackage::removeTree($root); }
});
final class FakeUpdateHttp implements HttpClient
{
    public array $calls = [];
    public function __construct(public array $responses) {}
    public function get(string $url, array $headers, int $limit): array
    {
        $this->calls[] = [$url, $headers, $limit];
        return $this->responses[$url] ?? throw new RuntimeException('Unexpected request');
    }
}
test('GitHub: fake latest and downloads, mandatory hash, redirect allow-list and no credentials', function () {
    $root = appFixture();
    try {
        $bytes = file_get_contents(releaseFixture($root)); $hash = hash('sha256', $bytes);
        $latest = 'https://api.github.com/repos/owner/repo/releases/latest';
        $zipUrl = 'https://api.github.com/repos/owner/repo/releases/assets/1'; $shaUrl = 'https://api.github.com/repos/owner/repo/releases/assets/2';
        $object = 'https://objects.githubusercontent.com/release.zip';
        $response = fn ($body) => ['status' => 200, 'headers' => [], 'body' => $body];
        $fake = new FakeUpdateHttp([$latest => $response(json_encode(['tag_name' => 'v0.2.0', 'name' => '<release>', 'body' => '<script>notes</script>', 'assets' => [
            ['name' => 'meteor-0.2.0.zip', 'url' => $zipUrl], ['name' => 'meteor-0.2.0.zip.sha256', 'url' => $shaUrl]]])), $shaUrl => $response($hash . '  meteor-0.2.0.zip'),
            $zipUrl => ['status' => 302, 'headers' => ['location' => $object], 'body' => ''], $object => $response($bytes)]);
        $github = new GitHubUpdates($fake); $release = $github->latest('owner/repo'); same('0.2.0', $release['version']);
        same($hash, $github->download($release, $root . '/download.zip'));
        same($hash, hash_file('sha256', $root . '/download.zip'));
        foreach ($fake->calls as $call) { ok(!str_contains(implode("\n", $call[1]), 'Authorization')); }
        same('JokerM7776/Meteor', GitHubUpdates::REPOSITORY);
        throws(fn () => $github->latest(), RuntimeException::class);
        same('https://api.github.com/repos/JokerM7776/Meteor/releases/latest', end($fake->calls)[0]);
        foreach (['http://github.com/file', 'https://evil.example/file', 'https://github.com.evil.example/file', 'https://github.com@evil.example/file', 'https://github.com:444/file', 'https://api.github.com/\\evil'] as $url) {
            $fake->responses[$zipUrl]['headers']['location'] = $url;
            throws(fn () => $github->download($release, $root . '/download.zip'));
            ok(!in_array($url, array_column($fake->calls, 0), true));
        }
        $fake->responses[$shaUrl] = $response(str_repeat('0', 64)); $fake->responses[$zipUrl] = $response($bytes);
        throws(fn () => $github->download($release, $root . '/download.zip'));
        throws(fn () => $github->download(['version' => '0.2.0', 'assets' => []], $root . '/download.zip'));
        $cdn = 'https://release-assets.githubusercontent.com/github-production-release-asset/1/abc?sp=r&sig=x%2By%3D';
        $fake->responses[$shaUrl] = $response($hash); $fake->responses[$zipUrl] = ['status' => 302, 'headers' => ['location' => $cdn], 'body' => '']; $fake->responses[$cdn] = $response($bytes);
        same($hash, $github->download($release, $root . '/download.zip'));
        throws(fn () => GitHubUpdates::validateUrl('https://release-assets.githubusercontent.com.evil.example/file'));
    } finally { ReleasePackage::removeTree($root); }
});
test('Updater: complete generated release replaces a temporary app and executes its migration', function () {
    $source = appFixture(); $target = appFixture(); $db = database();
    try {
        file_put_contents($source . '/VERSION', "0.3.0\n");
        file_put_contents($source . '/database/migrations/003_release.php', '<?php return static function (\Core\Database $db): void { $db->query("CREATE TABLE release_probe (id INT)"); };');
        file_put_contents($target . '/app/old.php', 'remove me');
        file_put_contents($target . '/manifest.json', json_encode(['files' => ['app/old.php' => hash('sha256', 'remove me')]]));
        $zip = ReleasePackage::build($source, $source . '/dist');
        (new Updater($target, $db))->apply($zip);
        same('0.3.0', Version::current($target)); ok(!is_file($target . '/app/old.php')); same([], $db->all('SELECT * FROM release_probe'));
        $manifest = json_decode(file_get_contents($target . '/manifest.json'), true);
        foreach ($manifest['files'] as $path => $hash) { if (ReleasePackage::managed($path)) { same($hash, hash_file('sha256', $target . '/' . $path)); } }
    } finally { ReleasePackage::removeTree($source); ReleasePackage::removeTree($target); }
});
test('Updater: expanded size limit rejects compressed bombs before extraction', function () {
    $root = appFixture(); $db = database();
    try {
        $path = releaseFixture($root); $zip = new ZipArchive(); $zip->open($path);
        file_put_contents($root . '/bomb-source', str_repeat('x', 1024 * 1024));
        for ($i = 0; $i < 201; $i++) { $zip->addFile($root . '/bomb-source', 'meteor/app/bomb' . $i); }
        $zip->close(); throws(fn () => (new Updater($root, $db))->apply($path));
        same([], glob($root . '/storage/updates/staging-*')); same('0.1.0', Version::current($root));
    } finally { ReleasePackage::removeTree($root); }
});
test('Admin updates HTTP: official repository, no password for installing and maintenance access', function () {
    $c = new AdminHttpClient();
    try {
        $token = $c->token(); $c->request('/admin/login', 'POST', ['csrf_token' => $token, 'username' => 'editor', 'password' => 'a long password']);
        $token = $c->token('/admin/updates');
        [$h, $html] = $c->request('/admin/updates'); contains('0.1.0', $html);
        ok(!str_contains($html, 'github.com/JokerM7776/Meteor')); contains('value="check"', $html);
        ok(!str_contains($html, 'name="current_password"'), 'Installing an update must not ask for the password');
        ok(!str_contains($html, 'name="repository"')); ok(!str_contains($html, 'name="token"'));
        $c->request('/admin/updates', 'POST', ['csrf_token' => $token, 'action' => 'settings', 'repository' => 'owner/repo', 'token' => 'x']);
        same(null, $c->db->one("SELECT value FROM settings WHERE `key` IN ('update_repository', 'update_token')"));
        $c->request('/admin/updates', 'POST', ['csrf_token' => $token, 'action' => 'upload']);
        [, $html] = $c->request('/admin/updates'); contains('Upload a zip up to 50 MB', $html); ok(!str_contains($html, 'Current password is incorrect'));
        same([], glob($c->root . '/storage/backups/*'));
        file_put_contents($c->root . '/storage/maintenance.flag', 'test');
        [$h, $html] = $c->request('/admin/updates'); contains('200 OK', $h); contains('Upload update zip', $html);
    } finally { $c->close(); }
});
test('Updates view: release names and notes remain escaped plain text', function () {
    $view = new Core\View(dirname(__DIR__) . '/views');
    $html = $view->render('admin/updates', ['repository' => 'owner/repo', 'hasToken' => true, 'backups' => [],
        'release' => ['name' => '<b>Release</b>', 'version' => '0.2.0', 'date' => '2026-10-17', 'notes' => '<script>alert(1)</script>']]);
    contains('&lt;b&gt;Release&lt;/b&gt;', $html); contains('&lt;script&gt;alert(1)&lt;/script&gt;', $html); ok(!str_contains($html, '<script>'));
});
test('Admin updates HTTP: multipart upload without password, backup table and password-protected restore', function () {
    $c = new AdminHttpClient();
    try {
        $token = $c->token(); $c->request('/admin/login', 'POST', ['csrf_token' => $token, 'username' => 'editor', 'password' => 'a long password']);
        $token = $c->token('/admin/updates'); $zip = releaseFixture($c->root);
        $values = ['csrf_token' => $token, 'action' => 'upload', 'sha256' => hash_file('sha256', $zip)];
        [$h] = $c->request('/admin/updates', 'POST', $values, false, null, ['field' => 'update', 'filename' => 'release.zip', 'bytes' => file_get_contents($zip)]);
        contains('303 See Other', $h); same('0.2.0', Version::current($c->root));
        [, $html] = $c->request('/admin/updates'); contains('Update installed successfully', $html);
        $backup = (new Updater($c->root, $c->db))->backups()[0];
        [, $html] = $c->request('/admin/updates'); contains('<caption class="sr-only">Code backups</caption>', $html);
        contains(e($backup), $html); contains('href="/admin/updates/restore?backup=' . e(rawurlencode($backup)) . '"', $html); contains('<td class="font-mono text-xs">0.1.0</td>', $html);
        [$h, $html] = $c->request('/admin/updates/restore?backup=' . rawurlencode($backup)); contains('200 OK', $h); contains('name="current_password"', $html); contains('value="' . e($backup) . '"', $html);
        [$h] = $c->request('/admin/updates/restore?backup=meteor-missing.zip'); contains('404 Not Found', $h);
        $values = ['csrf_token' => $token, 'action' => 'restore', 'backup' => $backup];
        $c->request('/admin/updates', 'POST', $values); same('0.2.0', Version::current($c->root));
        $values['current_password'] = 'wrong';
        $c->request('/admin/updates', 'POST', $values); same('0.2.0', Version::current($c->root));
        [, $html] = $c->request('/admin/updates'); contains('Current password is incorrect', $html);
        $values['current_password'] = 'a long password';
        $c->request('/admin/updates', 'POST', $values); same('0.1.0', Version::current($c->root));
    } finally { $c->close(); }
});
