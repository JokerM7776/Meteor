<?php
function adminFixture(): array {
    $db = database(); locale($db); locale($db, 'ja', 'preview', 0, 1); locale($db, 'de', 'off', 0, 2);
    $db->query('INSERT INTO users (username, password_hash) VALUES (?, ?)', ['editor', password_hash('a long password', PASSWORD_DEFAULT)]);
    $i18n = new Core\I18n($db, require dirname(__DIR__) . '/lang/en.php'); $GLOBALS['i18n'] = $i18n;
    $config = ['app_url' => 'https://meteor.example', 'webauthn' => ['rp_id' => 'meteor.example', 'rp_name' => 'Meteor']];
    $view = new Core\View(dirname(__DIR__) . '/views');
    $controller = new App\Controllers\Admin\AdminController($db, $i18n, $view, $config);
    return compact('db', 'i18n', 'controller', 'view');
}
test('Admin: content saves compile, first publication dates persist, pages and taxonomy detach', function () {
    $f = adminFixture(); $db = $f['db']; $editor = new App\Services\ContentEditor($db);
    $values = ['slug' => 'hello-world', 'title' => 'Hello', 'content_md' => "## 世界\n\n**Bold**", 'status' => 'draft', 'is_published' => '1', 'tags' => 'Hello, PHP'];
    $id = $editor->save(false, 0, 'en', $values);
    $row = $db->one('SELECT * FROM post_translations'); contains('<strong>Bold</strong>', $row['content_html']); contains('世界', $row['toc_html']); same(null, $row['published_at']);
    $values['status'] = 'published'; $editor->save(false, $id, 'en', $values);
    $row = $db->one('SELECT * FROM post_translations'); ok($row['published_at'] !== null); $date = $row['published_at'];
    $values['status'] = 'draft'; $editor->save(false, $id, 'en', $values); same($date, $db->one('SELECT * FROM post_translations')['published_at']);
    $values['status'] = 'published'; $editor->save(false, $id, 'en', $values);
    $posts = new App\Repositories\Posts($db); same(1, count($posts->published('en')));
    $editor->save(false, $id, 'ja', array_replace($values, ['title' => '日本語', 'status' => 'draft']));
    throws(fn () => $editor->save(false, 0, 'en', $values));
    throws(fn () => $editor->save(false, 0, 'en', array_replace($values, ['slug' => '../bad'])));
    throws(fn () => $editor->save(true, 0, 'en', array_replace($values, ['slug' => 'posts'])));
    $pageId = $editor->save(true, 0, 'en', array_replace($values, ['slug' => 'about', 'show_in_nav' => '1']));
    same('published', $db->one('SELECT status FROM page_translations')['status']);
    $taxonomy = new App\Services\TaxonomyEditor($db); $taxonomy->save(['kind' => 'category', 'slug' => 'code', 'names' => ['en' => 'Code', 'ja' => 'コード']]);
    $category = $db->one('SELECT id FROM categories')['id']; $db->query('UPDATE posts SET category_id = ? WHERE id = ?', [$category, $id]);
    $taxonomy->save(['kind' => 'category', 'action' => 'delete', 'id' => $category]); same(null, $db->one('SELECT category_id FROM posts')['category_id']);
    $tag = $db->one('SELECT id FROM tags')['id']; $taxonomy->save(['kind' => 'tag', 'action' => 'delete', 'id' => $tag]);
    same(null, $db->one('SELECT tag_id FROM post_tags WHERE tag_id = ?', [$tag]));
    $editor->delete(false, $id); same([], $db->all('SELECT * FROM post_translations')); $editor->delete(true, $pageId);
});
test('Admin: language rules, settings fallback and CSS validation', function () {
    $f = adminFixture(); $db = $f['db']; $languages = new App\Services\Languages($db);
    $languages->save('add', ['code' => 'pt-br', 'name' => 'Portuguese', 'native_name' => 'Português']);
    same('preview', $db->one('SELECT status FROM locales WHERE code = ?', ['pt-br'])['status']);
    throws(fn () => $languages->save('add', ['code' => 'INVALID', 'name' => 'Bad', 'native_name' => 'Bad']));
    throws(fn () => $languages->save('status', ['code' => 'en', 'status' => 'off']));
    throws(fn () => $languages->save('default', ['code' => 'pt-br']));
    $languages->save('status', ['code' => 'pt-br', 'status' => 'live']); $languages->save('default', ['code' => 'pt-br', 'match_browser_language' => '1']);
    $languages->save('up', ['code' => 'pt-br']);
    same(1, (int) $db->one('SELECT is_default FROM locales WHERE code = ?', ['pt-br'])['is_default']);
    $editor = new App\Services\ContentEditor($db); $editor->save(false, 0, 'ja', ['slug' => 'test', 'title' => 'Test']);
    throws(fn () => $languages->save('delete', ['code' => 'ja'])); $languages->save('delete', ['code' => 'de']);
    $settings = new App\Services\AdminSettings($db);
    $settings->save(['text' => ['site_title' => ['pt-br' => 'Title fallback']], 'custom_css' => 'body {color:red}']);
    (new App\Services\UiStrings($db))->save(['pt-br' => ['more_posts' => 'More fallback']]);
    $settings->save(['text' => ['site_title' => ['ja' => '']], 'custom_css' => 'body {color:red}']);
    $i18n = new Core\I18n($db, require dirname(__DIR__) . '/lang/en.php'); $i18n->setLocale('ja', true);
    same('More fallback', $i18n->translate('more_posts')); same('Title fallback', (new App\Repositories\Settings($db, $i18n))->get('site_title', 'ja'));
    throws(fn () => $settings->save(['custom_css' => '</StYle><script>']));
});
test('Admin: account password version, username and owned passkey actions', function () {
    $f = adminFixture(); $db = $f['db']; $service = new App\Services\Account($db); $user = $db->one('SELECT * FROM users');
    $_SESSION = ['user_id' => $user['id'], 'session_version' => 1];
    throws(fn () => $service->update($user, 'username', ['username' => 'new', 'current_password' => 'wrong']));
    $service->update($user, 'username', ['username' => 'new', 'current_password' => 'a long password']);
    same('new', $db->one('SELECT username FROM users')['username']);
    $service->update($user, 'password', ['current_password' => 'a long password', 'new_password' => 'a newer password', 'new_password_confirm' => 'a newer password']);
    same(2, $_SESSION['session_version']); ok((new Core\Auth($db))->user() !== null);
    $_SESSION['session_version'] = 1; same(null, (new Core\Auth($db))->user());
    $db->query('INSERT INTO passkeys (user_id, credential_id, credential_hash, public_key, algorithm, name) VALUES (?, ?, ?, ?, ?, ?)', [$user['id'], 'key', hash('sha256', 'key'), 'test', -7, 'Old']);
    $id = $db->one('SELECT id FROM passkeys')['id']; $service->update($user, 'rename', ['id' => $id, 'name' => 'New']); same('New', $db->one('SELECT name FROM passkeys')['name']);
    $user = $db->one('SELECT * FROM users'); throws(fn () => $service->update($user, 'remove', ['id' => $id, 'current_password' => 'wrong']));
    $service->update($user, 'remove', ['id' => $id, 'current_password' => 'a newer password']); same([], $db->all('SELECT * FROM passkeys'));
    $_SESSION = [];
});
test('Admin: every view renders with escaped data, external-only script, matrix status and form CSRF', function () {
    $f = adminFixture(); $db = $f['db']; $_SESSION = ['user_id' => 1, 'session_version' => 1]; $_SERVER['REQUEST_METHOD'] = 'GET'; $_GET = [];
    $editor = new App\Services\ContentEditor($db); $id = $editor->save(false, 0, 'en', ['slug' => 'hello', 'title' => '<script>title</script>', 'status' => 'published', 'is_pinned' => '1']);
    foreach (['/admin/posts', '/admin/pages', '/admin/editor', '/admin/languages', '/admin/settings', '/admin/taxonomy', '/admin/security', '/admin/posts/1/delete'] as $path) {
        $html = $f['controller']->handle($path); ok(preg_match('~<script src="/assets/admin\.js\?v=\d+" defer></script>~', $html) === 1, 'admin.js');
        ok(!preg_match('~<script(?! src="/assets/admin\.js\?v=\d+" defer>)~i', $html)); ok(!preg_match('/\son[a-z]+\s*=/i', $html));
        preg_match_all('~<form\b[^>]*method="post"[^>]*>(.*?)</form>~s', $html, $forms);
        foreach ($forms[1] as $form) { contains('name="csrf_token"', $form); }
        if ($path === '/admin/posts') { contains('✓ Published', $html); contains('+ Add', $html); contains('base', $html); contains('Pinned', $html); contains('&lt;script&gt;title', $html); ok(!str_contains($html, '>de ·')); }
    }
    $editor->save(false, $id, 'ja', ['slug' => 'hello', 'title' => 'Japanese', 'status' => 'draft']); contains('◐ Draft', $f['controller']->handle('/admin/posts'));
    $_GET = ['post' => $id, 'locale' => 'ja']; contains('Original · read only', $f['controller']->handle('/admin/editor'));
    $_SESSION = []; $_GET = []; contains('autocomplete="username webauthn"', $f['controller']->handle('/admin/login'));
});
test('Admin: Phase 2 upgrade adds and backfills publication dates idempotently', function () {
    $path = dirname(__DIR__) . '/storage/test-migrate-' . bin2hex(random_bytes(6)) . '.sqlite';
    $db = new Core\Database(['driver' => 'sqlite', 'path' => $path]);
    try {
        foreach (schemaStatements('sqlite') as $statement) {
            if (str_starts_with($statement, 'CREATE TABLE post_translations') || str_starts_with($statement, 'CREATE TABLE page_translations')) { $statement = str_replace('    published_at DATETIME,', '', $statement); }
            $db->query($statement);
        }
        (new App\Services\DemoSeeder($db))->run(false);
        foreach ([1, 2] as $attempt) {
            $process = proc_open([PHP_BINARY, dirname(__DIR__) . '/bin/migrate.php'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__), array_merge(getenv(), ['DB_DRIVER' => 'sqlite', 'DB_PATH' => $path]));
            fclose($pipes[0]); $out = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); same(0, proc_close($process)); same('', $error); contains('migrations applied', $out);
        }
        foreach (['post_translations', 'page_translations'] as $table) { ok($db->hasColumn($table, 'published_at')); same(null, $db->one("SELECT id FROM $table WHERE status = 'published' AND published_at IS NULL")); }
    } finally { unset($db); foreach ([$path, $path . '-wal', $path . '-shm'] as $file) { if (is_file($file)) { unlink($file); } } }
});
test('Admin: matrix filters, search, counts and pagination use configured visible locales', function () {
    $f = adminFixture(); $_SESSION = ['user_id' => 1, 'session_version' => 1]; $_SERVER['REQUEST_METHOD'] = 'GET';
    $editor = new App\Services\ContentEditor($f['db']);
    for ($i = 1; $i <= 23; $i++) {
        $id = $editor->save(false, 0, 'en', ['slug' => 'post-' . $i, 'title' => 'Title ' . $i, 'status' => 'published']);
        if ($i === 1) { $editor->save(false, $id, 'ja', ['slug' => 'post-' . $i, 'title' => 'Japanese draft', 'status' => 'draft']); }
    }
    $_GET = []; $html = $f['controller']->handle('/admin/posts'); contains('23 results', $html); contains('Next →', $html); same(20, substr_count($html, '✓ Published'));
    $_GET = ['page' => 2]; $html = $f['controller']->handle('/admin/posts'); same(3, substr_count($html, '✓ Published')); contains('← Previous', $html);
    $_GET = ['filter' => 'drafts']; $html = $f['controller']->handle('/admin/posts'); contains('1 results', $html); contains('◐ Draft', $html);
    $_GET = ['filter' => 'missing']; contains('22 results', $f['controller']->handle('/admin/posts'));
    $_GET = ['q' => 'Japanese']; contains('1 results', $f['controller']->handle('/admin/posts'));
    $_GET = ['q' => 'post-23']; contains('Title 23', $f['controller']->handle('/admin/posts'));
    $_GET = []; $_SESSION = [];
});
test('Admin: link URL rules', function () {
    foreach (['/en/', '/{locale}/projects', '#main', 'HTTPS://example.com/a|b', 'http://example.com', 'MAILTO:hello@example.com', 'https://example.com/{locale}/'] as $url) { ok(App\Services\SiteLinks::validUrl($url), $url); }
    foreach (['', 'javascript:alert(1)', 'JavaScript:alert(1)', 'data:text/plain,x', 'vbscript:x', '//host', '/\\host', 'ftp://host', '/a b', "/a\tx", '/a' . chr(0), 'https://', 'relative/path'] as $url) { ok(!App\Services\SiteLinks::validUrl($url), $url); }
});
test('Admin: menu editor saves only what is posted and keeps built-ins', function () {
    $f = adminFixture(); $db = $f['db']; $menus = new App\Repositories\Menus($db); $editor = new App\Services\MenuEditor($db);
    same(5, count($menus->rows('header'))); same(0, (int) $menus->rows('header')[0]['id']);
    $menus->ensureDefaults(); $menus->ensureDefaults(); same(10, (int) $db->one('SELECT COUNT(*) AS n FROM menu_items')['n']);
    $ids = array_column($menus->rows('header'), 'id', 'kind');
    $editor->save('header', ['header_show_title' => '0', 'items' => [$ids['posts'] => ['labels' => ['en' => 'Blog', 'ja' => '']], $ids['tags'] => ['visible' => '0']]]);
    $settings = new App\Repositories\Settings($db, $f['i18n']);
    same('0', $settings->global('header_show_title')); same('', $settings->global('header_show_icon'));
    throws(fn () => $editor->save('header', ['header_show_icon' => '0']), InvalidArgumentException::class);
    $rows = array_column($menus->rows('header'), null, 'kind'); same(['en' => 'Blog'], $rows['posts']['labels']); same(0, (int) $rows['tags']['is_visible']);
    $editor->save('header', ['items' => [$ids['posts'] => ['labels' => ['ja' => 'ブログ']]]]);
    same(['en' => 'Blog', 'ja' => 'ブログ'], array_column($menus->rows('header'), null, 'kind')['posts']['labels']);
    try { $editor->save('header', ['items' => [$ids['rss'] => ['remove' => '1']]]); throw new RuntimeException('Expected an error'); }
    catch (InvalidArgumentException $e) { contains('Header menu, row 4: built-in items', $e->getMessage()); }
    foreach (['javascript:alert(1)', '//evil.example', 'data:text/html,x', ''] as $url) {
        try { $editor->save('header', ['items' => ['new' => ['url' => $url, 'labels' => ['en' => 'Bad']]]]); throw new RuntimeException('Expected an error'); }
        catch (InvalidArgumentException $e) { contains('Header menu, new link: use a URL', $e->getMessage()); }
    }
    throws(fn () => $editor->save('header', ['items' => ['new' => ['url' => '/x', 'labels' => ['en' => '']]]]), InvalidArgumentException::class);
    throws(fn () => $editor->save('header', ['items' => ['new' => ['url' => '/x', 'labels' => ['xx' => 'Unknown']]]]), InvalidArgumentException::class);
    throws(fn () => $editor->save('header', ['items' => ['new' => ['url' => '/x', 'labels' => ['en' => str_repeat('a', 81)]]]]), InvalidArgumentException::class);
    throws(fn () => $editor->save('footer', ['items' => [$ids['posts'] => ['visible' => '0']]]), InvalidArgumentException::class);
    $editor->save('header', ['items' => ['new' => ['url' => '/{locale}/projects', 'sort_order' => '-5', 'labels' => ['en' => 'Projects']]]]);
    $rows = $menus->rows('header'); same('link', $rows[0]['kind']); same(range(0, 5), array_map('intval', array_column($rows, 'sort_order')));
    $link = $rows[0]['id'];
    throws(fn () => $editor->save('header', ['items' => [$link => ['labels' => ['en' => '']]]]), InvalidArgumentException::class);
    same('Projects', $menus->rows('header')[0]['labels']['en']);
    $editor->save('header', ['items' => [$link => ['sort_order' => '99']]]); $rows = $menus->rows('header'); same('link', $rows[array_key_last($rows)]['kind']);
    for ($n = 0; $n < 24; $n++) { $editor->save('header', ['items' => ['new' => ['url' => '/n' . $n, 'labels' => ['en' => 'N' . $n]]]]); }
    try { $editor->save('header', ['items' => ['new' => ['url' => '/full', 'labels' => ['en' => 'Full']]]]); throw new RuntimeException('Expected an error'); }
    catch (InvalidArgumentException $e) { contains('at most 30', $e->getMessage()); }
    $editor->save('header', ['items' => [$link => ['remove' => '1']]]); same(null, $db->one('SELECT id FROM menu_items WHERE id = ?', [$link]));
    $editor->save('footer', ['footer_show_feeds' => '0', 'footer_menu_heading' => ['en' => 'More', 'ja' => '']]);
    same('0', $settings->global('footer_show_feeds')); same('More', $settings->get('footer_menu_heading', 'en'));
});
test('Admin: taxonomy rows save independently and keep other languages', function () {
    $f = adminFixture(); $db = $f['db']; $editor = new App\Services\TaxonomyEditor($db);
    $editor->save(['kind' => 'category', 'slug' => 'code', 'sort_order' => '5', 'names' => ['en' => 'Code', 'ja' => 'コード']]);
    $id = (int) $db->one('SELECT id FROM categories')['id']; same(5, (int) $db->one('SELECT sort_order FROM categories')['sort_order']);
    $editor->save(['kind' => 'category', 'id' => $id, 'slug' => 'coding', 'names' => ['en' => 'Coding']]);
    $names = $editor->rows('category')[0]['names']; ksort($names); same(['en' => 'Coding', 'ja' => 'コード'], $names); same(5, (int) $editor->rows('category')[0]['sort_order']);
    $editor->save(['kind' => 'category', 'id' => $id, 'slug' => 'coding', 'names' => ['ja' => '']]); same(['en' => 'Coding'], $editor->rows('category')[0]['names']);
    throws(fn () => $editor->save(['kind' => 'category', 'id' => $id, 'slug' => 'coding', 'sort_order' => '1.5']), InvalidArgumentException::class);
    throws(fn () => $editor->save(['kind' => 'category', 'slug' => 'coding']), InvalidArgumentException::class);
    throws(fn () => $editor->save(['kind' => 'tag', 'id' => 999, 'slug' => 'ghost']), InvalidArgumentException::class);
    $editor->save(['kind' => 'tag', 'slug' => 'php', 'names' => ['en' => 'PHP', 'ja' => 'ピーエイチピー']]);
    same(1, count($editor->rows('tag', 'ピーエイチ'))); same(0, count($editor->rows('tag', 'nothing'))); same(1, count($editor->rows('category', 'COD')));
});
test('Admin: Turnstile keys are checked with Cloudflare and tokens verified', function () {
    $db = database(); locale($db); $calls = [];
    $reply = ['success' => false, 'error-codes' => ['invalid-input-secret']];
    $send = function (string $url, array $fields) use (&$calls, &$reply): ?array { $calls[] = [$url, $fields]; return $reply; };
    $guard = new App\Services\LoginProtection($db, true, $send);
    ok($guard->passes('', '1.2.3.4'), 'Off means no check');
    throws(fn () => $guard->save(['turnstile_enabled' => '1', 'turnstile_site_key' => '0x4AAAAAAAsite']), InvalidArgumentException::class);
    throws(fn () => $guard->save(['turnstile_enabled' => '0', 'turnstile_site_key' => 'bad key!']), InvalidArgumentException::class);
    throws(fn () => $guard->save(['turnstile_enabled' => '1', 'turnstile_site_key' => '0x4AAAAAAAsite', 'turnstile_secret_key' => '0x4AAAAAAAwrong']), InvalidArgumentException::class);
    same('https://challenges.cloudflare.com/turnstile/v0/siteverify', $calls[0][0]); same('0x4AAAAAAAwrong', $calls[0][1]['secret']); ok(!$guard->active());
    $reply = null;
    throws(fn () => $guard->save(['turnstile_enabled' => '1', 'turnstile_site_key' => '0x4AAAAAAAsite', 'turnstile_secret_key' => '0x4AAAAAAAgood']), InvalidArgumentException::class);
    $reply = ['success' => false, 'error-codes' => ['invalid-input-response']];
    $guard->save(['turnstile_enabled' => '1', 'turnstile_site_key' => '0x4AAAAAAAsite', 'turnstile_secret_key' => '0x4AAAAAAAgood']);
    ok($guard->active()); same('0x4AAAAAAAsite', $guard->siteKey());
    // An empty secret field keeps the saved one.
    $guard->save(['turnstile_enabled' => '1', 'turnstile_site_key' => '0x4AAAAAAAsite', 'turnstile_secret_key' => '']); same('0x4AAAAAAAgood', $guard->values()['secret']);
    $calls = [];
    ok(!$guard->passes('', '1.2.3.4')); ok(!$guard->passes(str_repeat('a', 2049), '1.2.3.4')); same([], $calls);
    ok(!$guard->passes('token', '1.2.3.4'));
    $reply = ['success' => true]; ok($guard->passes('token', '1.2.3.4'));
    same(['secret' => '0x4AAAAAAAgood', 'response' => 'token', 'remoteip' => '1.2.3.4'], $calls[1][1]);
    $reply = null; ok(!$guard->passes('token', '1.2.3.4'), 'Unreachable Cloudflare fails closed');
    $off = new App\Services\LoginProtection($db, false, $send); ok(!$off->active()); ok($off->passes('', '1.2.3.4'));
    $guard->save(['turnstile_enabled' => '0', 'turnstile_site_key' => '0x4AAAAAAAsite']); ok(!$guard->active());
});
