<?php
use App\Services\Import\{FrontMatter, HtmlToMarkdown, Files, Importer, TypechoSource};

function importFixture(): string
{
    $root = dirname(__DIR__) . '/storage/test-import-' . bin2hex(random_bytes(6)); mkdir($root, 0700); return $root;
}
function importFile(string $root, string $name, string $bytes): void
{
    if (!is_dir(dirname($root . '/' . $name))) { mkdir(dirname($root . '/' . $name), 0700, true); }
    file_put_contents($root . '/' . $name, $bytes);
}
function importZip(string $root, array $files): string
{
    $path = $root . '/source.zip'; $zip = new ZipArchive(); $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    foreach ($files as $name => $bytes) { $zip->addFromString($name, $bytes); } $zip->close(); return $path;
}
test('Import: front matter subset, JSON, missing delimiter and recoverable garbage', function () {
    [$v, $body, $w] = FrontMatter::parse("---\ntitle: \"A # title\" # comment\nquote: 'It''s fine'\ndate: 2024-01-02 12:30:00\npublished: false\norder: 3\ntags: [a, \"b c\"]\ncategories:\n  - [A, B]\n  -\n    - C\n    - D\nsummary: |\n  Line one\n  Line two\nexcerpt: >\n  Fold\n  me\nbad garbage\n---\nBody");
    same('A # title', $v['title']); same("It's fine", $v['quote']); same(false, $v['published']); same(3, $v['order']); same(['a', 'b c'], $v['tags']);
    same([['A', 'B'], ['C', 'D']], $v['categories']); same("Line one\nLine two", $v['summary']); same('Fold me', $v['excerpt']); same('Body', $body); ok(count($w) === 1);
    [$v, $body] = FrontMatter::parse("title: Hello\ntags:\n- a\n- b\n---\nText"); same(['a', 'b'], $v['tags']); same('Text', $body);
    [, $plain] = FrontMatter::parse("Normal text\n\n---\nMore text"); same("Normal text\n\n---\nMore text", $plain);
    [$v, $body] = FrontMatter::parse(";;;\n{\"title\":\"JSON\"}\n;;;\nBody"); same('JSON', $v['title']); same('Body', $body);
});
test('Import: HTML elements, nested lists, UTF-8, safe code and embedded HTML', function () {
    if (!class_exists(DOMDocument::class)) { return; }
    $warnings = [];
    $md = HtmlToMarkdown::convert('<h1>你好</h1><p>A *literal* <b>bold</b><i>em</i><s>gone</s><br><a href="https://example.com">link</a><img src="/a.png" alt="a"></p><pre><code class="language-php">echo 1;</code></pre><ul><li>One<ul><li>Two</li></ul></li></ul><ol><li>First</li></ol><blockquote>Quote</blockquote><hr><table><tr><th>A</th><th>B</th></tr><tr><td>1</td><td>2</td></tr></table><figure><img src="/b.png"><figcaption>Caption</figcaption></figure><section><span>Text</span></section><script>evil()</script><style>css</style><noscript>no</noscript><iframe src="https://video.example"></iframe><custom>unknown</custom>', $warnings);
    foreach (['# 你好', '\\*literal\\*', '**bold**', '*em*', '~~gone~~', '[link](https://example.com)', '![a](/a.png)', '```php', '- One', '  - Two', '1. First', '> Quote', '| --- | --- |', 'Caption', 'https://video.example'] as $needle) { contains($needle, $md); }
    ok(!str_contains($md, 'evil')); contains('iframe', implode(' ', $warnings)); contains('custom', implode(' ', $warnings));
    $warnings = []; $inline = HtmlToMarkdown::inline('a<br>b <https://example.com> <code>&lt;b&gt;</code>', $warnings); contains("a  \nb", $inline); contains('<https://example.com>', $inline); contains('`<b>`', $inline);
    $warnings = []; $input = "```html\n<img src=\"keep\">\n```\n`<b>code</b>` <b>bold</b> <img src=\"a.png\">\n<div>block</div><script>evil()</script>";
    $md = HtmlToMarkdown::inline($input, $warnings); contains('<img src="keep">', $md); contains('`<b>code</b>`', $md); contains('**bold**', $md); contains('![](a.png)', $md); ok(!str_contains($md, 'evil'));
});
test('Import: Hexo source, transactions, check parity, assets and translations', function () {
    $root = importFixture(); $db = database(); locale($db); locale($db, 'zh-hans', 'off', 0);
    try {
        importFile($root, 'blog/_config.yml', "timezone: Asia/Singapore\nurl: https://old.example\npost_asset_folder: true\n");
        importFile($root, 'blog/source/_posts/2024-01-02-hello.md', "---\ntitle: Hello\ndate: 2024-01-02 08:00:00\nlang: en\ncategories: [A, B]\ntags: [one, two]\nsticky: 1\ncover: pixel.png\n---\nIntro **text**<!-- more -->\n{% asset_img pixel.png Photo %}\n![missing](missing.png)\n{% codeblock lang:php %}\necho 1;\n{% endcodeblock %}\n{% blockquote Ada %}Quote{% endblockquote %}\n{% mystery thing %}\n<b>Bold</b><script>evil()</script>");
        importFile($root, 'blog/source/_posts/zh/hello.md', "---\nlang: zh-CN\ntitle: 你好\n---\n中文");
        importFile($root, 'blog/source/_posts/duplicate.md', "---\nslug: hello\nlang: en\n---\nDuplicate");
        importFile($root, 'blog/source/_posts/unknown.md', "---\nlang: xx-unknown\npublished: false\n---\nUnknown");
        importFile($root, 'blog/source/_drafts/draft.md', "---\ntitle: Draft\n---\nDraft");
        importFile($root, 'blog/source/about/index.md', "---\ntitle: About\norder: 4\n---\nAbout");
        importFile($root, 'blog/source/search.md', "Search"); importFile($root, 'blog/source/skip.md', "---\nlayout: false\n---\nSkip");
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j2ioAAAAASUVORK5CYII=');
        importFile($root, 'blog/source/_posts/2024-01-02-hello/pixel.png', $png);
        $import = new Importer($db, $root . '/public', $root);
        $before = glob($root . '/public/uploads/media/*') ?: [];
        $check = $import->run('hexo', ['file' => $root . '/blog'], ['check' => true]);
        same(false, $check->failed()); same(0, (int) $db->one('SELECT COUNT(*) n FROM posts')['n']); same(0, (int) $db->one('SELECT COUNT(*) n FROM categories')['n']); same($before, glob($root . '/public/uploads/media/*') ?: []);
        same(3, $check->totals['posts']['imported']); same(2, $check->totals['pages']['imported']); same(1, $check->totals['translations']['skipped']);
        $report = $import->run('hexo', ['file' => $root . '/blog']); ok(!$report->failed(), $report->text()); same($check->totals, $report->totals);
        $post = $db->one("SELECT * FROM posts WHERE slug = 'hello'"); same('en', $post['base_locale']); same('2024-01-02 00:00:00', $post['published_at']); same(1, (int) $post['is_pinned']);
        $en = $db->one("SELECT * FROM post_translations WHERE post_id = ? AND locale = 'en'", [$post['id']]); same('Intro text', $en['summary']); contains('```php', $en['content_md']); contains('**Bold**', $en['content_md']); ok(!str_contains($en['content_html'], '<script>'));
        same(2, (int) $db->one('SELECT COUNT(*) n FROM post_translations WHERE post_id = ?', [$post['id']])['n']);
        contains('Missing image', $report->text()); contains('Unknown language', $report->text()); contains('mystery', $report->text());
        same(1, (int) $db->one('SELECT COUNT(*) n FROM media')['n']); contains('/uploads/media/', $en['content_md']); contains('/uploads/media/', $post['cover_image']);
        same(4, (int) $db->one("SELECT sort_order FROM pages WHERE slug = 'about'")['sort_order']); ok($db->one("SELECT id FROM pages WHERE slug = 'search-2'") !== null);
        $again = $import->run('hexo', ['file' => $root . '/blog']); same(0, $again->totals['translations']['imported']);
        $renamed = $import->run('hexo', ['file' => $root . '/blog'], ['conflict' => 'rename', 'drafts' => true]); ok(!$renamed->failed(), $renamed->text());
        same(0, (int) $db->one("SELECT is_published FROM posts WHERE slug = 'hello-2'")['is_published']);
        throws(fn () => $import->run('hexo', ['file' => $root . '/blog'], ['locale' => 'missing']), InvalidArgumentException::class);
        if (class_exists(ZipArchive::class)) {
            $zip = importZip($root, ['wrap/source/_posts/a.md' => 'A', '../evil.md' => 'bad', '/evil.md' => 'bad', 'wrap/node_modules/no.md' => 'bad']);
            $files = new Files($zip); same(['wrap/source/_posts/a.md'], $files->names()); unset($files);
            $r = $import->run('hexo', ['file' => $zip], ['check' => true]); same(1, $r->totals['posts']['imported']);
            $zip = importZip($root, ['about.md' => 'No posts']); throws(fn () => $import->run('hexo', ['file' => $zip]), RuntimeException::class);
        }
    } finally { Core\ReleasePackage::removeTree($root); }
});

