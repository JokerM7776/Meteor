<?php
use Core\{ThemeCatalog, GitHubUpdates};
use App\Services\{ThemeDownloads, ThemeCatalogPackage, ThemeCatalogTools};

function themeEntry(array $extra = []): array
{
    return $extra + ['name' => 'Night Owl', 'description' => 'A quiet theme.', 'author' => 'Meteor Team', 'repo' => 'meteor-team/night-owl',
        'license' => 'MIT', 'version' => '1.0.0', 'api' => 1, 'requires' => '0.6.0',
        'zip' => 'https://github.com/meteor-team/night-owl/releases/download/1.0.0/night-owl.zip',
        'sha256' => str_repeat('a', 64), 'size' => 100, 'updated' => '2026-10-29T08:00:00Z'];
}
function themeIndexUrl(): string { return 'https://raw.githubusercontent.com/' . GitHubUpdates::REPOSITORY . '/main/themes/index.json'; }
function catalogZip(string $root, array $manifest = [], string $css = 'body{}'): string
{
    $json = themeJson('night-owl', $manifest + ['requires' => '0.6.0', 'license' => 'MIT', 'author' => 'Meteor Team']);
    return authorZip($root . '/catalog.zip', ['theme.json' => $json, 'theme.css' => $css]);
}
function offerTheme(FakeUpdateHttp $http, string $file, array $extra = []): array
{
    $entry = themeEntry($extra + ['size' => filesize($file), 'sha256' => hash_file('sha256', $file)]);
    $http->responses[themeIndexUrl()] = rawResponse(ThemeCatalog::encode(['night-owl' => $entry]));
    $http->responses[$entry['zip']] = rawResponse(file_get_contents($file));
    return $entry;
}

test('Theme catalog: strict fields, URLs, limits and sorted forward-compatible round trip', function () {
    $entry = themeEntry();
    $parsed = ThemeCatalog::parse(ThemeCatalog::encode(['z' => $entry, 'a' => $entry + ['future' => ['ok' => true]]]));
    same(['a', 'z'], array_keys($parsed)); ok(!isset($parsed['a']['future']));
    same($parsed, ThemeCatalog::parse(ThemeCatalog::encode($parsed))); ok(str_ends_with(ThemeCatalog::encode($parsed), "\n"));
    $invalid = ['name' => ['', str_repeat('x', 101), "x\n"], 'description' => [str_repeat('x', 501), "x\0"], 'author' => [[], "x\t"],
        'repo' => ['-bad/repo', 'a/..', 'a/b/c', 'a--b/repo'], 'license' => ['', str_repeat('x', 65)],
        'version' => ['1.0', 'v1.0.0', '1.0.0-beta'], 'api' => ['1', 1.1], 'requires' => ['1', 'v0.6'],
        'sha256' => [str_repeat('A', 64), 'abc'], 'size' => [0, App\Services\ThemePackage::LIMIT + 1, '100'],
        'updated' => ['today', '2026-02-30T08:00:00Z', '2026-10-29T08:00:00+01:00'],
        'zip' => ['https://github.com/other/repo/releases/download/1/a.zip', str_replace('https:', 'http:', $entry['zip']),
            $entry['zip'] . '?x=1', $entry['zip'] . '#x', str_replace('/1.0.0/', '/../', $entry['zip']),
            str_replace('/1.0.0/', '/a%2fb/', $entry['zip']), str_replace('/1.0.0/', '/%2e%2e/', $entry['zip']),
            str_replace('/1.0.0/', '/%252f/', $entry['zip']), str_replace('/1.0.0/', '/a%5cb/', $entry['zip'])]];
    foreach ($invalid as $field => $values) {
        foreach ($values as $value) { authorError(fn () => ThemeCatalog::parse(ThemeCatalog::encode(['night-owl' => [$field => $value] + $entry])), 'night-owl: invalid ' . $field); }
    }
    authorError(fn () => ThemeCatalog::parse(ThemeCatalog::encode(['BAD' => $entry])), 'BAD: invalid slug');
    foreach (['{}', '[]', '{"meteor_theme_index":1,"themes":[]}', '{"meteor_theme_index":2,"themes":{}}', str_repeat(' ', ThemeCatalog::MAX_BYTES + 1), ThemeCatalog::encode(array_fill_keys(range(1, 501), $entry))] as $bad) { throws(fn () => ThemeCatalog::parse($bad)); }
    // The shipped catalog lists the official starter at the same version Meteor bundles.
    $shipped = ThemeCatalog::parse(file_get_contents(dirname(__DIR__) . '/themes/index.json'));
    $bundled = json_decode(file_get_contents(dirname(__DIR__) . '/' . App\Services\Themes::STARTER . '/theme.json'), true);
    same(App\Services\Themes::STARTER_REPO, $shipped['starter']['repo']); same($bundled['version'], $shipped['starter']['version']);
    same($bundled['requires'], $shipped['starter']['requires']);
    same(false, Core\ReleasePackage::managed('themes/index.json'));
});

