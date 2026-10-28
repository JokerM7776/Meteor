<?php
declare(strict_types=1);
namespace App\Controllers\Public;

use App\Repositories\{Announcements, Menus, Posts, Pages, Search, Taxonomy, Settings};
use Core\{Database, I18n, View};

final class SiteController
{
    public Posts $posts;
    public Pages $pages;
    public Taxonomy $taxonomy;
    public Settings $settings;
    public Menus $menus;
    public Announcements $announcements;
    public function __construct(private Database $db, private I18n $i18n, private View $view, private bool $admin = false)
    {
        $this->posts = new Posts($db);
        $this->pages = new Pages($db);
        $this->taxonomy = new Taxonomy($db);
        $this->settings = new Settings($db, $i18n);
        $this->menus = new Menus($db);
        $this->announcements = new Announcements($db);
    }
    public function render(string $template, string $locale, array $data = [], int $status = 200): string
    {
        $this->i18n->setLocale($locale, $this->admin);
        http_response_code($status);
        $preview = $this->i18n->registry()[$locale]['status'] === 'preview';
        if ($this->admin) { header('Cache-Control: private, no-store'); }
        if ($preview) { header('X-Robots-Tag: noindex, nofollow'); }
        $translations = $data['translations'] ?? [];
        $suffix = $data['suffix'] ?? '/';
        $alternates = [];
        foreach ($translations as $translation) {
            if ($translation['status'] === 'live') { $alternates[$translation['code']] = '/' . $translation['code'] . $suffix; }
        }
        if (isset($alternates[$this->i18n->defaultLocale()])) { $alternates['x-default'] = $alternates[$this->i18n->defaultLocale()]; }
        $switcher = [];
        foreach ($this->i18n->enabledLocales($this->admin) as $code) {
            $switcher[$code] = '/' . $code . (in_array($code, array_column($translations, 'code'), true) ? $suffix : '/');
        }
        $published = $this->pages->published($locale);
        $menu = ['locale' => $locale, 'default' => $this->i18n->defaultLocale(), 'preview' => $preview, 'active' => $data['active'] ?? '',
            'pages' => $published,
            'path' => (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), 'translate' => fn (string $key) => $this->i18n->translate($key)];
        $themeCss = \App\Services\Appearance::css($this->settings);
        $data = $data + [
            'scheme' => \App\Services\Appearance::scheme($this->settings, $_COOKIE['meteor_theme'] ?? null),
            'fontSource' => $this->settings->global('font_source', 'local'),
            'themeSwitch' => $this->settings->global('theme_switch', '1') === '1',
            'themeCss' => $themeCss === '' ? '' : '/theme.css?v=' . substr(hash('sha256', $themeCss), 0, 10),
            'author' => \App\Services\Author::card($this->settings, $locale),
            'i18n' => $this->i18n, 'settings' => $this->settings, 'view' => $this->view,
            'locale' => $locale, 'registry' => $this->i18n->registry(), 'preview' => $preview,
            'headerLinks' => $this->menus->links('header', $menu), 'footerLinks' => $this->menus->links('footer', $menu),
            'live' => $this->i18n->enabledLocales(), 'switcher' => $switcher,
            'shareImage' => $status === 200 ? \App\Services\ShareImage::pick($this->db, $this->settings, $data['item']['cover_image'] ?? null) : null,
            'announcements' => $status === 200 ? $this->announcements->live($locale) : [],
            'alternates' => $preview || $status !== 200 ? [] : $alternates,
            'canonical' => $status === 200 ? '/' . $locale . $suffix : null,
            'title' => $this->settings->label('site_title'), 'description' => '',
            'active' => '', 'is404' => $status === 404, 'noindex' => $preview || $status !== 200,
        ];
        $themes = new \App\Services\Themes($this->db);
        $theme = $themes->current($this->admin);
        $this->view->setThemeRoot($theme['path'] ?? null);
        $api = (new \App\Theme\Builder($this->settings, $this->i18n))->build($template, $data) + ['theme' => $themes->object($theme)];
        $html = \App\Theme\Context::render($this->view, $api, fn () => $this->view->render('public/' . $template, $api, 'layouts/public'));
        $previewing = $themes->previewing($this->admin);
        if ($previewing !== null) {
            // Meteor's own banner, outside the theme, so a preview can always be stopped.
            $banner = $this->view->partial('system/theme-preview', ['name' => $previewing['name']]);
            $html = preg_replace('~<body\b[^>]*>~', '$0' . str_replace(['\\', '$'], ['\\\\', '\\$'], $banner), $html, 1) ?? $html;
        }
        return $html;
    }
    public function themes(): \App\Services\Themes { return new \App\Services\Themes($this->db); }
    private function decorate(array $posts): array
    {
        foreach ($posts as &$post) { $post['translations'] = $this->posts->translations((int) $post['id'], $this->admin); }
        return $posts;
    }
    public function home(string $locale): string
    {
        $this->i18n->setLocale($locale, $this->admin);
        $sections = array_keys(array_filter(\App\Services\HomePage::sections($this->settings)));
        $posts = $this->decorate($this->posts->published($locale));
        $pinned = in_array('pinned', $sections, true) ? array_values(array_filter($posts, fn ($p) => (bool) $p['is_pinned'])) : [];
        $categories = [];
        foreach ($posts as $post) {
            if ($post['category_slug'] === null) { continue; }
            $categories[$post['category_slug']] ??= ['slug' => $post['category_slug'], 'name' => $post['category_name'] ?: $post['category_slug'], 'count' => 0];
            $categories[$post['category_slug']]['count']++;
        }
        $intro = $this->settings->get('home_intro', $locale) ?: $this->settings->get('site_tagline', $locale);
        return $this->render('home', $locale, [
            'sections' => $sections, 'pinned' => $pinned, 'hasPosts' => $posts !== [],
            'latest' => array_slice(array_values(array_filter($posts, fn ($p) => !$pinned || !$p['is_pinned'])), 0, \App\Services\HomePage::latestCount($this->settings)),
            'categories' => array_values($categories),
            'eyebrow' => $this->settings->get('home_eyebrow', $locale),
            'heading' => $this->settings->get('home_heading', $locale) ?: $this->settings->label('site_title'),
            'intro' => $intro,
            'buttons' => \App\Services\HomePage::buttons($this->settings, $locale, fn (string $key) => $this->i18n->translate($key, ['language' => $this->i18n->registry()[$locale]['native_name']])),
            'decoration' => $this->settings->global('home_decoration', '1') === '1',
            'translations' => array_values($this->i18n->registry()),
            'description' => $intro,
        ]);
    }
    public function archive(string $locale, string $kind = 'posts', ?string $slug = null): string
    {
        $this->i18n->setLocale($locale, $this->admin);
        $categories = $this->taxonomy->categories($locale);
        $tags = $this->taxonomy->tags($locale);
        $selected = null;
        if ($slug !== null) {
            foreach ($kind === 'categories' ? $categories : $tags as $term) {
                if ($term['slug'] === $slug) { $selected = $term; break; }
            }
            if ($selected === null) { return $this->notFound('/' . $locale . '/'); }
        }
        $posts = $this->posts->published($locale, $kind === 'categories' ? (int) $selected['id'] : null,
            $kind === 'tags' && $selected ? (int) $selected['id'] : null);
        $page = $_GET['page'] ?? '1';
        $perPage = max(1, min(100, (int) $this->settings->get('posts_per_page', null, '10')));
        $totalPages = max(1, (int) ceil(count($posts) / $perPage));
        if (!is_string($page) || !preg_match('/^[1-9][0-9]*$/D', $page) || strlen($page) > 9 || (int) $page > $totalPages) {
            return $this->notFound('/' . $locale . '/');
        }
        $groups = [];
        foreach (array_slice($posts, ((int) $page - 1) * $perPage, $perPage) as $post) {
            $local = \Core\Dates::local($post['published_at']);
            $groups[$local->format('Y')][$local->format('Y-m')][] = $post;
        }
        $suffix = '/' . $kind . ($slug !== null ? '/' . rawurlencode($slug) : '');
        return $this->render('archive', $locale, compact('categories', 'tags', 'selected', 'groups', 'page', 'totalPages', 'suffix') + [
            'title' => $selected['name'] ?? __($kind === 'tags' ? 'tags' : 'archive'), 'active' => $kind === 'tags' ? 'tags' : 'posts',
            'description' => __('archive_description', ['language' => $this->i18n->registry()[$locale]['native_name']]),
            'canonical' => '/' . $locale . $suffix . ((int) $page > 1 ? '?page=' . $page : ''),
        ]);
    }
    public function item(string $locale, string $slug, bool $page = false, array $form = [], int $status = 200): string
    {
        $repository = $page ? $this->pages : $this->posts;
        $item = $repository->find($locale, $slug);
        if (!$item) { return $this->notFound('/' . $locale . '/'); }
        $discussion = $page ? null : $this->discussion($item, $locale, $form);
        $webmention = '';
        if (!$page && (int) $item['comments_enabled'] === 1 && (new \App\Services\CommentSettings($this->db))->receivesWebmentions()) {
            $webmention = absolute_url('/webmention');
            header('Link: <' . $webmention . '>; rel="webmention"', false);
        }
        $neighbors = [];
        if (!$page) {
            $posts = $this->posts->published($locale);
            $index = array_search($item['id'], array_column($posts, 'id'), true);
            $neighbors = ['prev' => $posts[$index + 1] ?? null, 'next' => $posts[$index - 1] ?? null];
        }
        return $this->render('item', $locale, [
            'item' => $item, 'isPage' => $page, 'title' => $item['title'], 'description' => $item['summary'],
            'translations' => $repository->translations((int) $item['id'], $this->admin),
            'suffix' => ($page ? '/' : '/posts/') . rawurlencode($slug), 'neighbors' => $neighbors,
            'tags' => $page ? [] : $this->taxonomy->tags($locale, (int) $item['id']),
            'active' => $page ? $slug : 'posts', 'discussion' => $discussion, 'webmention' => $webmention,
        ], $status);
    }
    /** Comments, mentions and the form below a post; null when comments are off site-wide or there is nothing to show. */
    private function discussion(array $item, string $locale, array $form): ?array
    {
        $comments = new \App\Services\CommentForm($this->db);
        if (!$comments->settings()->enabled()) { return null; }
        $data = (new \App\Repositories\Comments($this->db))->discussion((int) $item['id'], $locale);
        $open = (int) $item['comments_enabled'] === 1;
        if (!$open && !$data['threads'] && !$data['reactions']) { return null; }
        $values = ($form['values'] ?? []) + ['name' => '', 'email' => '', 'url' => '', 'body' => '', 'token' => $comments->token((int) $item['id'], $locale), 'parent_id' => 0];
        $reply = $values['parent_id'] ?: (int) ($_GET['reply'] ?? 0);
        $replyTo = null;
        foreach ($data['threads'] as $thread) {
            foreach ([$thread, ...$thread['replies']] as $row) { if ((int) $row['id'] === $reply) { $replyTo = $row; } }
        }
        $notice = $_GET['comment'] ?? '';
        return $data + ['open' => $open, 'values' => $values, 'error' => $form['error'] ?? '', 'field' => $form['field'] ?? '', 'replyTo' => $replyTo,
            'notice' => in_array($notice, ['pending', 'published'], true) && !$form ? $notice : '',
            'action' => '/' . $locale . '/posts/' . rawurlencode((string) $item['slug']) . '/comments',
            'url' => '/' . $locale . '/posts/' . rawurlencode((string) $item['slug'])];
    }
    /** POST /webmention (W3C Webmention receiving endpoint). */
    public function webmention(): string
    {
        header('Content-Type: text/plain; charset=UTF-8');
        header('Cache-Control: no-store');
        [$status, $message] = (new \App\Services\Webmentions($this->db, $GLOBALS['config']['app_url']))
            ->receive($_POST['source'] ?? null, $_POST['target'] ?? null, (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        http_response_code($status);
        if ($status === 202) {
            $db = $this->db;
            \App\Services\Webmentions::later(static fn () => (new \App\Services\Webmentions($db, $GLOBALS['config']['app_url']))->process(5));
        }
        return $message . "\n";
    }
    /** POST /{locale}/posts/{slug}/comments: save, show the CAPTCHA page, or show the post with the problem. */
    public function comment(string $locale, string $slug): string
    {
        header('Cache-Control: no-store');
        $item = $this->posts->find($locale, $slug);
        $form = new \App\Services\CommentForm($this->db);
        if (!$item || !$form->settings()->enabled() || (int) $item['comments_enabled'] !== 1) { return $this->notFound('/' . $locale . '/'); }
        $url = '/' . $locale . '/posts/' . rawurlencode($slug);
        $outcome = $form->submit($item, $locale, $_POST, (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        if ($outcome['result'] === 'saved' || $outcome['result'] === 'dropped') {
            $approved = ($outcome['status'] ?? '') === 'approved';
            header('Location: ' . $url . '?comment=' . ($approved ? 'published#comment-' . $outcome['id'] : 'pending#comments'), true, 303);
            return '';
        }
        if ($outcome['result'] === 'error') { return $this->item($locale, $slug, false, $outcome, 422); }
        $captcha = $form->settings()->captcha();
        $this->i18n->setLocale($locale, $this->admin);
        $html = $this->render('comment-check', $locale, ['title' => __('comment_check_title'), 'captcha' => $captcha, 'postUrl' => $url,
            'action' => $url . '/comments', 'values' => $outcome['values'], 'error' => $outcome['error'] ?? '', 'noindex' => true, 'canonical' => null]);
        // The one public page that runs a script: the CAPTCHA widget, after the visitor chose to comment.
        header('Content-Security-Policy: ' . security_policy($captcha['scripts'], $captcha['frames']));
        return $html;
    }
    public function search(string $locale): string
    {
        // With search turned off, /search is an ordinary page slug again.
        if (!$this->settings->enabled('header_show_search')) { return $this->item($locale, 'search', true); }
        $this->i18n->setLocale($locale, $this->admin);
        $query = $_GET['q'] ?? '';
        $query = is_string($query) && mb_check_encoding($query, 'UTF-8') ? mb_substr(trim($query), 0, Search::MAX_QUERY) : '';
        return $this->render('search', $locale, [
            'query' => $query, 'results' => $query === '' ? [] : (new Search($this->db))->find($locale, $query),
            'title' => $query === '' ? __('search') : __('search_results', ['query' => $query]),
            'description' => __('search_hint', ['language' => $this->i18n->registry()[$locale]['native_name']]),
            'active' => 'search', 'suffix' => '/search', 'noindex' => true, 'canonical' => null,
        ]);
    }
    public function notFound(string $uri): string
    {
        $prefix = explode('/', rawurldecode((string) parse_url($uri, PHP_URL_PATH)))[1] ?? '';
        $locale = in_array($prefix, $this->i18n->enabledLocales($this->admin), true) ? $prefix : $this->i18n->defaultLocale();
        $this->i18n->setLocale($locale, $this->admin);
        return $this->render('not-found', $locale, ['title' => __('not_found'), 'requestedPath' => (string) parse_url($uri, PHP_URL_PATH)], 404);
    }
}
