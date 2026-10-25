<?php
function publicApp(bool $admin = false, bool $demo = true): array {
    $db = database();
    (new App\Services\DemoSeeder($db))->run($demo);
    $i18n = new Core\I18n($db, require dirname(__DIR__) . '/lang/en.php');
    $GLOBALS['i18n'] = $i18n;
    $GLOBALS['config'] = ['app_url' => 'https://meteor.example'];
    $view = new Core\View(dirname(__DIR__) . '/views');
    $router = new Core\Router($i18n->enabledLocales($admin));
    $isAdmin = $admin;
    require dirname(__DIR__) . '/app/routes.php';
    return compact('db', 'i18n', 'view', 'router', 'site', 'feeds');
}
function publicRequest(array $app, string $uri): string {
    parse_str((string) parse_url($uri, PHP_URL_QUERY), $_GET);
    http_response_code(200);
    return $app['router']->dispatch('GET', $uri);
}
test('Public: default seed is English only, idempotent and compiled', function () {
    $app = publicApp(false, false); $db = $app['db'];
    (new App\Services\DemoSeeder($db))->run();
    same(1, count($db->all('SELECT * FROM locales')));
    same(7, count($app['site']->posts->published('en')));
    same(7, count($db->all('SELECT * FROM post_translations')));
    same(1, count($app['site']->pages->published('en')));
    same(4, count($app['site']->taxonomy->categories('en')));
    same(7, count($app['site']->taxonomy->tags('en')));
    contains('syntax-keyword', $app['site']->posts->published('en')[0]['content_html']);
    (new App\Services\DemoSeeder($db))->run(true);
    (new App\Services\DemoSeeder($db))->run(true);
    same(3, count($db->all('SELECT * FROM locales')));
    same(16, count($db->all('SELECT * FROM post_translations')));
});
test('Public: all HTML routes render without scripts and with root-relative navigation', function () {
    $app = publicApp();
    $routes = ['/en/', '/en/posts', '/en/tags', '/en/about', '/zh-hans/', '/zh-hans/about'];
    foreach ($app['site']->posts->published('en') as $post) { $routes[] = '/en/posts/' . $post['slug']; }
    foreach ($app['site']->taxonomy->categories('en') as $term) { $routes[] = '/en/categories/' . $term['slug']; }
    foreach ($app['site']->taxonomy->tags('en') as $term) { $routes[] = '/en/tags/' . $term['slug']; }
    foreach ($routes as $route) {
        $html = publicRequest($app, $route);
        same(200, http_response_code());
        ok(!str_contains(strtolower($html), '<script'), $route);
        ok(preg_match('~href="/assets/style\.css\?v=\d+"~', $html) === 1, 'versioned style.css');
        ok(!preg_match('~<a[^>]+href="https://meteor.example~', $html));
        ok(!str_contains($html, 'href="/ms/'));
    }
});
test('Public: archive filters fold with a CSS-only View all toggle', function () {
    $app = publicApp();
    $html = publicRequest($app, '/en/posts');
    contains('<input type="checkbox" id="more-tags" class="chip-toggle sr-only"><div class="chip-rows', $html);
    contains('<label for="more-tags" class="chip-more', $html); contains('View all · 7</span>', $html); contains('View all · 5</span>', $html);
    contains('<label for="more-categories" class="chip-less button-secondary mt-2 rounded-full">Show less</label>', $html);
    contains('href="/en/posts" aria-current="page">All</a>', $html);
    $html = publicRequest($app, '/en/tags/' . $app['site']->taxonomy->tags('en')[0]['slug']);
    contains('class="tag" href="/en/tags/' . $app['site']->taxonomy->tags('en')[0]['slug'] . '" aria-current="page">#', $html);
    ok(!str_contains(strtolower($html), '<script'));
});
test('Public: canonical, live alternates, translation switcher and metadata escaping', function () {
    $app = publicApp();
    $html = publicRequest($app, '/en/posts/building-a-zero-js-blog');
    contains('<link rel="canonical" href="https://meteor.example/en/posts/building-a-zero-js-blog">', $html);
    contains('hreflang="zh-hans" href="https://meteor.example/zh-hans/posts/building-a-zero-js-blog"', $html);
    contains('hreflang="x-default" href="https://meteor.example/en/posts/building-a-zero-js-blog"', $html);
    contains('href="/zh-hans/posts/building-a-zero-js-blog" lang="zh-hans"', $html);
    ok(!str_contains($html, 'hreflang="ms"'));
    $html = publicRequest($app, '/en/posts/markdown-parser-150-lines');
    contains('href="/zh-hans/" lang="zh-hans"', $html);
    ok(!str_contains($html, 'hreflang="zh-hans"'));
    $html = publicRequest($app, '/zh-hans/about');
    contains('hreflang="en" href="https://meteor.example/en/about"', $html);
    $app['db']->query('UPDATE post_translations SET title = ?, summary = ? WHERE locale = ?', ['<script>alert("x")</script>', '<script>summary</script>', 'en']);
    $html = publicRequest($app, '/en/posts/building-a-zero-js-blog');
    contains('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', $html);
    contains('content="&lt;script&gt;summary&lt;/script&gt;"', $html);
    ok(!str_contains($html, '<script'));
});
test('Public: preview/off/draft visibility and same-locale neighbors', function () {
    $app = publicApp();
    foreach (['/ms/', '/ms/posts/building-a-zero-js-blog', '/ms/rss.xml'] as $path) { publicRequest($app, $path); same(404, http_response_code()); }
    $app = publicApp(true);
    $html = publicRequest($app, '/ms/posts/building-a-zero-js-blog');
    same(200, http_response_code()); contains('content="noindex, nofollow"', $html);
    ok(!str_contains($html, 'hreflang=')); ok(!str_contains($html, '/ms/rss.xml'));
    contains('rel="prev" href="/ms/posts/compile-markdown-on-save"', $html);
    publicRequest($app, '/ms/rss.xml'); same(404, http_response_code());
    $app['db']->query("UPDATE post_translations SET status = 'draft' WHERE locale = ? AND post_id = ?", ['zh-hans', 1]);
    publicRequest($app, '/zh-hans/posts/building-a-zero-js-blog'); same(404, http_response_code());
    $app['db']->query("UPDATE posts SET is_published = 0 WHERE id = ?", [1]);
    publicRequest($app, '/en/posts/building-a-zero-js-blog'); same(404, http_response_code());
    $app['db']->query('UPDATE posts SET published_at = ? WHERE id = ?', ['2999-01-01 00:00:00', 2]);
    publicRequest($app, '/en/posts/compile-markdown-on-save'); same(404, http_response_code());
});
test('Public: pagination bounds, filters and canonical page queries', function () {
    $app = publicApp();
    $app['db']->upsert('settings', ['key' => 'posts_per_page', 'locale' => null, 'value' => '3'], ['key', 'locale_scope']);
    $html = publicRequest($app, '/en/posts?page=2');
    same(200, http_response_code()); contains('rel="prev" href="/en/posts"', $html);
    contains('rel="canonical" href="https://meteor.example/en/posts?page=2"', $html);
    publicRequest($app, '/en/posts?page=3'); same(200, http_response_code());
    foreach (['0', '-1', '4', '999999999999999999999', '1.5', 'abc', ''] as $page) { publicRequest($app, '/en/posts?page=' . $page); same(404, http_response_code()); }
    publicRequest($app, '/en/posts?page[]=1'); same(404, http_response_code());
    $html = publicRequest($app, '/en/categories/travel');
    same(200, http_response_code()); contains('Kuala Lumpur', $html); ok(!str_contains($html, 'Building a zero-JS'));
    publicRequest($app, '/en/tags/missing'); same(404, http_response_code());
});
test('Public: XML RSS and sitemap contain only published live translations', function () {
    $app = publicApp();
    $app['db']->query('UPDATE post_translations SET title = ?, summary = ? WHERE post_id = ? AND locale = ?', ['A & B < C', '"Quoted" & <escaped>', 1, 'en']);
    $rss = publicRequest($app, '/en/rss.xml');
    $xml = simplexml_load_string($rss); ok($xml !== false); same('2.0', (string) $xml['version']);
    same(7, count($xml->channel->item)); same('A & B < C', (string) $xml->channel->item[0]->title);
    contains('https://meteor.example/en/posts/', (string) $xml->channel->item[0]->link);
    $sitemap = publicRequest($app, '/sitemap.xml');
    $xml = simplexml_load_string($sitemap); ok($xml !== false); same(16, count($xml->url));
    ok(!str_contains($sitemap, '/ms/')); contains('xhtml:link', $sitemap);
    $xml->registerXPathNamespace('h', 'http://www.w3.org/1999/xhtml'); ok(count($xml->xpath('//h:link[@hreflang="x-default"]')) > 0);
    $app['db']->query("UPDATE post_translations SET status = 'draft' WHERE locale = ?", ['zh-hans']);
    same(0, count(simplexml_load_string(publicRequest($app, '/zh-hans/rss.xml'))->channel->item));
});
test('Public: localized 404s, reserved page slugs and trailing slash redirects', function () {
    $app = publicApp();
    $html = publicRequest($app, '/zh-hans/no/such/page'); same(404, http_response_code()); contains('页面不存在', $html); contains('<html lang="zh-hans"', $html);
    foreach (['/unknown/path', '/en/assets', '/en/rss.xml/no', '/en/404', '/en/posts/a%2fb'] as $path) {
        $html = publicRequest($app, $path); same(404, http_response_code()); contains('This page burned up on entry.', $html);
    }
    foreach (['/en' => '/en/', '/zh-hans' => '/zh-hans/', '/en/posts/' => '/en/posts', '/en/about/?x=1' => '/en/about?x=1', '/en/posts/test/' => '/en/posts/test'] as $from => $to) {
        $route = $app['router']->match('GET', $from); same(301, $route['status']); same($to, $route['location']);
    }
    same(404, $app['router']->match('GET', '/ms')['status']);
});
test('Public: localized settings fallback and custom CSS response', function () {
    $app = publicApp();
    $db = $app['db'];
    foreach (['custom_css' => 'body { color: red; }', 'copyright' => '© Demo & Co'] as $key => $value) { $db->upsert('settings', compact('key', 'value') + ['locale' => null], ['key', 'locale_scope']); }
    $db->upsert('settings', ['key' => 'home_heading', 'locale' => 'en', 'value' => 'Default intro'], ['key', 'locale_scope']);
    (new App\Services\MenuEditor($db))->save('header', ['items' => [$db->one("SELECT id FROM menu_items WHERE menu = 'header' AND kind = 'posts'")['id'] => ['labels' => ['zh-hans' => '所有文章']]]]);
    $html = publicRequest($app, '/zh-hans/'); contains('Default intro', $html); contains('所有文章', $html); contains('© Demo &amp; Co', $html);
    contains('href="/custom.css"', $html);
    same('body { color: red; }', publicRequest($app, '/custom.css'));
    $_SERVER['HTTP_IF_NONE_MATCH'] = '"' . hash('sha256', 'body { color: red; }') . '"';
    same('', publicRequest($app, '/custom.css')); same(304, http_response_code()); unset($_SERVER['HTTP_IF_NONE_MATCH']);
});
test('Public: configurable RTL and off locales, reserved pages and RSS limit', function () {
    $app = publicApp(); $db = $app['db'];
    $db->upsert('locales', ['code' => 'ar', 'name' => 'Arabic', 'native_name' => 'العربية', 'status' => 'live', 'direction' => 'rtl'], ['code']);
    $db->query("UPDATE locales SET status = 'off' WHERE code = ?", ['zh-hans']);
    $i18n = new Core\I18n($db, require dirname(__DIR__) . '/lang/en.php'); $GLOBALS['i18n'] = $i18n;
    $site = new App\Controllers\Public\SiteController($db, $i18n, $app['view'], true);
    $html = $site->home('ar'); contains('<html lang="ar" dir="rtl" data-theme="auto">', $html); ok(!str_contains($html, '/zh-hans/'));
    $db->upsert('pages', ['slug' => 'assets', 'base_locale' => 'en', 'is_published' => 1], ['slug']);
    $id = (int) $db->one('SELECT id FROM pages WHERE slug = ?', ['assets'])['id'];
    $db->upsert('page_translations', ['page_id' => $id, 'locale' => 'en', 'title' => 'Reserved', 'summary' => '', 'content_md' => '', 'content_html' => '', 'toc_html' => '', 'status' => 'published'], ['page_id', 'locale']);
    same(null, $site->pages->find('en', 'assets'));
    for ($n = 0; $n < 22; $n++) {
        $id = $db->insert('INSERT INTO posts (slug, base_locale, is_published, published_at) VALUES (?, ?, ?, ?)', ['extra-' . $n, 'en', 1, '2026-01-01 00:00:00']);
        $db->upsert('post_translations', ['post_id' => $id, 'locale' => 'en', 'title' => 'Extra ' . $n, 'summary' => '', 'content_md' => '', 'content_html' => '', 'toc_html' => '', 'status' => 'published'], ['post_id', 'locale']);
    }
    same(20, count(simplexml_load_string($app['feeds']->rss('en'))->channel->item));
});
test('Public: identity, header and footer customization without scripts', function () {
    $app = publicApp(); $db = $app['db']; $db->query('DELETE FROM settings'); $db->query('DELETE FROM menu_items');
    $_SERVER['REQUEST_URI'] = '/en/';
    $html = publicRequest($app, '/en/'); contains('<link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">', $html);
    preg_match('~<header\b.*?</header>~s', $html, $header);
    foreach (['/en/posts', '/en/tags', '/en/rss.xml'] as $path) { contains('href="' . $path . '"', $header[0]); }
    contains('Site</h2>', $html); contains('href="/sitemap.xml"', $html);
    $set = function ($key, $value, $locale = null) use ($db) { $db->upsert('settings', compact('key', 'value', 'locale'), ['key', 'locale_scope']); };
    $icon = '/uploads/' . str_repeat('a', 32) . '.png'; $set('site_icon', $icon);
    $set('site_title', 'Custom & title', 'en'); $set('site_tagline', 'A tagline', 'en');
    $set('footer_text', 'Footer <text>', 'en'); $set('copyright', '© {year} Custom', 'en'); $set('footer_menu_heading', 'More & more', 'en');
    $menus = new App\Repositories\Menus($db); $menus->ensureDefaults(); $editor = new App\Services\MenuEditor($db);
    $editor->save('header', ['items' => ['new' => ['url' => '/{locale}/?a=1&b=2', 'labels' => ['en' => '<Header>']]]]);
    $editor->save('header', ['items' => ['new' => ['url' => '/en/', 'labels' => ['zh-hans' => '只在中文']]]]);
    $editor->save('header', ['items' => ['new' => ['url' => '/{locale}/', 'sort_order' => '-1', 'labels' => ['en' => 'Here']]]]);
    $editor->save('footer', ['items' => ['new' => ['url' => 'HTTPS://example.com/?a=1&b=2', 'labels' => ['en' => '<Footer>']]]]);
    $db->query("UPDATE menu_items SET url = 'javascript:alert(1)' WHERE url = '/en/'");
    $html = publicRequest($app, '/en/');
    contains('<link rel="icon" href="' . $icon . '" type="image/png">', $html); contains('<link rel="apple-touch-icon" href="' . $icon . '">', $html);
    same(2, substr_count($html, '<img src="' . $icon)); ok(!str_contains($html, 'viewBox="0 0 28 28"'));
    contains('A tagline', $html); contains('Footer &lt;text&gt;', $html); contains('© ' . gmdate('Y') . ' Custom', $html); contains('More &amp; more</h2>', $html);
    contains('&lt;Header&gt;', $html); contains('href="/en/?a=1&amp;b=2"', $html); contains('href="/en/" aria-current="page">Here', $html);
    ok(strpos($html, '>Here<') < strpos($html, 'href="/en/posts"'), 'Order follows sort_order');
    contains('rel="noopener">&lt;Footer&gt;', $html); ok(!str_contains($html, 'javascript:')); ok(!str_contains($html, '<script'));
    $zh = publicRequest($app, '/zh-hans/'); contains('href="/zh-hans/?a=1&amp;b=2"', $zh); contains('&lt;Header&gt;', $zh); ok(!str_contains($zh, '只在中文'));
    foreach (['header_show_title', 'header_show_switcher', 'footer_show_languages', 'footer_show_feeds', 'footer_show_site'] as $key) { $set($key, '0'); }
    $db->query("UPDATE menu_items SET is_visible = 0 WHERE menu = 'header' AND (kind <> 'link' OR page_id IS NOT NULL)");
    $html = publicRequest($app, '/en/'); contains('<span class="sr-only">Custom &amp; title</span>', $html);
    preg_match('~<header\b.*?</header>~s', $html, $header);
    foreach (['/en/posts', '/en/tags', '/en/about', '/en/rss.xml', 'aria-label="Language"'] as $hidden) { ok(!str_contains($header[0], $hidden), $hidden); }
    foreach (['Read in</h2>', 'Feeds</h2>', 'More &amp; more</h2>'] as $hidden) { ok(!str_contains($html, $hidden), $hidden); }
    $set('site_icon', '/uploads/' . str_repeat('b', 32) . '.ico'); ok(!str_contains(publicRequest($app, '/en/'), 'apple-touch-icon'));
    unset($_SERVER['REQUEST_URI']);
});
test('Public: page links with labels, new-tab links and preview RSS', function () {
    $app = publicApp(true); $db = $app['db'];
    $db->query("UPDATE pages SET is_published = 1 WHERE slug = 'about'");
    $about = (int) $db->one("SELECT id FROM menu_items WHERE menu = 'header' AND page_id = (SELECT id FROM pages WHERE slug = 'about')")['id'];
    $db->query('INSERT INTO menu_item_labels (item_id, locale, label) VALUES (?, ?, ?)', [$about, 'en', 'Who we are']);
    $header = function (string $path) use ($app): string { preg_match('~<header\b.*?</header>~s', publicRequest($app, $path), $m); return $m[0]; };
    $html = $header('/en/');
    contains('Who we are', $html); same(2, substr_count($html, 'href="/en/about"')); ok(!str_contains($html, 'target="_blank"'));
    $db->query('UPDATE menu_items SET new_tab = 1 WHERE id = ?', [$about]);
    $html = $header('/en/');
    same(2, substr_count($html, 'href="/en/about" target="_blank" rel="noopener"')); contains('<span class="sr-only"> (opens in a new tab)</span>', $html); same(2, substr_count($html, 'M10 3.25h4.75V8'));
    $db->upsert('settings', ['key' => 'header_new_tab_icon', 'locale' => null, 'value' => '0'], ['key', 'locale_scope']);
    $html = $header('/en/'); ok(!str_contains($html, 'M10 3.25h4.75V8')); contains('(opens in a new tab)', $html);
    ok(!str_contains($header('/ms/'), 'rss.xml'));
});
test('Migration 010: the automatic Pages item becomes ordered page links with their navigation labels', function () {
    $db = database(); locale($db); locale($db, 'ms', 'live', 0, 1);
    $db->query('ALTER TABLE pages ADD COLUMN show_in_nav INTEGER NOT NULL DEFAULT 0');
    $db->query('ALTER TABLE page_translations ADD COLUMN nav_label VARCHAR(191)');
    $db->query('ALTER TABLE menu_items DROP COLUMN new_tab');
    foreach (['a' => 2, 'b' => 1, 'c' => 0] as $slug => $order) {
        $id = $db->insert('INSERT INTO pages (slug, base_locale, is_published, sort_order, show_in_nav) VALUES (?, ?, 1, ?, ?)', [$slug, 'en', $order, $slug === 'c' ? 0 : 1]);
        $db->query("INSERT INTO page_translations (page_id, locale, title, summary, content_md, content_html, toc_html, status, nav_label) VALUES (?, 'en', ?, '', '', '', '', 'published', ?)", [$id, strtoupper($slug), $slug === 'a' ? 'Nav A' : null]);
    }
    foreach (['posts', 'pages', 'rss'] as $n => $kind) { $db->query("INSERT INTO menu_items (menu, kind, sort_order, is_visible) VALUES ('header', ?, ?, 1)", [$kind, $n]); }
    $db->query("INSERT INTO menu_items (menu, kind, sort_order, is_visible) VALUES ('footer', 'pages', 0, 0)");
    $migration = require dirname(__DIR__) . '/database/migrations/010_menu_page_links_and_new_tab.php';
    $migration($db); $migration($db);
    $rows = (new App\Repositories\Menus($db))->rows('header');
    same(['posts', 'link', 'link', 'rss'], array_column($rows, 'kind'));
    same(['b', 'a'], array_map(fn ($r) => $db->one('SELECT slug FROM pages WHERE id = ?', [$r['page_id']])['slug'], array_slice($rows, 1, 2)));
    same(['en' => 'Nav A'], $rows[2]['labels']); same(0, (int) $rows[2]['new_tab']);
    same(0, (int) $db->one("SELECT COUNT(*) AS n FROM menu_items WHERE kind = 'pages'")['n']);
    same([0, 0], array_map('intval', array_column($db->all("SELECT is_visible FROM menu_items WHERE menu = 'footer'"), 'is_visible')));
});
test('Public: optional author card ends posts as the h-entry p-author h-card', function () {
    $app = publicApp(); $db = $app['db'];
    $slug = $app['site']->posts->published('en')[0]['slug'];
    $post = fn () => publicRequest($app, '/en/posts/' . $slug);
    $html = $post();
    contains('class="h-entry ', $html); contains('class="p-name ', $html); contains('class="e-content ', $html);
    contains('class="dt-published"', $html); contains('<data class="u-url" value="https://meteor.example/en/posts/' . $slug . '">', $html);
    ok(!str_contains($html, 'p-author'), 'Off by default');
    preg_match('~e-content.*?</article>\s*(.*?)<nav aria-label="More posts"~s', $html, $after);
    contains('border-t', $after[1] ?? ''); contains('class="tag"', $after[1] ?? '');
    $author = new App\Services\Author($db);
    $author->save(['text' => ['author_name' => ['en' => 'Ada <Writer>']], 'author_links' => 'https://example.com/ada', 'post_author_card' => '1']);
    $html = $post();
    contains('class="p-author h-card ', $html); contains('About the author</h2>', $html); contains('>Ada &lt;Writer&gt;</a>', $html);
    contains('rel="me" href="https://meteor.example/en/"', $html); contains('rel="me noopener" href="https://example.com/ada"', $html);
    ok(strpos($html, 'p-author') > strpos($html, '</article>') && strpos($html, 'p-author') < strpos($html, 'aria-label="More posts"'), 'Card sits between the article and Previous/Next');
    ok(!str_contains(publicRequest($app, '/en/about'), 'p-author'), 'Pages have no author card');
    $author->save(['text' => [], 'author_links' => 'https://example.com/ada']);
    contains('p-author', $post()); // a save without the field keeps the choice
    $author->save(['text' => [], 'author_links' => '', 'post_author_card' => '0']);
    ok(!str_contains($post(), 'p-author')); ok(!str_contains($post(), '<script'));
});
test('Public: post cards show a Read link and the other languages as text', function () {
    $app = publicApp();
    $rows = array_map(fn ($c) => ['code' => $c, 'native_name' => strtoupper($c) . '<'], ['a', 'b', 'c', 'd', 'e']);
    same('<span lang="a">A&lt;</span>', language_list(array_slice($rows, 0, 1)));
    same('<span lang="a">A&lt;</span> and <span lang="b">B&lt;</span>', language_list(array_slice($rows, 0, 2)));
    same('<span lang="a">A&lt;</span>, <span lang="b">B&lt;</span> and <span lang="c">C&lt;</span>', language_list(array_slice($rows, 0, 3)));
    same('<span lang="a">A&lt;</span>, <span lang="b">B&lt;</span> and 3 more languages', language_list($rows));
    $html = publicRequest($app, '/en/');
    contains('Also available in <span lang="zh-hans">简体中文</span>', $html);
    contains('Read<span class="sr-only">: ', $html);
    ok(!str_contains($html, 'href="/zh-hans/posts/'));
});
