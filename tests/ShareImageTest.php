<?php
test('Share image: post cover first, then the default image when turned on', function () {
    $app = publicApp(); $db = $app['db'];
    $cover = str_repeat('a', 32) . '.webp'; $default = str_repeat('b', 32) . '.jpg';
    $db->query("INSERT INTO media (file_name, original_name, mime_type, width, height, alt_text, search_text) VALUES (?, 'c.webp', 'image/webp', 2400, 1200, 'Code on a <screen>', ''), (?, 'd.jpg', 'image/jpeg', 1200, 630, '', '')", [$cover, $default]);
    $db->query('UPDATE posts SET cover_image = ? WHERE slug = ?', ['/uploads/media/' . $cover, 'building-a-zero-js-blog']);
    $html = publicRequest($app, '/en/posts/building-a-zero-js-blog');
    contains('<meta property="og:image" content="https://meteor.example/uploads/media/' . $cover . '">', $html);
    contains('<meta property="og:image:width" content="2400">', $html); contains('<meta property="og:image:height" content="1200">', $html);
    contains('<meta property="og:image:type" content="image/webp">', $html); contains('content="Code on a &lt;screen&gt;"', $html);
    contains('<meta name="twitter:card" content="summary_large_image">', $html);
    // Without the default turned on, other pages share no picture.
    $html = publicRequest($app, '/en/');
    ok(!str_contains($html, 'og:image')); contains('<meta name="twitter:card" content="summary">', $html);
    $settings = new App\Services\AdminSettings($db);
    throws(fn () => $settings->save(['share_image' => '/uploads/media/' . str_repeat('c', 32) . '.png']), InvalidArgumentException::class);
    throws(fn () => $settings->save(['share_image' => 'https://elsewhere.test/x.jpg']), InvalidArgumentException::class);
    $settings->save(['share_image' => '/uploads/media/' . $default, 'share_image_default' => '0']);
    ok(!str_contains(publicRequest($app, '/en/'), 'og:image'), 'stored but not used');
    $settings->save(['share_image_default' => '1']);
    foreach (['/en/', '/en/posts', '/en/posts/markdown-parser-150-lines'] as $path) {
        contains('<meta property="og:image" content="https://meteor.example/uploads/media/' . $default . '">', publicRequest($app, $path));
    }
    contains('og:image" content="https://meteor.example/uploads/media/' . $cover, publicRequest($app, '/en/posts/building-a-zero-js-blog'));
    ok(!str_contains(publicRequest($app, '/en/no-such-page'), 'og:image'), 'not on error pages');
    $settings->save(['share_image' => '']);
    ok(!str_contains(publicRequest($app, '/en/'), 'og:image'));
});