test('Theme catalog: GitHub URL validation before requests and release redirects', function () {
    $entry = themeEntry(); $url = $entry['zip'];
    $http = new FakeUpdateHttp([themeIndexUrl() => rawResponse('{}'), $url => ['status' => 302, 'headers' => ['location' => 'https://release-assets.githubusercontent.com/asset?token=x'], 'body' => ''],
        'https://release-assets.githubusercontent.com/asset?token=x' => rawResponse('zip')]);
    $github = new GitHubUpdates($http);
    foreach (['http://github.com/a/b/releases/download/v1/a.zip', $url . '?x=1', 'https://evil.example/a.zip'] as $bad) { throws(fn () => $github->themeZip($bad, 100)); }
    same([], $http->calls); same('{}', $github->themeIndex(100)); same(themeIndexUrl(), $http->calls[0][0]); same('zip', $github->themeZip($url, 100));
});

test('Theme catalog: install states, provenance, updates, local protection and no downgrade', function () {
    themeSandbox(function ($root, $write) {
        $db = database(); $themes = new App\Services\Themes($db); $http = new FakeUpdateHttp([]);
        $service = new ThemeDownloads($db, new GitHubUpdates($http));
        same(null, $service->catalog()); same([], $service->rows()); throws(fn () => $service->install('night-owl')); same([], $http->calls);
        $file = catalogZip($root); offerTheme($http, $file); $service->check();
        ok(is_file($root . '/themes/.catalog.json')); same([], $themes->all()); same('new', $service->rows()['night-owl']['state']);
        $calls = count($http->calls); contains('Installed Night Owl 1.0.0', $service->install('night-owl')); same($calls + 1, count($http->calls));
        same('current', $service->rows()['night-owl']['state']); same('1.0.0', $service->installed()['night-owl']['version']);
        $db->upsert('settings', ['key' => 'theme.night-owl.note', 'locale' => null, 'value' => '"Keep"'], ['key', 'locale_scope']);
        $file = catalogZip($root, ['version' => '2.0.0'], 'updated{}'); offerTheme($http, $file, ['version' => '2.0.0']); $service->check();
        same('update', $service->rows()['night-owl']['state']); contains('Your settings are kept.', $service->install('night-owl'));
        same('updated{}', file_get_contents($root . '/themes/night-owl/theme.css')); same('"Keep"', $db->one('SELECT value FROM settings WHERE `key` = ?', ['theme.night-owl.note'])['value']);
        offerTheme($http, $file, ['version' => '1.0.0', 'sha256' => str_repeat('b', 64)]); $service->check();
        same('current', $service->rows()['night-owl']['state']); $calls = count($http->calls); $service->install('night-owl'); same($calls, count($http->calls));
        $themes->installZip($file, true); same([], $service->installed()); same('local', $service->rows()['night-owl']['state']);
        authorError(fn () => $service->install('night-owl'), 'already installed here'); same($calls, count($http->calls));
        $themes->delete('night-owl'); offerTheme($http, $file, ['version' => '2.0.0']); $service->check(); $service->install('night-owl');
        $themes->delete('night-owl'); same([], $service->installed());
        foreach ([['api' => 2], ['requires' => '99.0']] as $extra) { offerTheme($http, $file, $extra); $service->check(); same('incompatible', $service->rows()['night-owl']['state']); throws(fn () => $service->install('night-owl')); }
        file_put_contents($root . '/themes/.catalog.json', '{"meteor_theme_index":1,"themes":[],"checked_at":"2026-10-29T08:00:00Z"}'); same(null, $service->catalog());
        file_put_contents($root . '/themes/.catalog.json', 'broken'); same(null, $service->catalog());
        $http->responses[themeIndexUrl()] = rawResponse('', 500); authorError(fn () => $service->check(), 'upload a theme zip by hand');
    });
});

