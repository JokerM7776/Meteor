<?php
declare(strict_types=1);
namespace App\Theme;

use App\Repositories\Settings;
use Core\{I18n, Dates};

/** Maps what the public controller loaded into Theme API values; runs no queries of its own beyond settings. */
final class Builder
{
    public function __construct(private Settings $settings, private I18n $i18n) {}

    public static function image(?string $url, array $meta = []): ?Image
    {
        if ($url === null || $url === '') { return null; }
        return new Image($url, (int) ($meta['width'] ?? 0), (int) ($meta['height'] ?? 0), (string) ($meta['alt'] ?? ''),
            $meta['thumbUrl'] ?? null, (string) ($meta['type'] ?? \App\Services\SiteIcon::type($url)));
    }

    public static function fonts(string $source): Fonts
    {
        [$from, $preload] = \App\Services\Fonts::resolve($source);
        return new Fonts($from, array_map(fn ($f) => '/' . \App\Services\Fonts::DIRECTORY . '/' . $f, $preload),
            $from === 'local' ? \asset(\App\Services\Fonts::DIRECTORY . '/fonts.css') : ($from === 'google' ? \App\Services\Fonts::GOOGLE : ''));
    }

    private function language(string $code, ?string $url = null): Language
    {
        $r = $this->i18n->registry()[$code];
        return new Language(
            code: $code, name: $r['name'], nativeName: $r['native_name'], dir: $r['direction'], url: $url ?? '/' . $code . '/',
            current: $code === $this->i18n->locale(), preview: $r['status'] === 'preview',
            shortLabel: $this->settings->get('language_label', $code, mb_strlen($r['native_name']) <= 4 ? $r['native_name'] : strtoupper($code)),
            homeUrl: '/' . $code . '/', feedUrl: '/' . $code . '/rss.xml');
    }

    private function date(?string $value): ?Date
    {
        if ($value === null || $value === '') { return null; }
        return new Date($value, new \DateTimeImmutable($value, new \DateTimeZone('UTC')), \localized_date($value),
            \localized_date($value, 'dd'), \localized_date($value, 'LLLL'), Dates::time($value, $this->i18n->locale()));
    }

    private function link(array $r): Link
    {
        return new Link((string) $r['label'], (string) $r['url'], (bool) ($r['current'] ?? false), (bool) ($r['external'] ?? false),
            (bool) ($r['new_tab'] ?? false), (bool) ($r['email'] ?? false));
    }

    private function term(array $r, string $kind): Term
    {
        return new Term($r['slug'], $r['name'] ?? $r['slug'], '/' . $this->i18n->locale() . '/' . $kind . '/' . rawurlencode($r['slug']),
            (int) ($r['count'] ?? 0), (string) ($r['description'] ?? ''));
    }

    /** A post or page row. $translations: rows with code (and the item URL suffix to build their links). */
    private function post(array $r, bool $isPage = false, array $tags = []): Post
    {
        $suffix = ($isPage ? '/' : '/posts/') . rawurlencode($r['slug']);
        return new Post(
            id: (int) $r['id'], isPage: $isPage, slug: $r['slug'], url: '/' . $this->i18n->locale() . $suffix,
            title: $r['title'], summary: (string) $r['summary'], html: (string) ($r['content_html'] ?? ''), toc: (string) ($r['toc_html'] ?? ''),
            readingMinutes: (int) ($r['reading_minutes'] ?? 1), published: $isPage ? null : $this->date($r['published_at'] ?? null),
            updated: $this->date($r['updated_at'] ?? null), cover: self::image($r['cover_image'] ?? null),
            // Like the original templates: a category without a name in this language is not shown.
            category: empty($r['category_name']) ? null : $this->term(['slug' => $r['category_slug'], 'name' => $r['category_name']], 'categories'),
            tags: array_map(fn ($t) => $this->term($t, 'tags'), $tags),
            translations: array_map(fn ($t) => $this->language($t['code'], '/' . $t['code'] . $suffix), $r['translations'] ?? []),
            pinned: (bool) ($r['is_pinned'] ?? false), commentsEnabled: (bool) ($r['comments_enabled'] ?? false), commentCount: null,
            language: $this->language($this->i18n->locale()));
    }

