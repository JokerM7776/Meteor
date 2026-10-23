<?php
use App\Services\MediaLibrary;
use Core\ImageOptimizer;

function mediaRoot(): string
{
    $root = dirname(__DIR__) . '/storage/test-media-' . bin2hex(random_bytes(6));
    mkdir($root . '/public', 0700, true);
    return $root;
}
function mediaImage(string $root, string $type, int $width, int $height): string
{
    $image = imagecreatetruecolor($width, $height);
    imagealphablending($image, false); imagesavealpha($image, true);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 200, 80, 20, $type === 'png' ? 60 : 0));
    imagefilledrectangle($image, 0, 0, intdiv($width, 2), intdiv($height, 2), imagecolorallocate($image, 20, 40, 160));
    $path = $root . '/source-' . bin2hex(random_bytes(4)) . '.' . $type;
    match ($type) { 'png' => imagepng($image, $path), 'jpg' => imagejpeg($image, $path, 95), 'gif' => imagegif($image, $path) };
    return $path;
}
function animatedGif(string $root): string
{
    // Two frames, each with its own graphic control extension.
    $frame = fn () => "\x21\xF9\x04\x00\x0A\x00\x00\x00\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00";
    $bytes = "GIF89a\x01\x00\x01\x00\x80\x00\x00\xFF\x00\x00\x00\x00\xFF" . "\x21\xFF\x0BNETSCAPE2.0\x03\x01\x00\x00\x00" . $frame() . $frame() . "\x3B";
    file_put_contents($path = $root . '/animated.gif', $bytes);
    return $path;
}

