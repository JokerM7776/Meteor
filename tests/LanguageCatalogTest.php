<?php
use Core\{GitHubUpdates, I18n, LanguageCatalog, LanguagePack, ReleasePackage};
use App\Services\{LanguageDownloads, LanguagePacks, UiStrings};

function rawLanguageUrl(string $file): string { return 'https://raw.githubusercontent.com/JokerM7776/Meteor/main/lang/' . $file; }
function rawResponse(string $body, int $status = 200): array { return ['status' => $status, 'headers' => [], 'body' => $body]; }
function knownPacks(Core\Database $db): string { return $db->one("SELECT value FROM settings WHERE `key` = 'language_packs_known'")['value'] ?? ''; }

test('Language catalog: index lists valid files, keeps dates of unchanged files and drops bad entries', function () {
    $builtins = require dirname(__DIR__) . '/lang/en.php';
    $dir = languageDir(['ja.json' => packData('ja', ['posts' => '投稿', 'tags' => 'タグ']), 'ar.json' => packData('ar', ['posts' => 'مقالات'], ['direction' => 'rtl']), 'bad.json' => 'nope']);
    try {
        $first = LanguageCatalog::build($dir, $builtins, [], '2026-01-01T00:00:00Z');
        same(['ar', 'ja'], array_keys($first)); same(2, $first['ja']['texts']); same('rtl', $first['ar']['direction']);
        same(hash_file('sha256', $dir . '/ja.json'), $first['ja']['sha256']);
        file_put_contents($dir . '/index.json', LanguageCatalog::encode($first));
        same(['ar', 'ja'], array_keys(LanguagePack::all($builtins, $dir)));
        same($first, LanguageCatalog::parse((string) file_get_contents($dir . '/index.json'))); same($first, LanguageCatalog::bundled($dir));
        file_put_contents($dir . '/ja.json', json_encode(packData('ja', ['posts' => '記事'])));
        $second = LanguageCatalog::build($dir, $builtins, $first, '2026-02-01T00:00:00Z');
        same('2026-01-01T00:00:00Z', $second['ar']['updated']); same('2026-02-01T00:00:00Z', $second['ja']['updated']); same(1, $second['ja']['texts']);
    } finally { ReleasePackage::removeTree($dir); }
    foreach (['nope', '[]', json_encode(['meteor_language_index' => 2, 'languages' => []]), str_repeat(' ', LanguageCatalog::MAX_BYTES + 1)] as $bad) {
        throws(fn () => LanguageCatalog::parse($bad), RuntimeException::class);
    }
    $entry = ['name' => 'Japanese', 'native_name' => '日本語', 'direction' => 'ltr', 'texts' => 3, 'sha256' => str_repeat('a', 64), 'updated' => '2026-01-01T00:00:00Z'];
    $parsed = LanguageCatalog::parse(json_encode(['meteor_language_index' => 1, 'languages' => ['ja' => $entry, '../x' => $entry,
        'ko' => ['sha256' => 'x'] + $entry, 'fr' => ['direction' => 'up'] + $entry, 'de' => ['name' => ''] + $entry, 'it' => ['updated' => 'today'] + $entry]]));
    same(['ja'], array_keys($parsed));
    $repository = LanguageCatalog::bundled(); ok(isset($repository['ms'], $repository['zh-hans']));
    throws(fn () => (new GitHubUpdates(new FakeUpdateHttp([])))->language('../index.json', 100), InvalidArgumentException::class);
    throws(fn () => (new GitHubUpdates(new FakeUpdateHttp([])))->language('en.php', 100), InvalidArgumentException::class);
});

