<?php
use Core\LanguagePack;
use App\Services\{LanguagePacks, UiStrings};

function languageDir(array $files): string {
    $dir = dirname(__DIR__) . '/storage/test-lang-' . bin2hex(random_bytes(6)); mkdir($dir, 0700, true);
    foreach ($files as $name => $data) { file_put_contents($dir . '/' . $name, is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE)); }
    return $dir;
}
function packData(string $code, array $strings, array $extra = []): array {
    return $extra + ['meteor_language_pack' => 1, 'code' => $code, 'name' => 'Test ' . $code, 'native_name' => 'Native ' . $code, 'direction' => 'ltr', 'strings' => $strings];
}
test('Language files: every built-in key is grouped once and bundled files are complete', function () {
    $builtins = require dirname(__DIR__) . '/lang/en.php';
    $grouped = array_merge(...array_values(UiStrings::GROUPS));
    same(count($grouped), count(array_unique($grouped))); same([], array_values(array_diff(array_keys($builtins), $grouped))); same([], array_values(array_diff($grouped, array_keys($builtins))));
    $packs = LanguagePack::all($builtins);
    ok(isset($packs['ms'], $packs['zh-hans']));
    // Contributed languages may be partial; the two maintained ones must cover every key.
    foreach ($packs as $code => $pack) {
        same(0, $pack['skipped'], $code);
        same($pack, LanguagePack::parse((string) file_get_contents(dirname(__DIR__) . '/lang/' . $code . '.json'), $builtins));
        if (in_array($code, ['ms', 'zh-hans'], true)) { same(array_keys($builtins), array_keys($pack['strings'])); }
    }
    same('简体中文', $packs['zh-hans']['native_name']); same('阅读约 {minutes} 分钟', $packs['zh-hans']['strings']['reading_minutes']);
});
test('Language files: parser rejects bad files and skips unknown keys and broken placeholders', function () {
    $builtins = require dirname(__DIR__) . '/lang/en.php';
    $parse = fn ($data) => LanguagePack::parse(is_string($data) ? $data : json_encode($data), $builtins);
    foreach (['not json', '[]', json_encode(array_replace(packData('ja', []), ['meteor_language_pack' => 2])), json_encode(['code' => 'ja', 'strings' => []])] as $bad) { throws(fn () => $parse($bad), InvalidArgumentException::class); }
    foreach ([['code' => '../x'], ['code' => 'JA'], ['name' => ''], ['native_name' => str_repeat('a', 192)], ['direction' => 'up'], ['strings' => 'x']] as $change) {
        throws(fn () => $parse(array_replace(packData('ja', []), $change)), InvalidArgumentException::class);
    }
    throws(fn () => $parse(str_repeat(' ', LanguagePack::MAX_BYTES + 1)), InvalidArgumentException::class);
    $pack = $parse(packData('ja', ['posts' => ' 投稿 ', 'unknown_key' => 'x', 'reading_minutes' => '読了', 'archive_description' => '{language} {extra}', 'tags' => ['x'], 'menu' => '', 'home' => "a\x01b"]));
    same(['posts' => '投稿'], $pack['strings']); same(6, $pack['skipped']);
    same('ja', $parse(packData('ja', ['reading_minutes' => '約{minutes}分']))['code']);
    same(['{a}', '{b}'], LanguagePack::placeholders('{b} and {a} and {b}'));
});
test('Language files: interface text layers site edits over the language file, then default language, then English', function () {
    $db = database(); locale($db, 'en'); locale($db, 'zh', 'live', 0, 1); locale($db, 'ja', 'live', 0, 2);
    $builtins = require dirname(__DIR__) . '/lang/en.php';
    $i18n = new Core\I18n($db, $builtins);
    same('文章', $i18n->translateFor('zh', 'posts')); same('Posts', $i18n->translateFor('ja', 'posts')); same('文章', $i18n->packText('zh', 'posts'));
    $db->query('INSERT INTO ui_strings (locale, `key`, value) VALUES (?, ?, ?)', ['zh', 'posts', '博文']);
    $db->query('INSERT INTO ui_strings (locale, `key`, value) VALUES (?, ?, ?)', ['en', 'tags', 'Topics']);
    $i18n = new Core\I18n($db, $builtins);
    same('博文', $i18n->translateFor('zh', 'posts')); same('Topics', $i18n->translateFor('ja', 'tags')); same('标签', $i18n->translateFor('zh', 'tags'));
    $i18n->setLocale('zh'); same('阅读约 3 分钟', $i18n->translate('reading_minutes', ['minutes' => 3]));
    $dir = languageDir(['ja.json' => packData('ja', ['posts' => '投稿'])]);
    try { same('投稿', (new Core\I18n($db, $builtins, $dir))->translateFor('ja', 'posts')); } finally { Core\ReleasePackage::removeTree($dir); }
});
test('Language files: saving interface text keeps only real changes and validates placeholders', function () {
    $db = database(); locale($db, 'en'); locale($db, 'zh', 'preview', 0, 1);
    $strings = new UiStrings($db); $rows = fn () => $db->all('SELECT locale, `key`, value FROM ui_strings ORDER BY locale, `key`');
    $strings->save(['zh' => ['posts' => '文章', 'tags' => '话题', 'home' => ''], 'en' => ['posts' => 'Posts', 'menu' => 'Navigation']]);
    same([['locale' => 'en', 'key' => 'menu', 'value' => 'Navigation'], ['locale' => 'zh', 'key' => 'tags', 'value' => '话题']], $rows());
    $strings->save(['zh' => ['posts' => '博文']]); same(3, count($rows()));
    $strings->save(['zh' => ['tags' => '']]); same(['menu', 'posts'], array_column($rows(), 'key'));
    same('首页', $strings->own('zh')['home']); same('博文', $strings->own('zh')['posts']);
    try { $strings->save(['zh' => ['reading_minutes' => '阅读几分钟']]); throw new RuntimeException('Expected an error'); }
    catch (InvalidArgumentException $e) { contains('keep the placeholder {minutes}', $e->getMessage()); }
    try { $strings->save(['zh' => ['posts' => '{oops}']]); throw new RuntimeException('Expected an error'); }
    catch (InvalidArgumentException $e) { contains('remove the {braces}', $e->getMessage()); }
    throws(fn () => $strings->save(['xx' => ['posts' => 'x']]), InvalidArgumentException::class);
    throws(fn () => $strings->save(['zh' => ['nope' => 'x']]), InvalidArgumentException::class);
    throws(fn () => $strings->save(['zh' => ['posts' => str_repeat('a', 2001)]]), InvalidArgumentException::class);
    same(['menu', 'posts'], array_column($rows(), 'key'));
    $db->query("INSERT INTO ui_strings (locale, `key`, value) VALUES ('zh', 'archive_description', '旧的说明')");
    $strings->save(['zh' => ['archive_description' => '旧的说明', 'home' => '主页']]);
    same('主页', $strings->own('zh')['home']); same('旧的说明', $strings->own('zh')['archive_description']);
});
test('Language files: export and import move a language between sites', function () {
    $db = database(); locale($db, 'en'); locale($db, 'zh', 'live', 0, 1);
    $strings = new UiStrings($db); $strings->save(['zh' => ['posts' => '博文']]);
    $json = $strings->export('zh'); $data = json_decode($json, true);
    same(1, $data['meteor_language_pack']); same('zh', $data['code']); same('博文', $data['strings']['posts']); same('首页', $data['strings']['home']);
    same(array_keys(require dirname(__DIR__) . '/lang/en.php'), array_keys($data['strings'])); contains('"博文"', $json);
    throws(fn () => $strings->export('xx'), InvalidArgumentException::class);
    // Another site: the edit comes back as the only stored row; matching text stays in the file.
    $other = database(); locale($other, 'en'); $import = new UiStrings($other);
    $message = $import->import($json);
    contains('added as Off', $message);
    same('off', $other->one('SELECT status FROM locales WHERE code = ?', ['zh'])['status']);
    same([['key' => 'posts', 'value' => '博文']], $other->all("SELECT `key`, value FROM ui_strings WHERE locale = 'zh'"));
    $ja = json_encode(packData('ja', ['posts' => '投稿', 'bogus' => 'x']));
    contains('Skipped 1 unknown or invalid entry.', $import->import($ja));
    same('Test ja', $other->one("SELECT name FROM locales WHERE code = 'ja'")['name']);
    $import->import(json_encode(packData('ja', ['posts' => '記事'], ['name' => 'Japanese'])));
    same('Test ja', $other->one("SELECT name FROM locales WHERE code = 'ja'")['name']); same('記事', $import->own('ja')['posts']);
    $import->import(json_encode(packData('ja', [], ['name' => 'Japanese', 'direction' => 'rtl'])), true);
    same(['Japanese', 'rtl'], array_values($other->one("SELECT name, direction FROM locales WHERE code = 'ja'")));
});
test('Language files: bundled languages are added once as Off and stay deleted', function () {
    $db = database(); locale($db, 'en'); locale($db, 'ms', 'live', 0, 1);
    $dir = languageDir(['zh.json' => packData('zh', ['posts' => '文章']), 'ms.json' => packData('ms', ['posts' => 'Catatan']), 'bad.json' => 'nope']);
    try {
        same(['zh'], LanguagePacks::sync($db, $dir));
        same(['en' => 'live', 'ms' => 'live', 'zh' => 'off'], array_column($db->all('SELECT code, status FROM locales ORDER BY code'), 'status', 'code'));
        same('Native zh', $db->one("SELECT native_name FROM locales WHERE code = 'zh'")['native_name']); same(2, (int) $db->one("SELECT sort_order FROM locales WHERE code = 'zh'")['sort_order']);
        same([], LanguagePacks::sync($db, $dir));
        $db->query("DELETE FROM locales WHERE code = 'zh'"); same([], LanguagePacks::sync($db, $dir)); same(null, $db->one("SELECT code FROM locales WHERE code = 'zh'"));
        file_put_contents($dir . '/ja.json', json_encode(packData('ja', []))); same(['ja'], LanguagePacks::sync($db, $dir));
        same('ja,ms,zh', $db->one("SELECT value FROM settings WHERE `key` = 'language_packs_known'")['value']);
    } finally { Core\ReleasePackage::removeTree($dir); }
    $db = database(); locale($db, 'en');
    $db->upsert('settings', ['key' => 'update_repository', 'locale' => null, 'value' => 'owner/repo'], ['key', 'locale_scope']);
    $db->upsert('settings', ['key' => 'update_token', 'locale' => null, 'value' => 'secret'], ['key', 'locale_scope']);
    (require dirname(__DIR__) . '/database/migrations/004_language_files_and_updates.php')($db);
    same(['language_packs_known'], array_column($db->all('SELECT `key` FROM settings'), 'key'));
    same(['en', 'ms', 'zh-hans'], array_column($db->all('SELECT code FROM locales ORDER BY code'), 'code'));
});
