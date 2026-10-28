<?php
use App\Services\{Themes, ThemePackage, ThemeCheck, UiStrings};

function authorZip(string $file, array $entries, ?string $link = null): string
{
    $zip = new ZipArchive(); same(true, $zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE));
    foreach ($entries as $name => $data) { $zip->addFromString($name, is_array($data) ? json_encode($data) : $data); }
    if ($link !== null) { $zip->setExternalAttributesName($link, ZipArchive::OPSYS_UNIX, 0120777 << 16); }
    $zip->close(); return $file;
}
function authorError(callable $body, string $message): void
{
    try { $body(); } catch (Throwable $e) { contains($message, $e->getMessage()); return; }
    throw new RuntimeException('Expected error: ' . $message);
}

test('Theme authoring: zip layouts, replacement preserves settings and cleans staging', function () {
    themeSandbox(function ($root) {
        $db = database(); locale($db); $themes = new Themes($db);
        $file = $root . '/upload.zip';
        authorZip($file, ['theme.json' => themeJson('sample'), 'theme.css' => 'body{}', '__MACOSX/junk.js' => '', '.git/config' => '', '.DS_Store' => '']);
        same('sample', $themes->installZip($file, false)['slug']);
        ok(!file_exists($root . '/themes/sample/.git'));
        authorError(fn () => $themes->installZip($file, false), 'already installed. Tick Replace');
        $db->upsert('settings', ['key' => 'theme.sample.note', 'locale' => null, 'value' => '"Keep"'], ['key', 'locale_scope']);
        authorZip($file, ['repo-main/theme.json' => themeJson('sample', ['version' => '2.0.0']), 'repo-main/theme.css' => 'new{}']);
        same(true, $themes->installZip($file, true)['replaced']);
        same('new{}', file_get_contents($root . '/themes/sample/theme.css'));
        same('"Keep"', $db->one('SELECT value FROM settings WHERE `key` = ?', ['theme.sample.note'])['value']);
        same([], glob($root . '/themes/.upload-*')); same([], glob($root . '/themes/.old-*'));
    });
});

test('Theme authoring: rejects malicious archives, PHP syntax and invalid manifests', function () {
    themeSandbox(function ($root) {
        $themes = new Themes(database()); $file = $root . '/upload.zip';
        $cases = [
            [['theme.json' => ['name' => 'No slug', 'api' => 1]], 'Add "slug"'],
            [['theme.json' => themeJson('safe'), '../escape.php' => ''], 'Unsafe theme path'],
            [['theme.json' => themeJson('safe'), '/absolute.php' => ''], 'Unsafe theme path'],
            [['theme.json' => themeJson('safe'), 'C:/absolute.php' => ''], 'Unsafe theme path'],
            [['theme.json' => themeJson('safe'), 'a\\b.php' => ''], 'Unsafe theme path'],
            [['theme.json' => themeJson('safe'), 'script.js' => ''], 'Themes cannot ship JavaScript'],
            [['theme.json' => themeJson('safe'), 'bad.html' => ''], 'File type is not allowed'],
            [['theme.json' => themeJson('safe'), 'public/home.php' => "<?php\nif ( ?>"], 'public/home.php line 2'],
            [['theme.json' => themeJson('safe', ['api' => 88])], 'Theme API 88'],
            [['a/theme.json' => themeJson('safe'), 'b/readme.txt' => ''], 'top level or inside one folder'],
            [['a/b/theme.json' => themeJson('safe')], 'top level or inside one folder'],
        ];
        foreach ($cases as [$entries, $message]) {
            authorZip($file, $entries); authorError(fn () => $themes->installZip($file, false), $message);
            same([], $themes->all()); same([], glob($root . '/themes/.upload-*'));
        }
        authorZip($file, ['theme.json' => themeJson('safe'), 'link.css' => '../secret'], 'link.css');
        authorError(fn () => $themes->installZip($file, false), 'Symlinks');
        authorError(fn () => ThemePackage::visible("bad\0.php"), 'Unsafe');
        authorZip($file, ['theme.json' => themeJson('safe'), 'large.txt' => str_repeat('x', Themes::ASSET_MAX + 1)]);
        authorError(fn () => $themes->installZip($file, false), 'per-file limit');
        $entries = ['theme.json' => themeJson('safe')]; for ($i = 0; $i < 1000; $i++) { $entries["$i.txt"] = ''; }
        authorZip($file, $entries); authorError(fn () => $themes->installZip($file, false), '1000 entries');
    });
});