test('Language catalog: downloads add a language as Off, update a renamed one in place and are verified', function () {
    $builtins = require dirname(__DIR__) . '/lang/en.php';
    $db = database(); locale($db, 'en'); locale($db, 'zh', 'live', 0, 1);
    $bundled = languageDir(['zh-hans.json' => packData('zh-hans', ['posts' => '文章', 'tags' => '标签'])]);
    $downloads = languageDir([]);
    $remote = languageDir(['ja.json' => packData('ja', ['posts' => '投稿'], ['name' => 'Japanese', 'native_name' => '日本語']), 'zh-hans.json' => packData('zh-hans', ['posts' => '博文', 'home' => '首页'])]);
    try {
        $fake = new FakeUpdateHttp([rawLanguageUrl('index.json') => rawResponse(LanguageCatalog::encode(LanguageCatalog::build($remote, $builtins)))]);
        foreach (['ja', 'zh-hans'] as $code) { $fake->responses[rawLanguageUrl($code . '.json')] = rawResponse((string) file_get_contents($remote . '/' . $code . '.json')); }
        $service = new LanguageDownloads($db, new GitHubUpdates($fake), $bundled, $downloads);
        same(null, $service->catalog()); same([], $service->rows());
        same(['ja', 'zh-hans'], array_keys($service->check())); ok(is_string($service->catalog()['checked_at']));
        $rows = $service->rows();
        same(['new', null], [$rows['ja']['state'], $rows['ja']['site_code']]); same(['update', 'zh'], [$rows['zh-hans']['state'], $rows['zh-hans']['site_code']]);
        contains('Added Japanese with 1 interface text', $service->install('ja'));
        same(['off', 'Japanese', '日本語'], array_values($db->one("SELECT status, name, native_name FROM locales WHERE code = 'ja'")));
        contains('Updated', $service->install('zh-hans'));
        same(null, $db->one("SELECT code FROM locales WHERE code = 'zh-hans'")); same('ja,zh-hans', knownPacks($db));
        same(['current', 'current'], array_values(array_column($service->rows(), 'state')));
        // A download wins, the bundled file fills its gaps, and site edits stay on top.
        $i18n = new I18n($db, $builtins, $bundled, $downloads);
        same('博文', $i18n->translateFor('zh', 'posts')); same('标签', $i18n->translateFor('zh', 'tags')); same('投稿', $i18n->translateFor('ja', 'posts'));
        same('博文', (new UiStrings($db, $bundled, $downloads))->own('zh')['posts']);
        $db->query("INSERT INTO ui_strings (locale, `key`, value) VALUES ('zh', 'posts', '全部文章')");
        same('全部文章', (new I18n($db, $builtins, $bundled, $downloads))->translateFor('zh', 'posts'));
        // A changed or foreign file, an unknown language and a failed request are refused.
        $fake->responses[rawLanguageUrl('ja.json')] = rawResponse(file_get_contents($remote . '/ja.json') . ' ');
        throws(fn () => $service->install('ja'), RuntimeException::class);
        $ko = json_encode(packData('ko', ['posts' => '글']));
        $index = json_decode(LanguageCatalog::encode(LanguageCatalog::build($remote, $builtins)), true);
        $index['languages']['ja']['sha256'] = hash('sha256', $ko);
        $fake->responses[rawLanguageUrl('index.json')] = rawResponse(json_encode($index)); $fake->responses[rawLanguageUrl('ja.json')] = rawResponse($ko);
        $service->check();
        try { $service->install('ja'); throw new LogicException('Expected an error'); } catch (RuntimeException $e) { contains('another language', $e->getMessage()); }
        same('投稿', (new I18n($db, $builtins, $bundled, $downloads))->translateFor('ja', 'posts'));
        throws(fn () => $service->install('xx'), InvalidArgumentException::class);
        $fake->responses[rawLanguageUrl('index.json')] = rawResponse('', 404);
        try { $service->check(); throw new LogicException('Expected an error'); } catch (RuntimeException $e) { contains('import a language file by hand', $e->getMessage()); }
        foreach ($fake->calls as $call) { ok(str_starts_with($call[0], rawLanguageUrl(''))); ok(!str_contains(implode("\n", $call[1]), 'Authorization')); }
        // After a Meteor update ships the same or newer text, the download makes way for it.
        file_put_contents($bundled . '/index.json', LanguageCatalog::encode(LanguageCatalog::build($bundled, $builtins, [], '2000-01-01T00:00:00Z')));
        same([], LanguagePacks::sync($db, $bundled, $downloads)); ok(is_file($downloads . '/zh-hans.json'));
        file_put_contents($bundled . '/index.json', LanguageCatalog::encode(LanguageCatalog::build($bundled, $builtins, [], '2999-01-01T00:00:00Z')));
        same([], LanguagePacks::sync($db, $bundled, $downloads));
        ok(!is_file($downloads . '/zh-hans.json')); ok(is_file($downloads . '/ja.json'));
        same(['ja'], array_keys(json_decode(file_get_contents($downloads . '/installed.json'), true)));
        same('文章', (new I18n($db, $builtins, $bundled, $downloads))->packText('zh', 'posts'));
    } finally { foreach ([$bundled, $downloads, $remote] as $dir) { ReleasePackage::removeTree($dir); } }
});

