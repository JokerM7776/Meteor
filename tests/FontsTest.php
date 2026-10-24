<?php
use App\Services\Fonts;

test('Fonts: source setting, fallback to Google while local files are missing, and preload list', function () {
    $public = dirname(__DIR__) . '/storage/test-fonts-' . bin2hex(random_bytes(6));
    mkdir($public . '/assets/fonts', 0700, true);
    try {
        same('local', Fonts::source('')); same('local', Fonts::source('elsewhere')); same('system', Fonts::source('system'));
        same(['google', []], Fonts::resolve('local', $public));
        same(['google', []], Fonts::resolve('google', $public)); same(['system', []], Fonts::resolve('system', $public));
        file_put_contents($public . '/assets/fonts/fonts.css', '@font-face{}');
        file_put_contents($public . '/assets/fonts/a.1234abcd.woff2', 'wOF2');
        file_put_contents($public . '/assets/fonts/fonts.json', json_encode(['files' => 2, 'preload' => ['a.1234abcd.woff2', 'missing.woff2', '../x.woff2', 7]]));
        $other = $public . '-copy'; rename($public, $other); $public = $other; // the manifest is cached per directory
        same(['local', ['a.1234abcd.woff2']], Fonts::resolve('local', $public));
        $db = database();
        throws(fn () => Fonts::save($db, ['font_source' => 'cdn']), InvalidArgumentException::class);
        Fonts::save($db, ['font_source' => 'system']); same('system', $db->one("SELECT value FROM settings WHERE `key` = 'font_source'")['value']);
    } finally { Core\ReleasePackage::removeTree($public); }
});

test('Fonts HTTP: head links for each source and the Optimization setting', function () {
    $c = new AdminHttpClient();
    $fonts = $c->root . '/public/assets/fonts';
    try {
        if (is_dir($fonts)) { Core\ReleasePackage::removeTree($fonts); }
        [, $html] = $c->request('/en/'); contains('<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Source+Sans+3', $html); contains('<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>', $html);

        mkdir($fonts, 0755, true);
        file_put_contents($fonts . '/fonts.css', '@font-face{}'); file_put_contents($fonts . '/sans.0123abcd.woff2', 'wOF2');
        file_put_contents($fonts . '/fonts.json', json_encode(['files' => 1, 'preload' => ['sans.0123abcd.woff2']]));
        [, $html] = $c->request('/en/'); ok(!str_contains($html, 'googleapis'), 'Local fonts need no Google link');
        contains('<link rel="preload" href="/assets/fonts/sans.0123abcd.woff2" as="font" type="font/woff2" crossorigin>', $html);
        ok(preg_match('~<link rel="stylesheet" href="/assets/fonts/fonts\.css\?v=\d+">~', $html) === 1, 'Local font stylesheet');

        $token = $c->token(); $c->request('/admin/login', 'POST', ['csrf_token' => $token, 'username' => 'editor', 'password' => 'a long password']);
        $token = $c->token('/admin/optimization');
        [, $html] = $c->request('/admin/optimization'); contains('name="font_source" value="local" checked', $html); ok(!str_contains($html, 'The font files are not on this server yet'));
        [$h] = $c->request('/admin/optimization', 'POST', ['csrf_token' => $token, 'section' => 'fonts', 'font_source' => 'nope']); contains('Location: /admin/optimization', $h);
        [, $html] = $c->request('/admin/optimization'); contains('Choose where fonts load from.', $html);
        $c->request('/admin/optimization', 'POST', ['csrf_token' => $token, 'section' => 'fonts', 'font_source' => 'system']);
        same('system', $c->db->one("SELECT value FROM settings WHERE `key` = 'font_source'")['value']);
        [, $html] = $c->request('/admin/optimization'); contains('name="font_source" value="system" checked', $html); ok(!str_contains($html, 'fonts.googleapis.com/css2'), 'Admin follows the setting');
        $c->cookie = '';
        [, $html] = $c->request('/en/'); ok(!str_contains($html, 'googleapis') && !str_contains($html, '/assets/fonts/'), 'System fonts load nothing');
    } finally { $c->close(); }
});