function typechoFixture(string $root): string
{
    $path = $root . '/typecho.db'; $pdo = new PDO('sqlite:' . $path); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE typecho_contents (cid INTEGER, title TEXT, slug TEXT, created INTEGER, modified INTEGER, text TEXT, `order` INTEGER, authorId INTEGER, template TEXT, type TEXT, status TEXT, password TEXT, allowComment INTEGER, parent INTEGER)');
    $pdo->exec('CREATE TABLE typecho_metas (mid INTEGER, name TEXT, slug TEXT, type TEXT, description TEXT, `order` INTEGER, parent INTEGER)');
    $pdo->exec('CREATE TABLE typecho_relationships (cid INTEGER, mid INTEGER)');
    $pdo->exec('CREATE TABLE typecho_comments (coid INTEGER, cid INTEGER, created INTEGER, author TEXT, authorId INTEGER, ownerId INTEGER, mail TEXT, url TEXT, ip TEXT, text TEXT, type TEXT, status TEXT, parent INTEGER)');
    $pdo->exec('CREATE TABLE typecho_fields (cid INTEGER, name TEXT, type TEXT, str_value TEXT)');
    $insert = $pdo->prepare('INSERT INTO typecho_contents VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    foreach ([[1, 'Markdown', 'markdown', '<!--markdown-->Intro<!--more-->\n![Photo](/usr/uploads/2024/pixel.png)', 'post', 'publish', null, 0], [2, 'HTML', 'html', '<h2>Heading</h2><p>Body <b>bold</b></p>', 'post', 'publish', null, 0], [3, 'About', 'about', '<p>Page</p>', 'page', 'publish', null, 0], [4, 'Hidden', 'hidden', 'Hidden', 'post', 'hidden', 'secret', 0], [5, 'Revision', 'revision', 'revision', 'post_draft', 'publish', '', 1], [6, 'Draft', 'draft', 'draft', 'post_draft', 'publish', '', 0]] as [$id, $title, $slug, $text, $type, $status, $password, $parent]) {
        $insert->execute([$id, $title, $slug, 1704153600, 1704240000, $text, 7, 9, '', $type, $status, $password, 1, $parent]);
    }
    $pdo->exec("INSERT INTO typecho_metas VALUES (1, 'Second', 'second', 'category', '', 2, 0), (2, 'First', 'first', 'category', 'Description', 1, 1), (3, 'Tag', 'tag', 'tag', '', 0, 0)");
    $pdo->exec('INSERT INTO typecho_relationships VALUES (1, 1), (1, 2), (1, 3)');
    $pdo->exec("INSERT INTO typecho_fields VALUES (1, 'cover', 'str', 'https://old.example/usr/uploads/2024/pixel.png')");
    $insert = $pdo->prepare('INSERT INTO typecho_comments VALUES (?, 1, 1704153600, ?, ?, 9, ?, ?, ?, ?, ?, ?, ?)');
    foreach ([[1, 'Owner', 9, 'approved', 0, 'comment'], [2, 'Reader', 0, 'waiting', 1, 'comment'], [3, 'Spam', 0, 'spam', 99, 'comment'], [4, 'Ping', 0, 'approved', 0, 'pingback']] as [$id, $name, $author, $status, $parent, $type]) { $insert->execute([$id, $name, $author, 'a@example.com', 'javascript:bad', '127.0.0.1', '<p>Hello</p><p>World</p>', $type, $status, $parent]); }
    return $path;
}
test('Import: Typecho read-only mapping, uploads, comments, prefix and optional fields', function () {
    $root = importFixture(); $db = database(); locale($db);
    try {
        $path = typechoFixture($root); $before = hash_file('sha256', $path);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j2ioAAAAASUVORK5CYII=');
        importFile($root, 'uploads/2024/pixel.png', $png); $uploads = $root . '/uploads';
        if (class_exists(ZipArchive::class)) { $uploads = importZip($root, ['usr/uploads/2024/pixel.png' => $png]); }
        $input = ['driver' => 'sqlite', 'file' => $path, 'uploads' => $uploads]; $import = new Importer($db, $root . '/public', $root);
        $check = $import->run('typecho', $input, ['check' => true]); ok(!$check->failed(), $check->text()); same(0, (int) $db->one('SELECT COUNT(*) n FROM posts')['n']);
        $report = $import->run('typecho', $input); ok(!$report->failed(), $report->text()); same($check->totals, $report->totals); same($before, hash_file('sha256', $path));
        same(4, $report->totals['posts']['imported']); same(1, $report->totals['pages']['imported']); same(1, $report->totals['translations']['skipped']); same(3, $report->totals['comments']['imported']); same(1, $report->totals['comments']['skipped']);
        same(['approved' => 1, 'pending' => 1, 'spam' => 1], $report->commentStatuses);
        $post = $db->one("SELECT * FROM posts WHERE slug = 'markdown'"); same('2024-01-02 00:00:00', $post['created_at']);
        same('First', $db->one('SELECT name FROM category_translations WHERE category_id = ?', [$post['category_id']])['name']); same('Description', $db->one('SELECT description FROM category_translations')['description']);
        $t = $db->one("SELECT * FROM post_translations WHERE post_id = ?", [$post['id']]); same('Intro', $t['summary']); contains('/uploads/media/', $t['content_md']); contains('/uploads/media/', $post['cover_image']);
        $html = $db->one("SELECT content_md FROM post_translations WHERE title = 'HTML'")['content_md']; contains('## Heading', $html); contains('**bold**', $html);
        same(0, (int) $db->one("SELECT is_published FROM posts WHERE slug = 'hidden'")['is_published']); contains('Was password protected; imported as draft.', $report->text()); same(7, (int) $db->one('SELECT sort_order FROM pages')['sort_order']);
        $comments = $db->all('SELECT * FROM comments ORDER BY id'); same(1, (int) $comments[0]['is_owner']); same((int) $comments[0]['id'], (int) $comments[1]['parent_id']); same(null, $comments[2]['parent_id']); same('', $comments[0]['author_url']); same("Hello\n\nWorld", $comments[0]['body']);
        same(0, $import->run('typecho', $input)->totals['translations']['imported']);
        throws(fn () => $import->run('typecho', $input + ['prefix' => 'wrong_']), RuntimeException::class);
        throws(fn () => $import->run('typecho', $input + ['prefix' => 'bad;']), InvalidArgumentException::class);
        same('/usr/uploads/pic.png', TypechoSource::attachmentPath('a:1:{s:4:"path";s:20:"/usr/uploads/pic.png";}')); same(null, TypechoSource::attachmentPath('O:4:"Evil":0:{}'));
        (new PDO('sqlite:' . $path))->exec('DROP TABLE typecho_fields'); ok(!$import->run('typecho', $input, ['check' => true])->failed());
    } finally { Core\ReleasePackage::removeTree($root); }
});
test('Import: failed content rolls back terms and media, then continues', function () {
    $root = importFixture(); $db = database(); locale($db);
    try {
        importFile($root, 'source/_posts/a.md', "---\ntitle: Rejected\ncategories: [Rollback]\n---\n![Image](a/image.png)");
        importFile($root, 'source/_posts/a/image.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j2ioAAAAASUVORK5CYII='));
        importFile($root, 'source/_posts/b.md', "---\ntitle: Accepted\n---\nGood");
        $db->query("CREATE TRIGGER reject_import BEFORE INSERT ON post_translations WHEN NEW.title = 'Rejected' BEGIN SELECT RAISE(ABORT, 'test rejection'); END");
        $r = (new Importer($db, $root . '/public', $root))->run('hexo', ['file' => $root . '/source']);
        same(true, $r->failed()); same(1, $r->totals['posts']['failed']); same(1, $r->totals['posts']['imported']);
        same(0, (int) $db->one('SELECT COUNT(*) n FROM media')['n']); same(0, (int) $db->one('SELECT COUNT(*) n FROM categories')['n']);
        same([], glob($root . '/public/uploads/media/*') ?: []); same([], glob($root . '/import-image-*'));
        same('b', $db->one('SELECT slug FROM posts')['slug']);
    } finally { Core\ReleasePackage::removeTree($root); }
});
test('Import: code stays untouched, file limits, unsafe paths and missing assets', function () {
    $warnings = [];
    $md = "````html\n<img src=\"keep\">\n```\n````\n`` a ` <b>keep</b> ``\n<pre><code class=\"lang-html\">&lt;b&gt;keep&lt;/b&gt;</code></pre>\n```html\n<i>unclosed fence</i>";
    $converted = HtmlToMarkdown::inline($md, $warnings);
    contains('<img src="keep">', $converted); contains('`` a ` <b>keep</b> ``', $converted); contains('<b>keep</b>', $converted); contains('<i>unclosed fence</i>', $converted);
    foreach (['../bad', '/bad', 'C:\\bad', "bad\0name", 'a/.hidden', 'node_modules/a', 'source/_data/a'] as $path) { same(null, Files::safe($path)); }
    $root = importFixture();
    try {
        importFile($root, 'source/_posts/a.md', '![Missing](absent.png) ![Not image](text.txt)'); importFile($root, 'source/_posts/text.txt', 'not an image');
        $db = database(); locale($db); $import = new Importer($db, $root . '/public', $root);
        $r = $import->run('hexo', ['file' => $root . '/source'], ['check' => true]); contains('Missing image', $r->text()); contains('Not a supported image', $r->text());
        importFile($root, 'source/_posts/large.md', str_repeat('x', 5242881)); throws(fn () => $import->run('hexo', ['file' => $root . '/source'], ['check' => true]), RuntimeException::class);
    } finally { Core\ReleasePackage::removeTree($root); }
});
test('Import: CLI check, help and failure exit codes', function () {
    $root = importFixture();
    try {
        $db = new Core\Database(['driver' => 'sqlite', 'path' => $root . '/meteor.db']); foreach (schemaStatements('sqlite') as $sql) { $db->query($sql); } locale($db);
        importFile($root, 'source/_posts/hello.md', "---\ntitle: CLI\n---\nBody");
        $run = function (array $args) use ($root): array {
            $process = proc_open([PHP_BINARY, 'bin/import.php', ...$args], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__), array_merge(getenv(), ['DB_DRIVER' => 'sqlite', 'DB_PATH' => $root . '/meteor.db']));
            fclose($pipes[0]); $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); return [proc_close($process), $out];
        };
        [$code, $out] = $run(['--help']); same(0, $code); contains('Usage:', $out);
        [$code, $out] = $run(['hexo', $root . '/source', '--check']); same(0, $code); contains('would import', $out); same(0, (int) $db->one('SELECT COUNT(*) n FROM posts')['n']);
        [$code, $out] = $run(['hexo', $root . '/source']); same(0, $code); contains('Import finished', $out);
        [$code] = $run(['hexo', $root . '/source', '--locale=missing']); same(1, $code);
        [$code] = $run(['hexo', $root . '/source', '--bad']); same(1, $code);
        $db->query("CREATE TRIGGER reject_cli BEFORE INSERT ON post_translations BEGIN SELECT RAISE(ABORT, 'test'); END");
        [$code, $out] = $run(['hexo', $root . '/source', '--rename']); same(1, $code); contains('failed', $out);
    } finally { Core\ReleasePackage::removeTree($root); }
});