    private function comment(array $r): Comment
    {
        $name = $r['author_name'] !== '' ? $r['author_name'] : (string) parse_url((string) $r['source_url'], PHP_URL_HOST);
        $url = $r['author_url'] !== '' && \App\Services\CommentForm::validUrl($r['author_url']) ? $r['author_url'] : '';
        return new Comment((int) $r['id'], $name, $url, \App\Repositories\Comments::html((string) $r['body']), $this->date($r['created_at']),
            (bool) $r['is_owner'], (string) $r['kind'], (string) $r['source_url'], (string) $r['mention_type'], array_map($this->comment(...), $r['replies'] ?? []));
    }

    /** @return array{site:Site,page:Page} */
    public function build(string $template, array $d): array
    {
        $s = $this->settings; $code = $this->i18n->locale(); $language = $this->language($code);
        $links = fn (array $rows) => array_map($this->link(...), $rows);
        $languages = [];
        foreach ($d['switcher'] as $c => $url) { $languages[$c] = $this->language($c, $url); }
        $live = [];
        foreach ($d['live'] as $c) { $live[$c] = $this->language($c); }
        $author = null;
        if ($d['author']) {
            $a = $d['author'];
            $author = new Author($a['name'], $a['bio'], self::image($a['avatar'] ?: null), $links($a['links']));
        }
        $brand = $s->global('footer_brand', 'site');
        if ($brand === 'author' && $author === null) { $brand = 'site'; }
        $back = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $schemes = [];
        foreach (['auto', 'light', 'dark'] as $option) {
            $schemes[$option] = new Link(\__('scheme_' . $option), '/theme?' . http_build_query(['set' => $option, 'back' => $back]), $d['scheme'] === $option, false, false, false);
        }
        $icon = $s->icon();
        $copyright = $s->enabled('footer_show_copyright')
            ? str_replace(['{year}', '{site}'], [(new \DateTimeImmutable('now', Dates::zone()))->format('Y'), $s->label('site_title')], $s->get('copyright', $code, '© {year} {site}')) : '';
        $site = new Site(
            title: $s->label('site_title'), tagline: $s->get('site_tagline', $code), url: rtrim((string) ($GLOBALS['config']['app_url'] ?? ''), '/'),
            icon: self::image($icon ?: null), favicon: self::image($icon ?: '/assets/favicon.svg'),
            locale: $language, languages: $languages, liveLanguages: $live, defaultLanguage: $this->language($this->i18n->defaultLocale()),
            header: new Header($s->enabled('header_show_icon'), $s->enabled('header_show_title'), $s->enabled('header_show_switcher'),
                $s->enabled('header_show_search'), $s->enabled('header_new_tab_icon'), $links($d['headerLinks'])),
            footer: new Footer($s->get('footer_text', $code), $copyright, $brand, $s->enabled('footer_show_languages'), $s->enabled('footer_show_feeds'),
                $s->enabled('footer_show_site'), $s->get('footer_menu_heading', $code, \__('site')), $s->enabled('footer_new_tab_icon'), $links($d['footerLinks'])),
            author: $author, postAuthorCard: $s->global('post_author_card') === '1',
            announcements: array_map(fn ($a) => new Announcement($a['title'], $a['message'], $a['button_text'], $a['button_url'], (bool) $a['external']), $d['announcements']),
            appearance: new Appearance($d['scheme'], $d['themeSwitch'], $schemes, $d['themeCss'], trim($s->get('custom_css')) !== ''),
            fonts: self::fonts($d['fontSource']), feedUrl: $d['preview'] ? '' : '/' . $code . '/rss.xml',
            searchUrl: '/' . $code . '/search', homeUrl: '/' . $code . '/', postsUrl: '/' . $code . '/posts');

        $post = isset($d['item']) ? $this->post($d['item'] + ['translations' => $d['translations']], (bool) $d['isPage'], $d['tags'] ?? []) : null;
        $archive = [];
        foreach ($d['groups'] ?? [] as $year => $months) {
            $list = [];
            foreach ($months as $month => $rows) { $list[] = new ArchiveMonth((string) $month, \localized_date($month . '-01', 'LLLL'), array_map(fn ($p) => $this->post($p), $rows)); }
            $archive[] = new ArchiveYear((string) $year, $list);
        }
        $pagination = null;
        if (isset($d['totalPages'])) {
            $n = (int) $d['page']; $total = (int) $d['totalPages'];
            $url = fn (int $i) => '/' . $code . $d['suffix'] . ($i > 1 ? '?page=' . $i : '');
            $pages = [];
            for ($i = max(1, $n - 2); $i <= min($total, $n + 2); $i++) { $pages[] = new Link((string) $i, $url($i), $i === $n, false, false, false); }
            $pagination = new Pagination($n, $total, $n > 1 ? $url($n - 1) : null, $n < $total ? $url($n + 1) : null, $pages);
        }
        $comments = null;
        if ($v = $d['discussion'] ?? null) {
            $comments = new Comments(array_map($this->comment(...), $v['threads']), array_map($this->comment(...), $v['reactions']), (int) $v['count'], (bool) $v['open'],
                $v['values'], (string) $v['error'], (string) $v['field'], $v['replyTo'] ? $this->comment($v['replyTo']) : null, (string) $v['notice'],
                $v['action'], $v['url'], \App\Repositories\Comments::MAX_BODY);
        }
        $home = null;
        if ($template === 'home') {
            $buttons = array_values(array_filter($d['buttons'], fn ($b) => !($d['preview'] && str_ends_with($b['url'], 'rss.xml'))));
            $home = new Home($d['sections'], (bool) $d['hasPosts'], $d['eyebrow'], $d['heading'], $d['intro'], $links($buttons), (bool) $d['decoration']);
        }
        $check = null;
        if ($template === 'comment-check') {
            $c = $d['captcha'];
            $check = new CommentCheck($d['values'], (string) $d['error'], $d['action'], $d['postUrl'], \App\Repositories\Comments::html((string) $d['values']['body']),
                (string) $c['widget'], (string) $c['site_key'], (string) $c['script']);
        }
        $type = match ($template) {
            'item' => $post->isPage ? 'page' : 'post',
            'archive' => $d['suffix'] === '/tags' ? 'tags' : 'archive',
            'not-found' => 'not_found', 'comment-check' => 'comment_check', default => $template,
        };
        $termKind = str_starts_with((string) ($d['suffix'] ?? ''), '/categories/') ? 'categories' : 'tags';
        $page = new Page(
            type: $type, title: $d['title'], description: $d['description'],
            canonical: $d['canonical'] === null ? null : \absolute_url($d['canonical']),
            alternates: array_map(\absolute_url(...), $d['alternates']), noindex: (bool) $d['noindex'],
            shareImage: isset($d['shareImage']) ? self::image($d['shareImage']['url'], $d['shareImage']) : null,
            language: $language, post: $post,
            posts: array_map(fn ($p) => $this->post($p), $d['latest'] ?? []),
            pinned: array_map(fn ($p) => $this->post($p), $d['pinned'] ?? []),
            pagination: $pagination,
            term: empty($d['selected']) ? null : $this->term($d['selected'], $termKind),
            categories: array_map(fn ($t) => $this->term($t, 'categories'), $template === 'item' ? [] : ($d['categories'] ?? [])),
            tags: array_map(fn ($t) => $this->term($t, 'tags'), $template === 'item' ? [] : ($d['tags'] ?? [])),
            archive: $archive, query: (string) ($d['query'] ?? ''), queryMaxLength: \App\Repositories\Search::MAX_QUERY,
            results: array_map(fn ($r) => new SearchResult('/' . $code . ($r['type'] === 'post' ? '/posts/' : '/') . rawurlencode($r['slug']), $r['title'],
                $this->date($r['published_at']), (string) $r['snippet']), $d['results'] ?? []),
            comments: $comments, neighbors: array_map(fn ($p) => $p ? $this->post($p) : null, $d['neighbors'] ?? []),
            home: $home, toc: $post?->toc ?? '', requestedPath: (string) ($d['requestedPath'] ?? ''), active: (string) $d['active'],
            suffix: (string) ($d['suffix'] ?? '/'), webmention: (string) ($d['webmention'] ?? ''), commentCheck: $check);
        return ['site' => $site, 'page' => $page];
    }
}