test('Theme authoring: create, copy, download and delete enforce folder boundaries', function () {
    themeSandbox(function ($root) {
        $db = database(); locale($db); $themes = new Themes($db);
        $theme = $themes->create('My Nice Theme!'); same('my-nice-theme', $theme['slug']); same('0.6.0', $theme['requires']);
        same('1.0.0', $theme['version']); ok(str_starts_with($themes->create('中文')['slug'], 'theme-'));
        authorError(fn () => $themes->create('Duplicate', 'my-nice-theme'), 'already exists');
        authorError(fn () => $themes->create('', 'empty'), '1 to 100');
        foreach (['../outside', '/tmp', 'UPPER'] as $slug) { throws(fn () => $themes->create('Bad', $slug)); throws(fn () => $themes->delete($slug)); }
        contains('storage/themes/my-nice-theme/public/home.php', $themes->copyTemplate('my-nice-theme', 'public/home'));
        same(file_get_contents(dirname(__DIR__) . '/views/public/home.php'), file_get_contents($theme['path'] . '/public/home.php'));
        authorError(fn () => $themes->copyTemplate('my-nice-theme', 'public/home'), 'already has public/home.php');
        foreach (['admin/themes', 'layouts/admin', '../config/config', 'public/home.php'] as $template) { throws(fn () => $themes->copyTemplate('my-nice-theme', $template)); }
        file_put_contents($theme['path'] . '/.secret', 'secret'); symlink(dirname(__DIR__) . '/README.md', $theme['path'] . '/link.md');
        $zipFile = $themes->download('my-nice-theme');
        try {
            $zip = new ZipArchive(); $zip->open($zipFile);
            same(false, $zip->locateName('my-nice-theme/.secret')); same(false, $zip->locateName('my-nice-theme/link.md'));
            same(file_get_contents($theme['path'] . '/public/home.php'), $zip->getFromName('my-nice-theme/public/home.php')); $zip->close();
        } finally { unlink($zipFile); }
        $themes->activate('my-nice-theme'); authorError(fn () => $themes->delete('my-nice-theme'), 'Switch to another');
        $themes->deactivate(); $_SESSION['theme_preview'] = 'my-nice-theme';
        $db->upsert('settings', ['key' => 'theme.my-nice-theme.x', 'locale' => null, 'value' => '1'], ['key', 'locale_scope']);
        $themes->delete('my-nice-theme'); same(null, $themes->find('my-nice-theme')); ok(!isset($_SESSION['theme_preview']));
        same(null, $db->one('SELECT * FROM settings WHERE `key` = ?', ['theme.my-nice-theme.x']));
        ok(is_file(dirname(__DIR__) . '/README.md'));
    });
});

test('Theme authoring: copied public template preserves the built-in golden home', function () {
    themeSandbox(function () {
        $app = publicApp(); $themes = new Themes($app['db']);
        $before = publicRequest($app, '/en/'); $theme = $themes->create('Copied');
        unlink($theme['path'] . '/theme.css');
        $themes->copyTemplate('copied', 'public/home'); $themes->activate('copied');
        same($before, publicRequest($app, '/en/'));
        $actual = array_keys(array_merge(...array_values(Themes::TEMPLATES))); sort($actual);
        $expected = ['layouts/public'];
        foreach (['public', 'partials'] as $dir) { foreach (glob(dirname(__DIR__) . '/views/' . $dir . '/*.php') as $file) { $expected[] = $dir . '/' . basename($file, '.php'); } }
        sort($expected); same($expected, $actual);
    });
});

