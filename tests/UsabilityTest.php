<?php
test('Auth: failed sign-ins are capped per address across usernames', function () {
    $db = database(); $auth = new Core\Auth($db);
    $db->query('INSERT INTO users (username, password_hash) VALUES (?, ?)', ['someone', password_hash('a long password', PASSWORD_DEFAULT)]);
    for ($i = 0; $i < Core\Auth::PER_ADDRESS - 1; $i++) { $auth->failure('user' . $i, '10.0.0.1'); }
    ok(!$auth->limited('someone', '10.0.0.1'));
    $auth->failure('another', '10.0.0.1');
    ok($auth->limited('someone', '10.0.0.1')); ok(!$auth->limited('someone', '10.0.0.2'));
    ok(!$auth->password('someone', 'a long password', '10.0.0.1'));
    $db->query('UPDATE login_attempts SET attempted_at = ?', [gmdate('Y-m-d H:i:s', time() - 901)]);
    ok(!$auth->limited('someone', '10.0.0.1'));
});

test('Admin HTTP: sign-in keeps the username, security headers, editor bars and the many-language switcher', function () {
    $c = new AdminHttpClient();
    try {
        // Five live languages: the public switcher becomes one menu instead of a row of pills.
        foreach (['fr' => 'Français', 'de' => 'Deutsch', 'es' => 'Español', 'it' => 'Italiano'] as $code => $native) {
            $c->db->query("INSERT INTO locales (code, name, native_name, status, sort_order) VALUES (?, ?, ?, 'live', 5)", [$code, $native, $native]);
        }
        [$h, $html] = $c->request('/en/');
        contains('<span class="sr-only">Language: </span><span lang="en">en</span>', $html); contains('<span lang="fr" dir="ltr">Français</span>', $html);
        contains('href="/en/" aria-current="page"', $html); ok(!str_contains($html, '<script'));
        contains('Strict-Transport-Security: max-age=31536000', $h); contains('Cross-Origin-Opener-Policy: same-origin', $h); contains('Permissions-Policy: camera=()', $h);
        $c->db->query("UPDATE locales SET status = 'off' WHERE code IN ('de', 'es', 'it')");
        [, $html] = $c->request('/en/'); ok(!str_contains($html, 'sr-only">Language: </span>')); contains('lang="fr"', $html);
        // A failed sign-in keeps the username (escaped) once and focuses the password.
        $token = $c->token();
        $c->request('/admin/login', 'POST', ['csrf_token' => $token, 'username' => 'Editor<x>', 'password' => 'wrong']);
        [, $html] = $c->request('/admin/login'); contains('value="Editor&lt;x&gt;"', $html); contains('required autofocus>', $html);
        [, $html] = $c->request('/admin/login'); ok(!str_contains($html, 'Editor&lt;x&gt;'));
        $c->request('/admin/login', 'POST', ['csrf_token' => $token, 'username' => 'editor', 'password' => 'a long password']);
        // New post: Save draft is the Ctrl+S default; each button exists once per breakpoint.
        [, $html] = $c->request('/admin/editor?kind=post');
        contains('data-save-state data-saved="0" aria-live="polite">Not saved yet', $html); same(2, substr_count($html, '>Save draft</button>')); same(2, substr_count($html, '>Publish</button>'));
        ok(preg_match('/value="draft"[^>]*data-save-default/', $html) === 1); contains('aria-describedby="editor-language-hint"', $html);
        // A published translation: Unpublish takes it off the site, Update is the Ctrl+S default.
        $c->db->query("INSERT INTO posts (slug, base_locale, is_published, published_at) VALUES ('hello', 'en', 1, '2026-01-01 00:00:00')");
        $c->db->query("INSERT INTO post_translations (post_id, locale, title, summary, content_md, content_html, toc_html, status, updated_at) VALUES (1, 'en', 'Hello', '', 'Hi', '<p>Hi</p>', '', 'published', '2026-10-01 09:30:00')");
        [, $html] = $c->request('/admin/editor?post=1&locale=en&kind=post');
        contains('>✓ Published<span class="sr-only"> · en</span>', $html); contains('Saved 2026-10-01 09:30 UTC', $html);
        same(2, substr_count($html, '>Unpublish</button>')); same(2, substr_count($html, '>Update</button>'));
        ok(preg_match('/value="published"[^>]*data-save-default/', $html) === 1); same(2, substr_count($html, '>Delete post…</a>'));
    } finally { $c->close(); }
});

test('Admin HTTP: posts list switches to compact language cells above six languages and pins the last column', function () {
    $c = new AdminHttpClient();
    try {
        $token = $c->token(); $c->request('/admin/login', 'POST', ['csrf_token' => $token, 'username' => 'editor', 'password' => 'a long password']);
        $c->db->query("INSERT INTO posts (slug, base_locale, is_published, published_at) VALUES ('hello', 'en', 1, '2026-01-01 00:00:00')");
        $c->db->query("INSERT INTO post_translations (post_id, locale, title, summary, content_md, content_html, toc_html, status) VALUES (1, 'en', 'Hello', '', 'Hi', '<p>Hi</p>', '', 'published')");
        [, $html] = $c->request('/admin/posts');
        contains('✓ Published', $html); contains('<th scope="col" class="sheet-end sheet-pin-end">Updated</th>', $html); ok(!str_contains($html, 'matrix-legend'));
        foreach (['fr', 'de', 'es', 'it', 'pt'] as $code) { $c->db->query("INSERT INTO locales (code, name, native_name, status, sort_order) VALUES (?, ?, ?, 'live', 5)", [$code, 'Name ' . $code, $code]); }
        [, $html] = $c->request('/admin/posts');
        contains('<table aria-describedby="matrix-legend">', $html); contains('<span class="sr-only">Published: en (base language)</span>', $html);
        contains('<span class="sr-only">Add Name fr</span>', $html); contains('<span class="sr-only">Name fr, live</span>', $html);
        ok(!str_contains($html, '✓ Published')); contains('View ↗', $html);
    } finally { $c->close(); }
});