test('Media: optimized upload, random names, thumbnail, metadata and files on disk', function () {
    if (!ImageOptimizer::available('webp')) { return; }
    $root = mediaRoot(); $db = database(); locale($db);
    try {
        $media = new MediaLibrary($db, $root . '/public');
        same(['optimize' => true, 'format' => 'webp', 'quality' => 82, 'max_size' => 2560], $media->options());
        $row = $media->import(mediaImage($root, 'jpg', 3000, 1500), 'My Holiday Photo.jpg', ['alt' => '  Sunset  over  the bay ', 'category' => 'Travel', 'tags' => 'beach, Sunset, sunset, , bay']);
        same('image/webp', $row['mime_type']); same([2560, 1280], [(int) $row['width'], (int) $row['height']]);
        ok((bool) preg_match('/^[a-f0-9]{32}\.webp$/D', $row['file_name']), 'Random file name'); ok((bool) preg_match('/^[a-f0-9]{32}\.webp$/D', $row['thumb_name']));
        ok($row['file_name'] !== $row['thumb_name']); ok(!str_contains($row['file_name'], 'holiday'));
        same('Sunset over the bay', $row['alt_text']); same('Travel', $row['category']); same(['bay', 'beach', 'Sunset'], $row['tags']);
        same('My Holiday Photo.jpg', $row['original_name']); same('', $row['note']);
        $file = $root . '/public/uploads/media/' . $row['file_name'];
        same('image/webp', (new finfo(FILEINFO_MIME_TYPE))->file($file)); same(filesize($file), (int) $row['size_bytes']);
        same([400, 200], array_slice(getimagesize($root . '/public/uploads/media/' . $row['thumb_name']), 0, 2));
        same(['id' => (int) $row['id'], 'url' => '/uploads/media/' . $row['file_name'], 'thumb' => '/uploads/media/' . $row['thumb_name'], 'name' => 'My Holiday Photo.jpg',
            'alt' => 'Sunset over the bay', 'width' => 2560, 'height' => 1280, 'category' => 'Travel', 'tags' => ['bay', 'beach', 'Sunset']], MediaLibrary::json($row));
        $png = $media->import(mediaImage($root, 'png', 120, 80), '../../evil<name>.png', []);
        same('image/webp', $png['mime_type']); same(null, $png['thumb_name']); same('evil<name>.png', $png['original_name']);
        $alpha = imagecreatefromwebp($root . '/public/uploads/media/' . $png['file_name']);
        ok((imagecolorat($alpha, 100, 70) >> 24) > 0, 'PNG transparency survives conversion');
        $media->delete((int) $row['id']);
        ok(!is_file($file)); ok(!is_file($root . '/public/uploads/media/' . $row['thumb_name'])); same(null, $media->find((int) $row['id']));
        same([], $db->all('SELECT * FROM media_tags WHERE media_id = ?', [$row['id']]));
    } finally { Core\ReleasePackage::removeTree($root); }
});
test('Media: optimization off keeps the original bytes; animated GIFs and invalid files', function () {
    $root = mediaRoot(); $db = database(); locale($db);
    try {
        $media = new MediaLibrary($db, $root . '/public');
        $media->saveOptions(['media_optimize' => '0', 'media_format' => 'webp', 'media_quality' => '70', 'media_max_size' => '1280']);
        same(['optimize' => false, 'format' => 'webp', 'quality' => 70, 'max_size' => 1280], $media->options());
        $source = mediaImage($root, 'jpg', 1600, 900);
        $row = $media->import($source, 'big.jpg', []);
        same('image/jpeg', $row['mime_type']); ok(str_ends_with($row['file_name'], '.jpg'));
        same(hash_file('sha256', $source), hash_file('sha256', $root . '/public/uploads/media/' . $row['file_name'])); same([1600, 900], [(int) $row['width'], (int) $row['height']]);
        $media->saveOptions(['media_optimize' => '1', 'media_format' => 'webp', 'media_quality' => '82', 'media_max_size' => '2560']);
        $gif = $media->import(animatedGif($root), 'moving.gif', []);
        same('image/gif', $gif['mime_type']); same('Animated GIFs are kept as they are.', $gif['note']);
        file_put_contents($root . '/fake.jpg', '<?php echo 1;');
        throws(fn () => $media->import($root . '/fake.jpg', 'fake.jpg', []), InvalidArgumentException::class);
        throws(fn () => $media->import($source, 'x.jpg', ['tags' => implode(',', range(1, 21))]), InvalidArgumentException::class);
        throws(fn () => $media->import($source, 'x.jpg', ['alt' => str_repeat('a', 256)]), InvalidArgumentException::class);
        same(2, (int) $db->one('SELECT COUNT(*) AS n FROM media')['n']);
        foreach ([['media_format' => 'gif'], ['media_quality' => '20'], ['media_max_size' => '1000']] as $bad) {
            throws(fn () => $media->saveOptions($bad + ['media_optimize' => '1', 'media_format' => 'webp', 'media_quality' => '82', 'media_max_size' => '2560']), InvalidArgumentException::class);
        }
        if (!ImageOptimizer::available('avif')) { throws(fn () => $media->saveOptions(['media_format' => 'avif', 'media_quality' => '60', 'media_max_size' => '0']), InvalidArgumentException::class); }
    } finally { Core\ReleasePackage::removeTree($root); }
});
test('Media: search by words, category and tags, wildcard escaping, pagination and editing', function () {
    $root = mediaRoot(); $db = database(); locale($db);
    try {
        $media = new MediaLibrary($db, $root . '/public');
        $media->saveOptions(['media_optimize' => '0', 'media_format' => 'webp', 'media_quality' => '82', 'media_max_size' => '0']);
        $source = mediaImage($root, 'png', 20, 20);
        $ids = [];
        foreach ([['beach.png', 'Travel', 'sea, Sand', ''], ['city.png', 'Travel', 'night', 'Tower at dusk'], ['cat.png', 'Pets', 'Été', '100% cute'], ['dog_1.png', '', '', '']] as $i => [$name, $category, $tags, $alt]) {
            $ids[$name] = (int) $media->import($source, $name, ['category' => $category, 'tags' => $tags, 'alt' => $alt])['id'];
            $db->query('UPDATE media SET created_at = ? WHERE id = ?', [gmdate('Y-m-d H:i:s', 1000000 + $i), $ids[$name]]);
        }
        $names = fn (array $result) => array_column($result['rows'], 'original_name');
        same(['dog_1.png', 'cat.png', 'city.png', 'beach.png'], $names($media->search('', '', 1, 20)));
        same(['city.png', 'beach.png'], $names($media->search('', 'Travel', 1, 20)));
        same(['beach.png'], $names($media->search('SAND', '', 1, 20)));
        same(['city.png'], $names($media->search('tower  DUSK', '', 1, 20)));
        same(['cat.png'], $names($media->search('été', '', 1, 20)));
        same(['cat.png'], $names($media->search('100%', '', 1, 20)));
        same(['dog_1.png'], $names($media->search('g_', '', 1, 20)));
        same([], $names($media->search('travel night sand', '', 1, 20)));
        $page = $media->search('', '', 2, 3);
        same(['beach.png'], $names($page)); same([4, 2, 2], [$page['total'], $page['page'], $page['pages']]);
        same(2, $media->search('', '', 99, 3)['page']);
        same(['Pets', 'Travel'], $media->categories());
        $media->update($ids['dog_1.png'], ['alt' => 'A dog', 'category' => 'Pets', 'tags' => 'Puppy']);
        same(['dog_1.png', 'cat.png'], $names($media->search('', 'Pets', 1, 20))); same(['dog_1.png'], $names($media->search('puppy', '', 1, 20)));
        $media->update($ids['dog_1.png'], ['alt' => '', 'category' => '', 'tags' => '']);
        same([], $names($media->search('puppy', '', 1, 20))); same([], $media->find($ids['dog_1.png'])['tags']);
        throws(fn () => $media->update(9999, []), InvalidArgumentException::class);
        $url = MediaLibrary::url($media->find($ids['cat.png'])['file_name']);
        $html = (new Core\Markdown())->compile('![A cat](' . $url . ')')['html'];
        contains('<img src="' . $url . '" alt="A cat" loading="lazy">', $html);
        $db->query("INSERT INTO posts (slug, base_locale) VALUES ('p', 'en')");
        $db->query("INSERT INTO post_translations (post_id, locale, title, summary, content_md, content_html, toc_html) VALUES (1, 'en', 'T', '', ?, '', '')", ['See ![A cat](' . $url . ')']);
        same(1, $media->usage($media->find($ids['cat.png']))); same(0, $media->usage($media->find($ids['city.png'])));
    } finally { Core\ReleasePackage::removeTree($root); }
});
test('Media: upload limit and byte sizes', function () {
    same(2097152, ImageOptimizer::bytes('2M')); same(1024, ImageOptimizer::bytes('1k')); same(1073741824, ImageOptimizer::bytes('1G')); same(-1, ImageOptimizer::bytes('-1'));
    ok(MediaLibrary::uploadLimit() <= MediaLibrary::MAX_BYTES);
    same('1.5 MB', MediaLibrary::size(1572864)); same('2 KB', MediaLibrary::size(2048)); same('1 KB', MediaLibrary::size(10));
});
test('Admin HTTP: media page, picker JSON, uploads, delete and optimization settings', function () {
    $c = new AdminHttpClient();
    try {
        foreach (['/admin/media', '/admin/media/library', '/admin/media/upload', '/admin/media/1/delete', '/admin/optimization', '/admin/updates/restore', '/admin/security/passkeys/1/remove'] as $path) {
            [$h] = $c->request($path); contains('Location: /admin/login', $h);
        }
        $token = $c->token(); $c->request('/admin/login', 'POST', ['csrf_token' => $token, 'username' => 'editor', 'password' => 'a long password']);
        $token = $c->token('/admin/media');
        [$h, $html] = $c->request('/admin/media'); contains('200 OK', $h); contains('No images yet.', $html); contains('aria-current="page"', $html); contains('Optimization · WEBP on', $html);
        $png = (function () { ob_start(); $image = imagecreatetruecolor(900, 600); imagefill($image, 0, 0, imagecolorallocate($image, 10, 120, 200)); imagepng($image); return ob_get_clean(); })();
        [$h] = $c->request('/admin/media', 'POST', ['action' => 'upload', 'category' => 'Travel', 'tags' => 'sea', 'alt' => 'Blue'], false, null, ['field' => 'media', 'filename' => 'blue.png', 'bytes' => $png]);
        contains('403 Forbidden', $h);
        [$h] = $c->request('/admin/media', 'POST', ['csrf_token' => $token, 'action' => 'upload', 'category' => 'Travel', 'tags' => 'sea', 'alt' => 'Blue'], false, null, ['field' => 'media', 'filename' => 'blue.png', 'bytes' => $png]);
        contains('303 See Other', $h); contains('Location: /admin/media', $h);
        $row = $c->db->one('SELECT * FROM media');
        same(['blue.png', 'Travel', 'Blue'], [$row['original_name'], $row['category'], $row['alt_text']]);
        ok(is_file($c->root . '/public/uploads/media/' . $row['file_name']));
        [, $html] = $c->request('/admin/media'); contains('1 image uploaded.', $html); contains(e('/uploads/media/' . $row['thumb_name']), $html); contains('value="sea"', $html);
        [$h, $json] = $c->request('/admin/media/library?q=blue&page=1'); contains('Content-Type: application/json', $h);
        $data = json_decode($json, true); same(1, $data['total']); same('/uploads/media/' . $row['file_name'], $data['items'][0]['url']); same(['Travel'], $data['categories']);
        same(0, json_decode($c->request('/admin/media/library?q=nothing')[1], true)['total']);
        [$h, $json] = $c->request('/admin/media/upload', 'POST', ['alt' => 'Again'], false, null, ['field' => 'media', 'filename' => 'again.png', 'bytes' => $png]);
        contains('403 Forbidden', $h); contains('application/json', $h); ok(isset(json_decode($json, true)['error']));
        [$h, $json] = $c->request('/admin/media/upload', 'POST', ['csrf_token' => $token, 'alt' => 'Again', 'category' => 'Pets'], false, null, ['field' => 'media', 'filename' => 'again.png', 'bytes' => $png]);
        contains('200 OK', $h); same('Again', json_decode($json, true)['item']['alt']);
        [$h, $json] = $c->request('/admin/media/upload', 'POST', ['csrf_token' => $token], false, null, ['field' => 'media', 'filename' => 'notes.png', 'bytes' => 'not an image']);
        contains('422', $h); same('Upload a JPG, PNG, WebP, GIF or AVIF image.', json_decode($json, true)['error']);
        [, $html] = $c->request('/admin/media?category=Pets'); contains('again.png', $html); ok(!str_contains($html, 'blue.png'));
        $c->request('/admin/media?q=blue', 'POST', ['csrf_token' => $token, 'action' => 'save', 'id' => $row['id'], 'alt' => 'Deep blue', 'category' => 'Colors', 'tags' => 'blue, sky']);
        same('Deep blue', $c->db->one('SELECT alt_text FROM media WHERE id = ?', [$row['id']])['alt_text']);
        [$h] = $c->request('/admin/media?q=blue', 'POST', ['csrf_token' => $token, 'action' => 'save', 'id' => $row['id'], 'tags' => implode(',', range(1, 25))]);
        contains('Location: /admin/media?q=blue', $h);
        [, $html] = $c->request('/admin/media?q=blue'); contains('Use at most 20 tags per image.', $html);
        [$h, $html] = $c->request('/admin/media/' . $row['id'] . '/delete'); contains('200 OK', $h); contains('No post or page uses this image.', $html);
        $c->request('/admin/media/' . $row['id'] . '/delete', 'POST', ['csrf_token' => $token]);
        same(null, $c->db->one('SELECT id FROM media WHERE id = ?', [$row['id']])); ok(!is_file($c->root . '/public/uploads/media/' . $row['file_name']));
        [$h] = $c->request('/admin/media/' . $row['id'] . '/delete'); contains('404 Not Found', $h);
        [$h, $html] = $c->request('/admin/optimization'); contains('200 OK', $h); contains('name="media_optimize" value="1" checked', $html); contains('aria-current="page">Optimization', $html);
        $c->request('/admin/optimization', 'POST', ['csrf_token' => $token, 'media_optimize' => '0', 'media_format' => 'webp', 'media_quality' => '75', 'media_max_size' => '1920']);
        same('0', $c->db->one("SELECT value FROM settings WHERE `key` = 'media_optimize'")['value']);
        [, $html] = $c->request('/admin/optimization'); contains('Changes saved.', $html); ok(!str_contains($html, 'name="media_optimize" value="1" checked'));
        $c->request('/admin/optimization', 'POST', ['csrf_token' => $token, 'media_optimize' => '1', 'media_format' => 'webp', 'media_quality' => '5', 'media_max_size' => '1920']);
        [, $html] = $c->request('/admin/optimization'); contains('Quality must be between 40 and 95.', $html); contains('value="5"', $html);
        [, $html] = $c->request('/admin/editor?kind=post');
        contains('data-md-editor', $html); contains('id="media-picker"', $html); contains('data-cmd="image"', $html); contains('name="content_md"', $html);
        same(1, substr_count($html, '<script')); contains('/assets/admin.js', $html);
    } finally { $c->close(); }
});
test('Admin HTTP: passkeys table and removal confirmation', function () {
    $c = new AdminHttpClient();
    try {
        $token = $c->token(); $c->request('/admin/login', 'POST', ['csrf_token' => $token, 'username' => 'editor', 'password' => 'a long password']);
        $c->db->query('INSERT INTO passkeys (user_id, credential_id, credential_hash, public_key, algorithm, name) VALUES (1, ?, ?, ?, -7, ?)', ['credential', hash('sha256', 'credential'), 'pem', 'Laptop <one>']);
        [, $html] = $c->request('/admin/security'); contains('<caption class="sr-only">Your passkeys</caption>', $html); contains('value="Laptop &lt;one&gt;"', $html);
        contains('href="/admin/security/passkeys/1/remove"', $html);
        [$h, $html] = $c->request('/admin/security/passkeys/1/remove'); contains('200 OK', $h); contains('This is your last passkey.', $html); contains('name="current_password"', $html);
        [$h] = $c->request('/admin/security/passkeys/2/remove'); contains('404 Not Found', $h);
    } finally { $c->close(); }
});