test('Theme catalog: rejected bytes and manifests install nothing and remove temporary files', function () {
    themeSandbox(function ($root) {
        $db = database(); $http = new FakeUpdateHttp([]); $service = new ThemeDownloads($db, new GitHubUpdates($http));
        $file = catalogZip($root);
        foreach ([['size' => filesize($file) - 1], ['size' => filesize($file) + 1], ['sha256' => str_repeat('b', 64)]] as $extra) {
            offerTheme($http, $file, $extra); $service->check(); authorError(fn () => $service->install('night-owl'), ThemeCatalogPackage::MISMATCH);
        }
        foreach ([['slug' => 'other'], ['version' => '9.0.0'], ['api' => 2], ['requires' => '0.5.0']] as $manifest) {
            $file = catalogZip($root, $manifest); offerTheme($http, $file); $service->check(); authorError(fn () => $service->install('night-owl'), 'does not match the catalog');
        }
        same([], (new App\Services\Themes($db))->all()); same([], $service->installed());
        foreach (['theme-catalog-*', 'theme-inspect-*', 'theme-entry-*'] as $pattern) { same([], glob(dirname(__DIR__) . '/storage/' . $pattern)); }
    });
});

function themeCommand(array $arguments, ?string $cwd = null): array
{
    $proc = proc_open([PHP_BINARY, ...$arguments], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd ?? dirname(__DIR__));
    $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    return [proc_close($proc), $out, $err];
}

test('Theme catalog: CLI local entry and empty/broken index without network', function () {
    themeSandbox(function ($root) {
        $file = catalogZip($root); $entry = themeEntry();
        [$status, $out, $err] = themeCommand(['bin/theme.php', 'entry', $entry['repo'], $entry['zip'], $file]);
        same(0, $status); same('', $err); $entries = json_decode($out, true); same(hash_file('sha256', $file), $entries['night-owl']['sha256']);
        same(['night-owl'], array_keys(ThemeCatalog::parse(ThemeCatalog::encode($entries))));
        same(0, themeCommand(['bin/theme.php', 'catalog', 'check'])[0]);
        $fixture = appFixture();
        try {
            mkdir($fixture . '/themes'); file_put_contents($fixture . '/themes/index.json', '{bad');
            same(1, themeCommand(['bin/theme.php', 'catalog', 'check'], $fixture)[0]);
        } finally { Core\ReleasePackage::removeTree($fixture); }
    });
});

