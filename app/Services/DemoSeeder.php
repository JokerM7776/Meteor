<?php
declare(strict_types=1);
namespace App\Services;
use Core\{Database, Markdown};

final class DemoSeeder
{
    public function __construct(private Database $db) {}
    public function run(bool $demoLocales = false): void
    {
        $this->db->transaction(function () use ($demoLocales): void {
            if (!$this->db->one('SELECT code FROM locales WHERE code = ?', ['en'])) {
                $this->db->upsert('locales', ['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'status' => 'live', 'is_default' => 1], ['code']);
            }
            if ($demoLocales) {
                // Interface text comes from lang/zh-hans.json and lang/ms.json. Languages the installer added
                // as Off are switched on for the demo; any other existing status is kept.
                foreach ([['zh-hans', 'Chinese (Simplified)', '简体中文', 'live', 1], ['ms', 'Malay', 'Bahasa Melayu', 'preview', 2]] as [$code, $name, $native, $status, $order]) {
                    $row = $this->db->one('SELECT status FROM locales WHERE code = ?', [$code]);
                    if (!$row) { $this->db->upsert('locales', ['code' => $code, 'name' => $name, 'native_name' => $native, 'status' => $status, 'sort_order' => $order], ['code']); }
                    elseif ($row['status'] === 'off') { $this->db->query('UPDATE locales SET status = ? WHERE code = ?', [$status, $code]); }
                }
            }
            $locales = $demoLocales ? ['en', 'zh-hans', 'ms'] : ['en'];
            $categories = [];
            foreach (['engineering' => 'Engineering', 'css' => 'CSS', 'seo' => 'SEO', 'travel' => 'Travel'] as $slug => $name) {
                $this->db->upsert('categories', ['slug' => $slug], ['slug']);
                $id = (int) $this->db->one('SELECT id FROM categories WHERE slug = ?', [$slug])['id'];
                $categories[$slug] = $id;
                foreach ($locales as $locale) { $this->db->upsert('category_translations', ['category_id' => $id, 'locale' => $locale, 'name' => $name], ['category_id', 'locale']); }
            }
            $tags = [];
            foreach (['php', 'zero-js', 'markdown', 'i18n', 'architecture', 'dark-mode', 'malaysia'] as $slug) {
                $this->db->upsert('tags', ['slug' => $slug], ['slug']);
                $id = (int) $this->db->one('SELECT id FROM tags WHERE slug = ?', [$slug])['id'];
                $tags[$slug] = $id;
                foreach ($locales as $locale) { $this->db->upsert('tag_translations', ['tag_id' => $id, 'locale' => $locale, 'name' => $slug], ['tag_id', 'locale']); }
            }
            $data = require __DIR__ . '/demo-posts.php';
            foreach ($data as $index => [$slug, $title, $summary, $date, $category, $postTags, $body]) {
                $this->db->upsert('posts', ['slug' => $slug, 'base_locale' => 'en', 'category_id' => $categories[$category], 'is_published' => 1, 'is_pinned' => (int) in_array($index, [0, 3], true), 'published_at' => $date . ' 08:00:00'], ['slug']);
                $id = (int) $this->db->one('SELECT id FROM posts WHERE slug = ?', [$slug])['id'];
                $this->translation('post', $id, 'en', $title, $summary, $body);
                foreach ($postTags as $tag) { $this->db->upsert('post_tags', ['post_id' => $id, 'tag_id' => $tags[$tag]], ['post_id', 'tag_id']); }
                if ($demoLocales && in_array($index, [0, 1, 3, 4, 6], true)) {
                    $zhTitles = [0 => '用原生 PHP 打造零 JS 博客', 1 => '保存时预编译 Markdown', 3 => '每种翻译都需要独立的网址', 4 => '一个媒体查询实现深色模式', 6 => '不需要 JavaScript 的菜单'];
                    $this->translation('post', $id, 'zh-hans', $zhTitles[$index], '用简单的工具构建快速、易读的多语言网站。', "大多数博客发出的 JavaScript 比文字还多，而这个博客一行也没有。公开页面由原生 PHP 渲染，样式只有一个 CSS 文件。\n\n## 单一入口\n\n所有请求都会被重写到 `public/index.php`。路由器读取路径，取出语言前缀。\n\n```php\n\$locale = \$registry[\$code];\n```\n\n## 保存时编译\n\n文章保存时生成 HTML，阅读时直接显示。\n\n> 最快的代码，是已经运行过的代码。\n\n- 简单的模板\n- 清晰的链接\n- 可访问的导航");
                }
                if ($demoLocales && in_array($index, [0, 1, 4, 5], true)) {
                    $msTitles = [0 => 'Membina blog tanpa JS dengan PHP', 1 => 'Kompilasi Markdown semasa menyimpan', 4 => 'Mod gelap dengan satu pertanyaan media', 5 => 'Kuala Lumpur melalui tiga gerai makanan'];
                    $this->translation('post', $id, 'ms', $msTitles[$index], 'Catatan tentang web yang ringkas, pantas dan mudah dibaca.', "Laman yang ringkas memberi ruang kepada tulisan. Setiap halaman dihantar sebagai HTML dan CSS.\n\n## Mula dengan asas\n\nGunakan pautan biasa dan templat PHP yang mudah dibaca.\n\n```php\necho \$title;\n```\n\n> Kesederhanaan memudahkan penyelenggaraan.\n\n- Tulis kandungan\n- Semak terjemahan\n- Terbitkan halaman");
                }
            }
            $this->db->upsert('pages', ['slug' => 'about', 'base_locale' => 'en', 'is_published' => 1], ['slug']);
            $id = (int) $this->db->one('SELECT id FROM pages WHERE slug = ?', ['about'])['id'];
            $this->translation('page', $id, 'en', 'About Meteor', 'Server-rendered HTML and CSS. Zero JavaScript on public pages.', "Meteor is a small place for notes on engineering, design and everyday life.\n\n## A quieter web\n\nEvery page is plain HTML and CSS — no scripts, no trackers, nothing to wait for.\n\n## Read your way\n\n- Browse the archive\n- Follow a language’s RSS feed\n- Share a direct link to a translation\n\n> Good tools leave room for the writing.");
            (new \App\Repositories\Menus($this->db))->ensureDefaults();
            if ($demoLocales) { $this->translation('page', $id, 'zh-hans', '关于 Meteor', '一个简单的多语言博客。', "## 关于本站\n\n这里记录工程、设计与日常生活。\n\n## 阅读方式\n\n通过归档和 RSS 阅读文章。"); }
        });
    }
    private function translation(string $type, int $id, string $locale, string $title, string $summary, string $markdown): void
    {
        $compiled = (new Markdown())->compile($markdown);
        $this->db->upsert($type . '_translations', [$type . '_id' => $id, 'locale' => $locale, 'title' => $title, 'summary' => $summary,
            'content_md' => $markdown, 'content_html' => $compiled['html'], 'toc_html' => $compiled['toc_html'],
            'reading_minutes' => $compiled['reading_minutes'], 'status' => 'published'], [$type . '_id', 'locale']);
    }
}
