<?php
test('Admin UX: slugs generated from the title, unique, Unicode and reserved-aware', function () {
    $f = adminFixture(); $db = $f['db']; $editor = new App\Services\ContentEditor($db);
    same('hello-world', App\Services\ContentEditor::slugFrom('  Hello, World! ', 'post'));
    same('你好世界', App\Services\ContentEditor::slugFrom('你好世界', 'post'));
    same('post', App\Services\ContentEditor::slugFrom('!!! ???', 'post'));
    same(80, mb_strlen(App\Services\ContentEditor::slugFrom(str_repeat('ab-', 40) . 'x', 'post')));
    same(str_repeat('abc-', 19) . 'abc', App\Services\ContentEditor::slugFrom(str_repeat('abc ', 30), 'post'));
    $first = $editor->save(false, 0, 'en', ['slug' => '', 'title' => 'Hello, World!']);
    $second = $editor->save(false, 0, 'en', ['title' => 'Hello world']);
    same('hello-world', $db->one('SELECT slug FROM posts WHERE id = ?', [$first])['slug']);
    same('hello-world-2', $db->one('SELECT slug FROM posts WHERE id = ?', [$second])['slug']);
    $cjk = $editor->save(false, 0, 'en', ['title' => '你好世界']); same('你好世界', $db->one('SELECT slug FROM posts WHERE id = ?', [$cjk])['slug']);
    $symbols = $editor->save(false, 0, 'en', ['title' => '???']); same('post', $db->one('SELECT slug FROM posts WHERE id = ?', [$symbols])['slug']);
    // A translation saved with an empty slug uses the base-language title.
    $editor->save(false, $first, 'ja', ['slug' => '', 'title' => 'こんにちは']); same('hello-world', $db->one('SELECT slug FROM posts WHERE id = ?', [$first])['slug']);
    $page = $editor->save(true, 0, 'en', ['title' => 'Posts']); same('posts-2', $db->one('SELECT slug FROM pages WHERE id = ?', [$page])['slug']);
    $mark = $editor->save(true, 0, 'en', ['slug' => 'नमस्ते', 'title' => 'Hindi']); same('नमस्ते', $db->one('SELECT slug FROM pages WHERE id = ?', [$mark])['slug']);
    throws(fn () => $editor->save(false, 0, 'en', ['slug' => 'bad slug', 'title' => 'x']), InvalidArgumentException::class);
});
test('Admin UX: language order is saved from the dragged list', function () {
    $f = adminFixture(); $db = $f['db']; $languages = new App\Services\Languages($db);
    $languages->save('order', ['order' => ['de', 'en', 'ja']]);
    same(['de', 'en', 'ja'], array_column($db->all('SELECT code FROM locales ORDER BY sort_order'), 'code'));
    foreach ([['en', 'ja'], ['en', 'ja', 'ja'], ['en', 'ja', 'xx'], 'en'] as $bad) {
        throws(fn () => $languages->save('order', ['order' => $bad]), InvalidArgumentException::class);
    }
    same(['de', 'en', 'ja'], array_column($db->all('SELECT code FROM locales ORDER BY sort_order'), 'code'));
    $_SESSION = ['user_id' => 1, 'session_version' => 1]; $_SERVER['REQUEST_METHOD'] = 'GET'; $_GET = [];
    $html = $f['controller']->handle('/admin/languages');
    contains('id="language-order"', $html); contains('data-drag-handle', $html); contains('form="language-order" name="order[]" value="de"', $html);
    ok(!str_contains($html, 'value="up"'));
    $html = $f['controller']->handle('/admin/menus'); contains('<tbody data-sortable>', $html); contains('data-sort-order', $html); ok(!str_contains($html, 'type="number"'));
});
test('Admin UX: list View links, editor language select and the collapsed sidebar', function () {
    $f = adminFixture(); $db = $f['db']; $editor = new App\Services\ContentEditor($db);
    $_SESSION = ['user_id' => 1, 'session_version' => 1]; $_SERVER['REQUEST_METHOD'] = 'GET'; $_GET = [];
    $public = $editor->save(false, 0, 'en', ['slug' => 'open', 'title' => 'Open', 'status' => 'published', 'is_published' => '1']);
    $editor->save(false, 0, 'en', ['slug' => 'hidden', 'title' => 'Hidden', 'status' => 'published']);
    $editor->save(false, 0, 'en', ['slug' => 'draft', 'title' => 'Draft', 'status' => 'draft', 'is_published' => '1']);
    $html = $f['controller']->handle('/admin/posts');
    contains('href="/en/posts/open" target="_blank" rel="noopener">View ↗', $html); same(2, substr_count($html, 'Not public'));
    $editor->save(true, 0, 'en', ['slug' => 'about', 'title' => 'About', 'status' => 'published', 'is_published' => '1']);
    contains('href="/en/about" target="_blank"', $f['controller']->handle('/admin/pages'));
    $_GET = ['post' => $public, 'locale' => 'en', 'kind' => 'post'];
    $html = $f['controller']->handle('/admin/editor');
    contains('<select id="editor-language" name="locale"', $html); contains('en · Published · base', $html); contains('ja · Missing', $html);
    ok(!str_contains($html, 'aria-label="Translation language"')); ok(!str_contains($html, 'Deutsch')); contains('href="/en/posts/open" target="_blank"', $html);
    contains('data-slug-field', $html); ok(!preg_match('~name="slug"[^>]*required~', $html));
    $_GET = []; $_COOKIE = ['meteor_admin_nav' => 'collapsed'];
    contains('data-nav="collapsed"', $f['controller']->handle('/admin/posts'));
    $_COOKIE = ['meteor_admin_nav' => '"><script>']; contains('data-nav="expanded"', $f['controller']->handle('/admin/posts'));
    $_COOKIE = [];
});
test('Public: pages never show the table of contents; posts still do', function () {
    $app = publicApp(); $db = $app['db']; $editor = new App\Services\ContentEditor($db);
    $editor->save(true, 0, 'en', ['slug' => 'colophon', 'title' => 'Colophon', 'content_md' => "## One\n\n## Two", 'status' => 'published', 'is_published' => '1']);
    $html = publicRequest($app, '/en/colophon'); contains('Colophon', $html); ok(!str_contains($html, 'id="toc-title"'));
    $post = $editor->save(false, 0, 'en', ['slug' => 'with-toc', 'title' => 'With TOC', 'content_md' => "## One\n\n## Two", 'status' => 'published', 'is_published' => '1']);
    $db->query("UPDATE posts SET published_at = '2000-01-01 00:00:00' WHERE id = ?", [$post]);
    contains('id="toc-title"', publicRequest($app, '/en/posts/with-toc'));
});
