<?php
test('Markdown: headings, paragraphs, emphasis, inline code, links and images', function () {
    $parser = new Core\Markdown();
    $result = $parser->compile("# One\n## Two\n### Three\n#### Four\n##### Five\n###### Six\n\nA **bold** and *italic* with __strong__ and _emphasis_.\nSecond line.\n\n`<b>literal</b>` [Link](https://example.test/a \"Title\") ![Alt](images/photo.jpg)");
    foreach (['<h1 id="one">One</h1>', '<h6 id="six">Six</h6>', '<strong>bold</strong>', '<em>italic</em>', '<strong>strong</strong>', '<em>emphasis</em>', '<code>&lt;b&gt;literal&lt;/b&gt;</code>', '<a href="https://example.test/a" title="Title">Link</a>', '<img src="/images/photo.jpg" alt="Alt" loading="lazy">', "Second line.</p>"] as $part) { contains($part, $result['html']); }
    same(2, substr_count($result['html'], '<p>'));
});
test('Markdown: unordered/ordered nesting, quotes, rules and escaped fences', function () {
    $result = (new Core\Markdown())->compile("- Parent\n  - Child\n  - Child two\n- Next\n\n1. First\n   1. Nested\n2. Second\n\n> Quote **bold**\n>\n> Another paragraph\n\n---\n\n```php\n<script>alert(1)</script>\n**literal**\n```\n\n~~~css\na { color: red; }\n~~~");
    foreach (['<ul><li>Parent<ul><li>Child</li><li>Child two</li></ul>', '<ol><li>First<ol><li>Nested</li></ol>', '<blockquote><p>Quote <strong>bold</strong></p>', '<hr>', '<code class="language-php">&lt;script&gt;alert(1)&lt;/script&gt;', '**literal**', 'class="language-css"'] as $part) { contains($part, $result['html']); }
    contains('<pre><code>unclosed</code></pre>', (new Core\Markdown())->compile("```\nunclosed")['html']);
});
test('Markdown: raw HTML, dangerous schemes, attribute attacks and code isolation', function () {
    $parser = new Core\Markdown();
    foreach (['<script>alert(1)</script>', '<img src=x onerror=alert(1)>', '[bad](javascript:alert)', '[bad](JaVaScRiPt:alert)', '[bad](javascript&#58;alert)', '![bad](data:text/html,bad)', '[bad](//evil.test)', '[bad](java&#x09;script:alert)', '[bad](javascript%3Aalert)'] as $input) {
        $html = $parser->compile($input)['html'];
        ok(!str_contains($html, '<script')); ok(!str_contains($html, '<img src=x'));
        ok(!preg_match('/(?:href|src)="(?:javascript:|data:|\/\/)/i', $html));
    }
    $html = $parser->compile('![x" onerror="evil](https://safe.test/x) [x](https://safe.test/"oops) `**no** [no](https://safe.test)`')['html'];
    contains('alt="x&quot; onerror=&quot;evil"', $html);
    contains('href="https://safe.test/&quot;oops"', $html);
    contains('<code>**no** [no](https://safe.test)</code>', $html);
    ok(!str_contains($html, '<strong>no'));
});
test('Markdown: unicode IDs, collision safety, reset and nested TOC', function () {
    $parser = new Core\Markdown();
    $result = $parser->compile("## 中文标题\n### 小节\n### 小节\n## 中文标题\n## 中文标题-2\n## <script>");
    foreach (['id="中文标题"', 'id="小节"', 'id="小节-2"', 'id="中文标题-2"', 'id="中文标题-2-2"'] as $part) { contains($part, $result['html']); }
    contains('<li><a href="#中文标题">中文标题</a><ul><li>', $result['toc_html']);
    contains('</li></ul></li><li><a href="#中文标题-2">', $result['toc_html']);
    ok(!str_contains($result['toc_html'], '<script>'));
    same('<ul><li><a href="#小节">小节</a></li></ul>', $parser->compile('### 小节')['toc_html']);
    contains('id="中文标题"', $parser->compile('## 中文标题')['html']);
    same('', $parser->compile('# No TOC')['toc_html']);
});
test('Markdown: reading time counts words and CJK without markup or URL inflation', function () {
    $parser = new Core\Markdown();
    same(1, $parser->compile('')['reading_minutes']);
    same(1, $parser->compile(str_repeat('word ', 220))['reading_minutes']);
    same(2, $parser->compile(str_repeat('word ', 221))['reading_minutes']);
    same(2, $parser->compile(str_repeat("- word\n", 221))['reading_minutes']);
    same(1, $parser->compile(str_repeat('中', 400))['reading_minutes']);
    same(2, $parser->compile(str_repeat('中', 401))['reading_minutes']);
    same(2, $parser->compile(str_repeat('word ', 220) . str_repeat('中', 400))['reading_minutes']);
    same(1, $parser->compile('[one](https://example.test/' . str_repeat('many/', 500) . ')')['reading_minutes']);
});
test('Markdown: fenced highlighter preserves escaped source across all six languages', function () {
    $samples = [
        'php' => '<?php // comment' . "\n" . '$value = "<script>"; if (true) { echo $value; }',
        'js' => '// comment' . "\n" . 'const text = "<script>"; return text;',
        'css' => '/* comment */ body { color: "red"; --accent: orange; }',
        'html' => '<!-- comment --><div class="test">Hello & goodbye</div>',
        'sql' => "-- comment\nSELECT title FROM posts WHERE locale = 'en';",
        'bash' => '# comment' . "\n" . 'echo "$HOME"',
    ];
    foreach ($samples as $language => $code) {
        $html = (new Core\Markdown())->compile("```$language\n$code\n```")['html'];
        contains('syntax-comment', $html); contains('syntax-keyword', $html); contains('syntax-string', $html);
        same($code, html_entity_decode(strip_tags(trim($html)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        ok(!str_contains($html, '<script>'));
    }
    $html = Core\Highlighter::highlight('$value = "if $ignored // string"; // return', 'php');
    same(1, substr_count($html, 'syntax-variable')); same(1, substr_count($html, 'syntax-string')); same(0, substr_count($html, 'syntax-keyword'));
    same('&lt;b&gt;unknown&lt;/b&gt;', Core\Highlighter::highlight('<b>unknown</b>', 'unknown'));
});
test('Markdown: escaped quotes stay inside a single highlighted string', function () {
    $code = '$text = "say \\"hello\\" if $ignored";';
    $html = Core\Highlighter::highlight($code, 'php');
    same(1, substr_count($html, 'syntax-string'));
    same(1, substr_count($html, 'syntax-variable'));
    same(0, substr_count($html, 'syntax-keyword'));
    same($code, html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8'));
});