test('Admin HTTP: theme catalog checks, installs, CSRF, empty state and chips in both tables', function () {
    $c = new AdminHttpClient();
    try {
        file_put_contents($c->root . '/VERSION', "0.7.0\n");
        // Replace transport only in this disposable application; every HTTP response stays offline.
        file_put_contents($c->root . '/core/CurlHttpClient.php', <<<'FAKE'
<?php
namespace Core;
final class CurlHttpClient implements HttpClient {
    public function get(string $url, array $headers, int $limit): array {
        $root = dirname(__DIR__) . '/storage/';
        $file = str_ends_with($url, '/themes/index.json') ? 'remote-index.json' : 'remote-theme.zip';
        return ['status' => 200, 'headers' => [], 'body' => file_get_contents($root . $file)];
    }
}
FAKE);
        $token = $c->token(); $c->request('/admin/login', 'POST', ['csrf_token' => $token, 'username' => 'editor', 'password' => 'a long password']);
        [, $html] = $c->request('/admin/themes'); contains('Get themes', $html); contains('Check for themes', $html); contains('CONTRIBUTING.md#publishing-a-theme', $html);
        ok(!str_contains($html, 'The catalog has no themes yet')); ok(!str_contains($html, '<caption class="sr-only">Theme catalog'));
        foreach (['catalog', 'catalog_install'] as $action) { [$h] = $c->request('/admin/themes', 'POST', ['action' => $action, 'slug' => 'night-owl']); contains('403', $h); }
        $token = $c->token('/admin/themes');
        $post = fn ($action) => $c->request('/admin/themes', 'POST', ['csrf_token' => $token, 'action' => $action, 'slug' => 'night-owl']);
        file_put_contents($c->root . '/storage/remote-index.json', ThemeCatalog::encode([]));
        [$h] = $post('catalog'); contains('Location: /admin/themes', $h);
        [, $html] = $c->request('/admin/themes'); contains('The catalog has no themes yet. Made one?', $html);
        $file = catalogZip($c->root . '/storage'); $entry = themeEntry(['size' => filesize($file), 'sha256' => hash_file('sha256', $file)]);
        copy($file, $c->root . '/storage/remote-theme.zip');
        $entries = ['night-owl' => $entry, 'local' => $entry, 'future' => ['requires' => '99.0'] + $entry];
        file_put_contents($c->root . '/storage/remote-index.json', ThemeCatalog::encode($entries));
        Core\ReleasePackage::write($c->root . '/storage/themes/local/theme.json', json_encode(themeJson('local')));
        $post('catalog');
        [, $html] = $c->request('/admin/themes'); contains('Name in use', $html); contains('Needs Meteor 99.0', $html); contains('Install<span class="sr-only"> Night Owl', $html);
        [$h] = $post('catalog_install'); contains('Location: /admin/themes', $h);
        [, $html] = $c->request('/admin/themes'); contains('Installed Night Owl 1.0.0', $html); contains('From the catalog', $html);
        $file = catalogZip($c->root . '/storage', ['version' => '2.0.0']); copy($file, $c->root . '/storage/remote-theme.zip');
        $entry = themeEntry(['version' => '2.0.0', 'size' => filesize($file), 'sha256' => hash_file('sha256', $file)]);
        file_put_contents($c->root . '/storage/remote-index.json', ThemeCatalog::encode(['night-owl' => $entry])); $post('catalog');
        [, $html] = $c->request('/admin/themes'); same(2, substr_count($html, 'Update available')); contains('Update<span class="sr-only"> Night Owl', $html);
        $post('catalog_install'); [, $html] = $c->request('/admin/themes'); contains('Updated Night Owl to 2.0.0. Your settings are kept.', $html);
    } finally { $c->close(); }
});

test('Theme catalog: the bundled starter counts as the official catalog theme', function () {
    themeSandbox(function ($root, $write) {
        $db = database(); $themes = new App\Services\Themes($db); $themes->installStarter();
        $version = $themes->find('starter')['version'];
        $starter = themeEntry(['name' => 'Starter', 'repo' => App\Services\Themes::STARTER_REPO, 'version' => $version,
            'zip' => 'https://github.com/' . App\Services\Themes::STARTER_REPO . '/releases/download/v' . $version . '/starter.zip']);
        $http = new FakeUpdateHttp([themeIndexUrl() => rawResponse(ThemeCatalog::encode(['starter' => $starter]))]);
        $downloads = new ThemeDownloads($db, new GitHubUpdates($http)); $downloads->check();
        same('current', $downloads->rows()['starter']['state']);
        $http->responses[themeIndexUrl()] = rawResponse(ThemeCatalog::encode(['starter' => ['version' => '9.9.9'] + $starter])); $downloads->check();
        same('update', $downloads->rows()['starter']['state']);
        $themes->deactivate(); $themes->delete('starter'); same([], $downloads->installed());
    });
});