test('Language catalog: an unused zh becomes zh-hans, a zh in use keeps its code and is not added twice', function () {
    $migration = require dirname(__DIR__) . '/database/migrations/007_chinese_simplified_code.php';
    $empty = languageDir([]);
    try {
        $db = database(); locale($db, 'en'); locale($db, 'zh', 'off', 0, 3);
        $db->query("INSERT INTO ui_strings (locale, `key`, value) VALUES ('zh', 'posts', '博文')");
        $db->upsert('settings', ['key' => 'site_title', 'locale' => 'zh', 'value' => '流星'], ['key', 'locale_scope']);
        LanguagePacks::remember($db, ['ms', 'zh']);
        $migration($db);
        same(['en', 'zh-hans'], array_column($db->all('SELECT code FROM locales ORDER BY code'), 'code'));
        same(['off', 3], [$db->one("SELECT status FROM locales WHERE code = 'zh-hans'")['status'], (int) $db->one("SELECT sort_order FROM locales WHERE code = 'zh-hans'")['sort_order']]);
        same('博文', $db->one("SELECT value FROM ui_strings WHERE locale = 'zh-hans'")['value']);
        same('流星', $db->one("SELECT value FROM settings WHERE `key` = 'site_title' AND locale = 'zh-hans'")['value']);
        same('ms,zh,zh-hans', knownPacks($db)); same([], LanguagePacks::sync($db, null, $empty));
        // Live, or Off with content: the code and URLs stay; the zh-hans file is not added beside it.
        foreach (['live', 'off'] as $status) {
            $db = database(); locale($db, 'en'); locale($db, 'zh', $status, 0, 1);
            if ($status === 'off') {
                $db->query("INSERT INTO categories (slug) VALUES ('notes')");
                $db->query("INSERT INTO category_translations (category_id, locale, name) VALUES (1, 'zh', '笔记')");
            }
            $migration($db);
            same(['en', 'zh'], array_column($db->all('SELECT code FROM locales ORDER BY code'), 'code'));
            same(['ms'], LanguagePacks::sync($db, null, $empty)); same('ms,zh-hans', knownPacks($db));
            same('文章', (new I18n($db, require dirname(__DIR__) . '/lang/en.php', null, $empty))->translateFor('zh', 'posts'));
        }
        // A zh deleted earlier stays deleted under its new name.
        $db = database(); locale($db, 'en'); LanguagePacks::remember($db, ['ms', 'zh']);
        $migration($db); same([], LanguagePacks::sync($db, null, $empty));
        same(['en'], array_column($db->all('SELECT code FROM locales'), 'code'));
    } finally { ReleasePackage::removeTree($empty); }
});

test('Admin HTTP: Languages page lists the last catalog check with Add and Update buttons', function () {
    $c = new AdminHttpClient();
    try {
        $token = $c->token(); $c->request('/admin/login', 'POST', ['csrf_token' => $token, 'username' => 'editor', 'password' => 'a long password']);
        [, $html] = $c->request('/admin/languages');
        contains('id="catalog"', $html); contains('Meteor contacts GitHub only when you click', $html); contains('CONTRIBUTING.md#translating-meteor', $html);
        $entry = fn (string $name, string $native, string $hash) => ['name' => $name, 'native_name' => $native, 'direction' => 'ltr', 'texts' => 46, 'sha256' => $hash, 'updated' => '2026-10-01T00:00:00Z'];
        ReleasePackage::write($c->root . '/storage/lang/catalog.json', json_encode(['meteor_language_index' => 1, 'checked_at' => '2026-10-24T09:30:00Z', 'languages' => [
            'fr' => $entry('French', 'Français', str_repeat('a', 64)), 'ja' => $entry('Japanese', '日本語', str_repeat('b', 64)),
            'ms' => $entry('Malay', 'Bahasa Melayu', hash_file('sha256', $c->root . '/lang/ms.json'))]]));
        $c->db->query("INSERT INTO locales (code, name, native_name, status, sort_order) VALUES ('ms', 'Malay', 'Bahasa Melayu', 'off', 2)");
        [, $html] = $c->request('/admin/languages');
        contains('Checked 24 Oct 2026, 09:30', $html); contains('<span class="font-semibold" lang="fr" dir="ltr">Français</span>', $html);
        contains('Add<span class="sr-only"> French</span>', $html); contains('Update<span class="sr-only"> Japanese</span>', $html);
        ok(!str_contains($html, '<span class="sr-only"> Malay</span></button>')); contains('Up to date', $html); contains('Newer text available', $html);
        [$h] = $c->request('/admin/languages', 'POST', ['action' => 'download', 'code' => 'fr']); contains('403', $h);
    } finally { $c->close(); }
});