test('Theme authoring: interface text precedence, legacy codes, validation and active-only edits', function () {
    themeSandbox(function ($root, $write) {
        $db = database(); locale($db, 'ms'); locale($db, 'zh', 'live', 0); locale($db, 'en', 'live', 0);
        $write('words', ['theme.json' => themeJson('words'), 'lang/zh-hans.json' => ['latest' => 'Theme Chinese', 'words_own' => '主题'],
            'lang/en.json' => ['words_en' => 'Theme English', 'words_own' => 'Own'], 'lang/ms.json' => ['words_default' => 'Theme default']]);
        $themes = new Themes($db); $theme = $themes->activate('words');
        $db->query('INSERT INTO ui_strings (locale, `key`, value) VALUES (?, ?, ?)', ['zh', 'latest', 'Site edit']);
        $i18n = new Core\I18n($db, require dirname(__DIR__) . '/lang/en.php'); $i18n->useTheme($theme['strings']);
        same('Site edit', $i18n->translateFor('zh', 'latest')); same('主题', $i18n->translateFor('zh', 'words_own'));
        same('Theme default', $i18n->translateFor('zh', 'words_default')); same('Theme English', $i18n->translateFor('zh', 'words_en'));
        same('unknown', $i18n->translateFor('zh', 'unknown'));
        $i18n->useTheme([]); same('words_own', $i18n->translateFor('zh', 'words_own'));
        $service = new UiStrings($db); $service->save(['en' => ['words_own' => 'Site own']]);
        same('Site own', $service->overrides()['en']['words_own']); ok(!isset($service->own('en')['words_own']));
        ok(!str_contains($service->export('en'), 'words_own'));
        $themes->deactivate(); authorError(fn () => $service->save(['en' => ['words_own' => 'Other']]), 'Unknown interface text');
        $write('badwords', ['theme.json' => themeJson('badwords'), 'lang/en.json' => ['reading_minutes' => 'No placeholder']]);
        contains('placeholders', $themes->find('badwords')['problem']); throws(fn () => $themes->activate('badwords'));
        $write('badwords', ['lang/en.json' => ['Bad_key' => 'Invalid']]); contains('invalid text', $themes->find('badwords')['problem']);
        $write('badwords', ['lang/en.json' => ['own' => str_repeat('x', 1001)]]); contains('1000', $themes->find('badwords')['problem']);
    });
});

test('Theme authoring: checker finds failures and leaves the main database and globals alone', function () {
    themeSandbox(function ($root, $write) {
        $app = publicApp(); $before = $app['db']->all('SELECT * FROM settings'); $global = $GLOBALS['i18n'];
        $checker = new ThemeCheck(); same([], $checker->check(dirname(__DIR__) . '/views/examples/starter'));
        $write('bad', ['theme.json' => themeJson('bad'), 'public/home.php' => '<?php missing_theme_function();',
            'public/search.php' => '<?php if ( ?>', 'bad.js' => 'alert(1)',
            'slots/footer.php' => '<script></script><style></style><p style="color:red" onclick="x()"><img src="/themes/bad/no.png"></p><?php $o = new class { public string $new = "x"; }; \\App\\Theme\\Deprecated::read($o, "old", ["old" => "new"]); ?>']);
        $report = implode("\n", $checker->check($root . '/themes/bad'));
        foreach (['JavaScript', 'line 1', 'template public/home', 'missing_theme_function', 'Deprecated', 'script element', 'style element', 'inline style attribute', 'event attribute', 'missing theme asset'] as $finding) { contains($finding, $report); }
        $write('nocaptcha', ['theme.json' => themeJson('nocaptcha'), 'public/comment-check.php' => '<?php // drops the CAPTCHA script ?><p>Check</p>']);
        same(['comment check: the CAPTCHA script is missing, so comments cannot be sent when CAPTCHA is on. Keep <script src="<?= e($check->script) ?>" async defer></script>.'], $checker->check($root . '/themes/nocaptcha'));
        same($before, $app['db']->all('SELECT * FROM settings')); same($global, $GLOBALS['i18n']);
        same([], glob(dirname(__DIR__) . '/storage/theme-check-*'));
        ok(!file_exists($root . '/themes.log'));
    });
});

