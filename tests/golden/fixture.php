<?php
declare(strict_types=1);

/** The baseline uses fixed content/dates, not the wall clock; only signed tokens and asset mtimes vary. */
function themeGoldenPages(): array
{
    Core\Dates::configure();
    $saved = [$_GET, $_POST, $_COOKIE, $_SERVER, $_SESSION ?? []];
    try {
        $_GET = $_POST = $_COOKIE = $_SESSION = [];
        $app = publicApp(true);
        $db = $app['db'];
        foreach (['posts_per_page' => '3', 'font_source' => 'system', 'copyright' => '© 2026 Meteor Team',
            'author_name' => 'Meteor Team', 'author_bio' => 'Notes from a quieter web.', 'author_links' => 'Website | https://example.org/',
            'footer_brand' => 'author', 'post_author_card' => '1', 'comments_enabled' => '1', 'comments_captcha' => '0',
            'app_secret' => str_repeat('a', 64), 'theme_switch' => '1', 'header_show_search' => '1'] as $key => $value) {
            $db->upsert('settings', ['key' => $key, 'locale' => null, 'value' => $value], ['key', 'locale_scope']);
        }
        $db->query('UPDATE posts SET cover_image = ?, comments_enabled = 1 WHERE id = ?', ['/uploads/golden-cover.webp', 1]);
        foreach (['posts', 'pages', 'post_translations', 'page_translations'] as $table) {
            $db->query("UPDATE $table SET updated_at = ?", ['2026-01-01 12:00:00']);
        }
        $db->query("UPDATE menu_items SET new_tab = 1 WHERE kind = 'rss'");
        (new App\Services\MenuEditor($db))->save('footer', ['items' => ['new' => ['url' => 'https://example.org/', 'new_tab' => '1', 'labels' => ['en' => 'Elsewhere']]]]);
        $db->query('INSERT INTO announcements (title, message, button_text, button_url) VALUES (?, ?, ?, ?)', ['Welcome', 'A small announcement.', 'Read on', '/{locale}/about']);
        $db->query('INSERT INTO announcements (title, message) VALUES (?, ?)', ['News', 'Another announcement.']);
        $db->query("INSERT INTO comments (post_id, locale, author_name, body, status, created_at) VALUES (1, 'en', 'Reader', 'Hello <world>.', 'approved', '2026-01-02 10:00:00')");
        $db->query("INSERT INTO comments (post_id, locale, parent_id, author_name, body, status, is_owner, created_at) VALUES (1, 'en', 1, 'Meteor Team', 'Thank you.', 'approved', 1, '2026-01-02 11:00:00')");
        $paths = ['home-en' => '/en/', 'home-zh-hans' => '/zh-hans/', 'archive' => '/en/posts',
            'category' => '/en/categories/engineering', 'tag' => '/en/tags/php', 'tags' => '/en/tags',
            'archive-page-2' => '/en/posts?page=2', 'post' => '/en/posts/building-a-zero-js-blog',
            'comments-reply' => '/en/posts/building-a-zero-js-blog?reply=1', 'page' => '/en/about',
            'search-results' => '/en/search?q=PHP', 'search-empty' => '/en/search?q=unfindablexyz',
            'not-found-zh-hans' => '/zh-hans/no/such/page', 'preview-locale' => '/ms/'];
        $pages = [];
        foreach ($paths as $name => $path) {
            $_SERVER['REQUEST_URI'] = $path;
            $pages[$name] = themeGoldenNormalize(publicRequest($app, $path));
        }
        $db->query("INSERT INTO locales (code, name, native_name, direction, status) VALUES ('ar', 'Arabic', 'العربية', 'rtl', 'live')");
        $i18n = new Core\I18n($db, require dirname(__DIR__, 2) . '/lang/en.php');
        $GLOBALS['i18n'] = $i18n;
        $site = new App\Controllers\Public\SiteController($db, $i18n, $app['view'], true);
        $_SERVER['REQUEST_URI'] = '/ar/';
        $pages['home-rtl'] = themeGoldenNormalize($site->home('ar'));
        $_SERVER['REQUEST_URI'] = '/en/posts/building-a-zero-js-blog/comments';
        $pages['comment-check'] = themeGoldenNormalize($site->render('comment-check', 'en', [
            'title' => 'Check your comment', 'captcha' => ['widget' => 'g-recaptcha', 'site_key' => 'golden-site-key', 'script' => 'https://www.google.com/recaptcha/api.js'],
            'postUrl' => '/en/posts/building-a-zero-js-blog', 'action' => '/en/posts/building-a-zero-js-blog/comments',
            'values' => ['name' => 'Reader', 'email' => '', 'url' => '', 'body' => 'Hello.', 'token' => 'golden', 'parent_id' => 0],
            'error' => '', 'noindex' => true, 'canonical' => null,
        ]));
        return $pages;
    } finally {
        [$_GET, $_POST, $_COOKIE, $_SERVER, $_SESSION] = $saved;
        Core\Dates::configure();
    }
}

function themeGoldenNormalize(string $html): string
{
    $html = preg_replace('~(name="(?:token|_csrf)" value=")[^"]*~', '$1{TOKEN}', $html);
    // asset() adds file mtimes, which differ across checkouts and change with every CSS build.
    return preg_replace('~(/assets/[^"?]+)\?v=\d+~', '$1?v={ASSET}', $html);
}
