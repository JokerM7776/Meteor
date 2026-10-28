<?php
use App\Services\Import\{CheckedFiles, FrontMatter, HexoSource, HexoTags, HtmlToMarkdown, Importer, ReferenceLinks, Report, Files};

function reviewImport(callable $test): void
{
    $root = importFixture(); $db = database(); locale($db); locale($db, 'zh-hans', 'off', 0);
    $db->query('UPDATE locales SET native_name = ? WHERE code = ?', ['简体中文', 'zh-hans']);
    try { $test($root, $db, new Importer($db, $root . '/public', $root)); }
    finally { Core\ReleasePackage::removeTree($root); }
}
function reviewPng(): string { return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j2ioAAAAASUVORK5CYII='); }

test('Import review 01: NULL and blank Typecho passwords stay published', function () {
    reviewImport(function ($root, $db, $import) {
        $file = typechoFixture($root); $pdo = new PDO('sqlite:' . $file);
        $pdo->exec("UPDATE typecho_contents SET password = NULL WHERE cid = 1");
        $pdo->exec("UPDATE typecho_contents SET password = '  ' WHERE cid = 2");
        $r = $import->run('typecho', ['driver' => 'sqlite', 'file' => $file]); ok(!$r->failed(), $r->text());
        foreach (['markdown', 'html'] as $slug) { same(1, (int) $db->one('SELECT is_published FROM posts WHERE slug = ?', [$slug])['is_published']); }
        same(0, (int) $db->one("SELECT is_published FROM posts WHERE slug = 'hidden'")['is_published']);
        contains('Was password protected; imported as draft.', $r->text());
    });
});
test('Import review 02: shared reference links and images resolve in both sources outside code', function () {
    $md = "![alt][ ImG ] [text][LINK] [Link][] [shortcut] [nope] [Link](keep)\n  [img]: <image.png> \"Photo\"\n[link]: https://example.com 'Title'\n[shortcut]: /short (Short title)\n[unused]: /unused\n[^note]: footnote\n[^note]\n`[shortcut]`\n```\n[shortcut]\n```";
    $out = ReferenceLinks::convert($md);
    foreach (['![alt](image.png "Photo")', '[text](https://example.com "Title")', '[Link](https://example.com "Title")', '[shortcut](/short "Short title")', '[unused]: /unused', '[^note]: footnote', '`[shortcut]`', '[Link](keep)', "```\n[shortcut]\n```"] as $part) { contains($part, $out); }
    ok(!str_contains($out, '[img]:')); ok(!str_contains($out, '[link]:'));
    $w = []; contains('[unused]: <url>', HtmlToMarkdown::inline(ReferenceLinks::convert('[unused]: <url>'), $w));
    same('[x](url)', trim(ReferenceLinks::convert("[x][ A   B ]\n[a b]: url")));
    reviewImport(function ($root, $db, $import) {
        importFile($root, 'hexo/_posts/refs.md', "![hexo][1]\n  [1]: image.png"); importFile($root, 'hexo/_posts/image.png', reviewPng());
        $r = $import->run('hexo', ['file' => $root . '/hexo']); ok(!$r->failed(), $r->text());
        $file = typechoFixture($root); $pdo = new PDO('sqlite:' . $file);
        $pdo->prepare('UPDATE typecho_contents SET text = ? WHERE cid = 1')->execute(["<!--markdown-->![1234.png][1]\n  [1]: https://old/usr/uploads/2021/05/1234.png"]);
        importFile($root, 'uploads/2021/05/1234.png', reviewPng());
        $r = $import->run('typecho', ['driver' => 'sqlite', 'file' => $file, 'uploads' => $root . '/uploads']); ok(!$r->failed(), $r->text());
        foreach (['refs', 'markdown'] as $slug) { $row = $db->one('SELECT content_md FROM post_translations t JOIN posts p ON p.id = t.post_id WHERE p.slug = ?', [$slug]); contains('/uploads/media/', $row['content_md']); ok(!str_contains($row['content_md'], '[1]:')); }
    });
});
test('Import review 03: HTML text uses only necessary Markdown escapes', function () {
    $w = []; same('Version 1.2 (final) - done: yes!', HtmlToMarkdown::convert('<p>Version 1.2 (final) - done: yes!</p>', $w));
    same('A \\*literal\\*', HtmlToMarkdown::convert('<p>A *literal*</p>', $w));
    foreach (['# heading' => '\\# heading', '> quote' => '\\> quote', '- item' => '\\- item', '+ item' => '\\+ item', '1. item' => '1\\. item', 'x | y' => 'x | y', '==hi==' => '\\=\\=hi\\=\\='] as $text => $expected) { same($expected, HtmlToMarkdown::convert('<p>' . e($text) . '</p>', $w)); }
    contains('x\\|y', HtmlToMarkdown::convert('<table><tr><td>x|y</td></tr></table>', $w));
});
test('Import review 04: HTML spacing, inline code, empty anchors and comment breaks', function () {
    $w = []; $md = HtmlToMarkdown::convert('<div><p>One </p><p>Two<br>three</p></div><p><code>x</code> <code>`x`</code></p><a>plain</a> <a href="#">anchor</a>', $w);
    ok(!str_contains($md, "\n\n\n")); contains("One\n\nTwo  \nthree", $md); contains('`x`', $md); contains('`` `x` ``', $md); contains('plain anchor', $md);
    $md = HtmlToMarkdown::inline("<p>A </p>\n\n\n<p>B<br>C</p>\nend \n", $w); ok(!str_contains($md, "\n\n\n")); contains("B  \nC", $md); ok(!str_contains($md, "end \n"));
    reviewImport(function ($root, $db, $import) {
        $file = typechoFixture($root); (new PDO('sqlite:' . $file))->exec("UPDATE typecho_comments SET text = '<p>A<br>B</p><p>C</p>'");
        $import->run('typecho', ['driver' => 'sqlite', 'file' => $file]); same("A\nB\n\nC", $db->one('SELECT body FROM comments ORDER BY id')['body']);
    });
});
test('Import review 05: nested YAML maps and silent Hexo config parsing', function () {
    [$data, $warnings] = FrontMatter::yaml("deploy:\n  type: git\n  branch: main\ntitle: Blog"); same(['type' => 'git', 'branch' => 'main'], $data['deploy']); same([], $warnings);
    reviewImport(function ($root, $db, $import) {
        importFile($root, 'blog/_config.yml', "deploy:\n  type: git\nunsupported !value\ntimezone: UTC"); importFile($root, 'blog/source/_posts/a.md', 'A');
        $r = $import->run('hexo', ['file' => $root . '/blog'], ['check' => true]); ok(!str_contains($r->text(), 'Unsupported front matter'));
    });
});
test('Import review 06: same-language filenames conflict normally; language folders group translations', function () {
    reviewImport(function ($root, $db, $import) {
        importFile($root, 'source/_posts/2020/notes.md', 'Old notes'); importFile($root, 'source/_posts/2021/notes.md', 'New notes');
        importFile($root, 'source/_posts/en/hello.md', 'Hello'); importFile($root, 'source/_posts/zh-CN/hello.md', '你好');
        $r = $import->run('hexo', ['file' => $root . '/source'], ['check' => true]); contains('Slug already used', $r->text()); ok(!str_contains($r->text(), 'Duplicate group'));
        $r = $import->run('hexo', ['file' => $root . '/source'], ['conflict' => 'rename']); ok(!$r->failed(), $r->text()); same(3, $r->totals['posts']['imported']);
        ok($db->one("SELECT id FROM posts WHERE slug = 'notes-2'") !== null);
        same(2, (int) $db->one("SELECT COUNT(*) n FROM post_translations t JOIN posts p ON p.id = t.post_id WHERE p.slug = 'hello'")['n']);
    });
});
test('Import review 07: automatic site language, explicit override and default fallback', function () {
    reviewImport(function ($root, $db, $import) {
        importFile($root, 'blog/_config.yml', "language: [zh-CN, en]"); importFile($root, 'blog/source/_posts/a.md', 'A');
        $r = $import->run('hexo', ['file' => $root . '/blog'], ['check' => true]); same('zh-hans', $r->rows[0]['language']); contains('简体中文 (zh-hans), based on _config.yml.', $r->text());
        $r = $import->run('hexo', ['file' => $root . '/blog'], ['check' => true, 'locale' => 'en']); same('en', $r->rows[0]['language']); contains('based on your choice', $r->text());
        importFile($root, 'blog/_config.yml', 'language: nowhere'); $r = $import->run('hexo', ['file' => $root . '/blog'], ['check' => true]); same('en', $r->rows[0]['language']);
        importFile($root, 'blog/_config.yml', 'language: zh-CN'); $r = $import->run('hexo', ['file' => $root . '/blog'], ['check' => true]); same('zh-hans', $r->rows[0]['language']);
        $r = $import->run('typecho', ['driver' => 'sqlite', 'file' => typechoFixture($root)], ['check' => true]); same('en', $r->rows[0]['language']);
    });
});
test('Import review 08: only imported media can become a cover in Check and Import', function () {
    reviewImport(function ($root, $db, $import) {
        foreach (['missing' => 'gone.png', 'remote' => 'https://remote.example/image.png', 'text' => 'text.txt', 'prefixed' => '/uploads/media/missing.png', 'valid' => 'image.png'] as $slug => $cover) { importFile($root, 'source/_posts/' . $slug . '.md', "---\ncover: $cover\n---\nBody"); }
        importFile($root, 'source/_posts/text.txt', 'Text'); importFile($root, 'source/_posts/image.png', reviewPng());
        $check = $import->run('hexo', ['file' => $root . '/source'], ['check' => true]);
        $real = $import->run('hexo', ['file' => $root . '/source']);
        same(4, substr_count($check->text(), 'Cover not imported:')); same(4, substr_count($real->text(), 'Cover not imported:'));
        foreach (['missing', 'remote', 'text', 'prefixed'] as $slug) { same(null, $db->one('SELECT cover_image FROM posts WHERE slug = ?', [$slug])['cover_image']); }
        contains('/uploads/media/', $db->one("SELECT cover_image FROM posts WHERE slug = 'valid'")['cover_image']);
    });
});
test('Import review 09: checked uploads can import, cancel, expire and reject tokens', function () {
    if (!class_exists(ZipArchive::class)) { return; }
    $c = new AdminHttpClient(); $root = importFixture();
    try {
        $csrf = $c->token(); $c->request('/admin/login', 'POST', ['csrf_token' => $csrf, 'username' => 'editor', 'password' => 'a long password']);
        $csrf = $c->token('/admin/import'); $zip = importZip($root, ['source/_posts/checked.md' => 'Checked']);
        $check = function () use ($c, $csrf, $zip): array {
            [, $html] = $c->request('/admin/import', 'POST', ['csrf_token' => $csrf, 'action' => 'check', 'password' => 'never-retain-password'], false, null, ['field' => 'archive', 'filename' => 'source.zip', 'bytes' => file_get_contents($zip)]);
            contains('Import these ', $html); preg_match('/name="token" value="([a-f0-9]{32})"/', $html, $m); ok(isset($m[1])); return [$m[1], $html];
        };
        [$token] = $check(); $directory = $c->root . '/storage/imports/' . $token;
        same(0700, fileperms($directory) & 0777); same(0600, fileperms($directory . '/source.zip') & 0777);
        foreach (glob($c->directory . '/sess_*') as $session) { ok(!str_contains(file_get_contents($session), 'never-retain-password')); }
        [, $html] = $c->request('/admin/import', 'POST', ['csrf_token' => $csrf, 'action' => 'import_checked', 'token' => str_repeat('0', 32)]); contains('no longer available', $html); ok(is_dir($directory));
        [$headers] = $c->request('/admin/import', 'POST', ['action' => 'import_checked', 'token' => $token]); contains('403 Forbidden', $headers); ok(is_dir($directory));
        [, $html] = $c->request('/admin/import', 'POST', ['csrf_token' => $csrf, 'action' => 'import_checked', 'token' => $token]); contains('Import finished', $html); ok(!is_dir($directory)); same(1, (int) $c->db->one('SELECT COUNT(*) n FROM posts')['n']);
        [$token] = $check(); $c->request('/admin/import', 'POST', ['csrf_token' => $csrf, 'action' => 'cancel', 'token' => $token]); ok(!is_dir($c->root . '/storage/imports/' . $token));
        [$token] = $check(); touch($c->root . '/storage/imports/' . $token, time() - 7201); $c->request('/admin/import'); ok(!is_dir($c->root . '/storage/imports/' . $token));
        [, $html] = $c->request('/admin/import', 'POST', ['csrf_token' => $csrf, 'action' => 'import_checked', 'token' => $token]); contains('no longer available', $html);
        $sqlite = typechoFixture($root); $zip = importZip($root, ['usr/uploads/2024/pixel.png' => reviewPng()]);
        [, $html] = $c->request('/admin/import?source=typecho', 'POST', ['csrf_token' => $csrf, 'action' => 'check', 'driver' => 'sqlite'], false, null,
            [['field' => 'sqlite', 'filename' => 'typecho.db', 'bytes' => file_get_contents($sqlite)], ['field' => 'uploads', 'filename' => 'uploads.zip', 'bytes' => file_get_contents($zip)]]);
        preg_match('/name="token" value="([a-f0-9]{32})"/', $html, $m); ok(isset($m[1]), $html); $token = $m[1];
        ok(is_file($c->root . '/storage/imports/' . $token . '/source.db')); ok(is_file($c->root . '/storage/imports/' . $token . '/uploads.zip'));
        [, $html] = $c->request('/admin/import?source=typecho', 'POST', ['csrf_token' => $csrf, 'action' => 'import_checked', 'token' => $token]); contains('Import finished', $html); same(1, (int) $c->db->one('SELECT COUNT(*) n FROM media')['n']); same([], glob($c->root . '/storage/imports/*'));
        same(['driver' => 'mysql', 'user' => 'reader'], CheckedFiles::options(['driver' => 'mysql', 'user' => 'reader', 'password' => 'secret', 'file' => '/bad']));
    } finally { $c->close(); Core\ReleasePackage::removeTree($root); }
});
test('Import review 10: compact reports, editor URLs, chips, source labels and readable CLI', function () {
    $report = new Report(false); $checked = null;
    $report->count('posts', 'imported', 3); $report->commentStatuses = ['approved' => 2, 'pending' => 1, 'spam' => 0]; $report->warnings = ['First warning', 'Second warning'];
    foreach (['imported', 'skipped', 'failed'] as $result) { $report->row(['kind' => 'post', 'title' => '<Title>', 'locale' => 'en', 'slug' => 'slug', 'source' => '_posts/file.md', 'warnings' => ['Note'], 'id' => 42], $result); }
    ob_start(); require dirname(__DIR__) . '/views/admin/import-report.php'; $html = ob_get_clean();
    foreach (['Import totals', 'Imported', 'Skipped', 'Failed', 'approved 2 · pending 1', 'border-dashed', 'bg-red-50 text-red-900', 'bg-slate-900 text-white', '_posts/file.md', 'post=42&amp;locale=en&amp;kind=post', '&lt;Title&gt;', 'max-w-full', 'min-w-[720px]'] as $part) { contains($part, $html); }
    same(1, substr_count($html, 'border-amber-200')); ok(!str_contains($html, 'spam 0')); contains('posts         3 imported, 0 skipped, 0 failed', $report->text()); contains('Comments: approved 2 · pending 1', $report->text()); ok(!str_contains($report->text(), '{"'));
    $report = new Report(true); $checked = ['token' => str_repeat('a', 32), 'source' => 'typecho', 'options' => ['driver' => 'mysql']];
    ob_start(); require dirname(__DIR__) . '/views/admin/import-report.php'; $html = ob_get_clean(); contains('Would import', $html); contains('name="password" required autocomplete="off"', $html);
    $c = new AdminHttpClient();
    try {
        $csrf = $c->token(); $c->request('/admin/login', 'POST', ['csrf_token' => $csrf, 'username' => 'editor', 'password' => 'a long password']);
        [, $html] = $c->request('/admin/import'); contains('Automatic (Hexo: from _config.yml)', $html); contains('value="auto" selected', $html);
        [, $html] = $c->request('/admin/import?source=typecho'); contains('Default language (en)', $html);
    } finally { $c->close(); }
});
test('Import review 11: Hexo link arguments, fenced raw tags and clean summaries', function () {
    $w = []; $md = HexoTags::convert('{% link Some text https://example.com true "A title" %}', '', $w); same('[Some text](https://example.com)', $md);
    foreach (['/local', '#anchor', 'mailto:a@example.com'] as $url) { same('[Text](' . $url . ')', HexoTags::convert('{% link Text ' . $url . ' false Title %}', '', $w)); }
    $code = "```html\n{% raw %}<p>Keep</p>{% endraw %}\n```"; same($code, HexoTags::convert($code, '', $w));
    $body = 'Intro {% img /a.png %} <!-- a comment -->text<!--more-->Rest'; same('Intro text', HtmlToMarkdown::summary($body));
    same('Explicit text', HtmlToMarkdown::summary($body, 'Explicit {% note %}text{% endnote %}<!-- hidden -->'));
});
test('Import review 12: Hexo root requires a segment boundary and prefers shallow roots', function () {
    reviewImport(function ($root, $db, $import) {
        importFile($root, 'bad/my_posts/a.md', 'Wrong'); throws(fn () => $import->run('hexo', ['file' => $root . '/bad']), RuntimeException::class);
        importFile($root, 'good/a/deep/source/_posts/deep.md', 'Deep'); importFile($root, 'good/source/_posts/shallow.md', 'Shallow');
        $r = $import->run('hexo', ['file' => $root . '/good'], ['check' => true]); same(1, count($r->rows)); same('shallow', $r->rows[0]['slug']);
    });
});
test('Import review 13: untouched installer About is replaceable; edited translations are protected', function () {
    reviewImport(function ($root, $db, $import) {
        $id = App\Services\AboutPage::ensure($db);
        importFile($root, 'source/_posts/a.md', 'A'); importFile($root, 'source/about/index.md', "---\ntitle: My About\n---\nImported biography");
        $check = $import->run('hexo', ['file' => $root . '/source'], ['check' => true]); contains('Replaced the default About page.', $check->text()); same(App\Services\AboutPage::MARKDOWN, $db->one('SELECT content_md FROM page_translations')['content_md']);
        $r = $import->run('hexo', ['file' => $root . '/source']); contains('Replaced the default About page.', $r->text()); same(1, (int) $db->one('SELECT COUNT(*) n FROM pages')['n']); same((int) $id, (int) $db->one('SELECT id FROM pages')['id']); same('Imported biography', $db->one('SELECT content_md FROM page_translations')['content_md']);
        $r = $import->run('hexo', ['file' => $root . '/source']); contains('Slug already used', $r->text()); ok(!str_contains($r->text(), 'Replaced the default'));
        $r = $import->run('hexo', ['file' => $root . '/source'], ['conflict' => 'rename']); ok($db->one("SELECT id FROM pages WHERE slug = 'about-2'") !== null);
        $db->query('UPDATE page_translations SET content_md = ?, title = ?, status = ? WHERE page_id = ?', [App\Services\AboutPage::MARKDOWN, 'About', 'draft', $id]);
        $db->upsert('page_translations', ['page_id' => $id, 'locale' => 'zh-hans', 'title' => 'About', 'summary' => '', 'content_md' => 'Edited Chinese', 'content_html' => '<p>Edited Chinese</p>', 'toc_html' => '', 'status' => 'draft'], ['page_id', 'locale']);
        $r = $import->run('hexo', ['file' => $root . '/source'], ['check' => true]); ok(!str_contains($r->text(), 'Replaced the default'));
    });
});