test('Theme authoring: a failed replacement restores the old folder', function () {
    themeSandbox(function ($root, $write) {
        $db = database(); $write('restore', ['theme.json' => themeJson('restore'), 'theme.css' => 'old']);
        $moves = 0;
        $themes = new Themes($db, null, function ($from, $to) use (&$moves) { return ++$moves === 2 ? false : rename($from, $to); });
        $zip = authorZip($root . '/replace.zip', ['theme.json' => themeJson('restore'), 'theme.css' => 'new']);
        authorError(fn () => $themes->installZip($zip, true), 'Cannot install');
        same(3, $moves); same('old', file_get_contents($root . '/themes/restore/theme.css'));
        same([], glob($root . '/themes/.upload-*')); same([], glob($root . '/themes/.old-*'));
    });
});

test('Theme authoring: precise interface text fallback order and early page translations', function () {
    themeSandbox(function ($root, $write) {
        $db = database(); locale($db, 'ms'); locale($db, 'zh-hans', 'live', 0);
        mkdir($root . '/packs');
        $builtins = ['a' => 'Built-in A', 'b' => 'Built-in B', 'c' => 'Built-in C', 'd' => 'Built-in D', 'e' => 'Built-in E', 'f' => 'Built-in F', 'g' => 'Built-in G'];
        file_put_contents($root . '/packs/zh-hans.json', Core\LanguagePack::encode(['code' => 'zh-hans', 'name' => 'Chinese', 'native_name' => '中文', 'direction' => 'ltr'], ['a' => 'Pack A', 'b' => 'Pack B', 'c' => 'Pack C']));
        file_put_contents($root . '/packs/ms.json', Core\LanguagePack::encode(['code' => 'ms', 'name' => 'Malay', 'native_name' => 'Malay', 'direction' => 'ltr'], ['e' => 'Default pack E']));
        foreach ([['zh-hans', 'a', 'Site A'], ['ms', 'd', 'Default site D']] as $row) { $db->query('INSERT INTO ui_strings (locale, `key`, value) VALUES (?, ?, ?)', $row); }
        $i18n = new Core\I18n($db, $builtins, $root . '/packs', $root . '/no-downloads');
        $i18n->useTheme(['zh-hans' => ['a' => 'Theme A', 'b' => 'Theme B'], 'ms' => ['d' => 'Default theme D'], 'en' => ['f' => 'Theme English F']]);
        foreach (['a' => 'Site A', 'b' => 'Theme B', 'c' => 'Pack C', 'd' => 'Default site D', 'e' => 'Default pack E', 'f' => 'Theme English F', 'g' => 'Built-in G'] as $key => $value) { same($value, $i18n->translateFor('zh-hans', $key)); }
        $app = publicApp(); $write('early', ['theme.json' => themeJson('early'), 'lang/en.json' => ['archive' => 'Theme archive', 'not_found' => 'Theme missing']]);
        (new Themes($app['db']))->activate('early');
        contains('<title>Theme archive', publicRequest($app, '/en/posts'));
        contains('<title>Theme missing', publicRequest($app, '/en/no/such/page'));
    });
});

test('Admin HTTP: theme authoring routes, upload errors, downloads, deletion and theme text cells', function () {
    $c = new AdminHttpClient();
    try {
        file_put_contents($c->root . '/VERSION', "0.6.0\n");
        [$headers] = $c->request('/admin/themes'); contains('Location: /admin/login', $headers);
        $token = $c->token(); $c->request('/admin/login', 'POST', ['csrf_token' => $token, 'username' => 'editor', 'password' => 'a long password']);
        [, $html] = $c->request('/admin/appearance'); contains('Themes: Settings → Themes', $html); ok(!str_contains($html, 'id="themes"'));
        $token = $c->token('/admin/themes');
        [$h] = $c->request('/admin/themes', 'POST', ['action' => 'create', 'name' => 'No token']); contains('403', $h);
        [$h] = $c->request('/admin/themes', 'POST', ['csrf_token' => $token, 'action' => 'create', 'name' => 'HTTP Theme']); contains('Location: /admin/themes', $h);
        $c->request('/admin/themes', 'POST', ['csrf_token' => $token, 'action' => 'copy', 'slug' => 'http-theme', 'template' => 'public/home']);
        ok(is_file($c->root . '/storage/themes/http-theme/public/home.php'));
        [$h, $body] = $c->request('/admin/themes/http-theme/download'); contains('application/zip', $h); contains('filename="http-theme-1.0.0.zip"', $h); same('PK', substr($body, 0, 2));
        [, $html] = $c->request('/admin/themes/http-theme/delete'); contains('Delete permanently', $html); contains('including any templates you edited', $html); contains('/admin/themes/http-theme/download', $html);
        $c->request('/admin/themes', 'POST', ['csrf_token' => $token, 'action' => 'activate', 'slug' => 'http-theme']);
        [, $html] = $c->request('/admin/themes/http-theme/delete'); contains('Switch to another theme', $html); ok(!str_contains($html, 'Delete permanently'));
        $c->request('/admin/themes', 'POST', ['csrf_token' => $token, 'action' => 'delete', 'slug' => 'http-theme']); ok(is_dir($c->root . '/storage/themes/http-theme'));
        mkdir($c->root . '/storage/themes/http-theme/lang');
        file_put_contents($c->root . '/storage/themes/http-theme/lang/en.json', json_encode(['http_own' => 'Theme base', 'latest' => 'Reworded']));
        [, $html] = $c->request('/admin/strings'); contains('From HTTP Theme', $html); contains('strings[en][http_own]', $html); contains('placeholder="Theme base"', $html);
        $c->request('/admin/strings', 'POST', ['csrf_token' => $token, 'strings' => ['en' => ['http_own' => 'Edited']]]);
        [, $html] = $c->request('/admin/strings'); contains('value="Edited"', $html);
        $c->request('/admin/themes', 'POST', ['csrf_token' => $token, 'action' => 'upload']);
        [, $html] = $c->request('/admin/themes'); contains('PHP upload_max_filesize and post_max_size', $html);
        $c->request('/admin/appearance/themes', 'POST', ['csrf_token' => $token, 'action' => 'deactivate']);
        $c->request('/admin/themes', 'POST', ['csrf_token' => $token, 'action' => 'delete', 'slug' => 'http-theme']); ok(!is_dir($c->root . '/storage/themes/http-theme'));
        [$h] = $c->request('/admin/themes/INVALID/delete'); contains('404', $h);
        $zipFile = authorZip($c->root . '/storage/http-upload.zip', ['package/theme.json' => themeJson('uploaded'), 'package/theme.css' => 'body{}']);
        [$h] = $c->request('/admin/themes', 'POST', ['csrf_token' => $token, 'action' => 'upload'], upload: ['field' => 'theme_zip', 'filename' => 'theme.zip', 'bytes' => file_get_contents($zipFile)]);
        contains('Location: /admin/themes', $h);
        [, $html] = $c->request('/admin/themes'); contains('Uploaded 1.0.0 installed.', $html);
        $c->request('/admin/themes', 'POST', ['csrf_token' => $token, 'action' => 'upload', 'replace' => '1'], upload: ['field' => 'theme_zip', 'filename' => 'theme.zip', 'bytes' => file_get_contents($zipFile)]);
        [, $html] = $c->request('/admin/themes'); contains('Uploaded updated to 1.0.0.', $html);
        same([], glob($c->root . '/storage/themes/.upload-*'));
        same([], glob($c->root . '/storage/theme-download-*'));
    } finally { $c->close(); }
});

test('Theme authoring: archive byte limits and checker zip input', function () {
    themeSandbox(function ($root) {
        $themes = new Themes(database()); $file = $root . '/limit.zip';
        $handle = fopen($file, 'w'); ftruncate($handle, ThemePackage::LIMIT + 1); fclose($handle);
        authorError(fn () => $themes->installZip($file, false), '20 MB');
        $entries = ['theme.json' => themeJson('large')];
        for ($i = 0; $i < 11; $i++) { $entries[$i . '.txt'] = str_repeat('x', Themes::ASSET_MAX); }
        authorZip($file, $entries); unset($entries);
        authorError(fn () => $themes->installZip($file, false), '50 MB');
        $theme = $themes->installStarter();
        $zip = $themes->download('starter');
        try { same([], (new ThemeCheck())->check($zip)); } finally { unlink($zip); }
        same([], glob($root . '/themes/.upload-*'));
    });
});
