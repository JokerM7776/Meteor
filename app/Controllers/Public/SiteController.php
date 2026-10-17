<?php
declare(strict_types=1);
namespace App\Controllers\Public;

use App\Repositories\{Posts, Pages, Taxonomy, Settings};
use Core\{Database, I18n, View};

final class SiteController
{
    public Posts $posts;
    public Pages $pages;
    public Taxonomy $taxonomy;
    public Settings $settings;
    public function __construct(Database $db, private I18n $i18n, private View $view, private bool $admin = false)
    {
        $this->posts = new Posts($db);
        $this->pages = new Pages($db);
        $this->taxonomy = new Taxonomy($db);
        $this->settings = new Settings($db, $i18n);
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
        return $this->view->render('public/' . $template, $data + [
            'i18n' => $this->i18n, 'settings' => $this->settings, 'view' => $this->view,
            'locale' => $locale, 'registry' => $this->i18n->registry(), 'preview' => $preview,
            'live' => $this->i18n->enabledLocales(), 'switcher' => $switcher,
            'alternates' => $preview || $status !== 200 ? [] : $alternates,
            'canonical' => $status === 200 ? '/' . $locale . $suffix : null,
            'title' => $this->settings->label('site_title'), 'description' => '',
            'active' => '', 'is404' => $status === 404, 'noindex' => $preview || $status !== 200,
        ], 'layouts/public');
    }
    private function decorate(array $posts): array
    {
        foreach ($posts as &$post) { $post['translations'] = $this->posts->translations((int) $post['id'], $this->admin); }
        return $posts;
    }
    public function home(string $locale): string
    {
        $this->i18n->setLocale($locale, $this->admin);
        $posts = $this->decorate($this->posts->published($locale));
        return $this->render('home', $locale, [
            'pinned' => array_filter($posts, fn ($p) => (bool) $p['is_pinned']),
            'mobileLatest' => array_slice($posts, 0, 4),
            'latest' => array_slice(array_values(array_filter($posts, fn ($p) => !$p['is_pinned'])), 0, 5),
            'translations' => array_values($this->i18n->registry()),
            'description' => $this->settings->get('home_intro', $locale, $this->i18n->translate('home_intro')),
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
            $groups[substr($post['published_at'], 0, 4)][substr($post['published_at'], 0, 7)][] = $post;
        }
        $suffix = '/' . $kind . ($slug !== null ? '/' . rawurlencode($slug) : '');
        return $this->render('archive', $locale, compact('categories', 'tags', 'selected', 'groups', 'page', 'totalPages', 'suffix') + [
            'title' => $selected['name'] ?? __($kind === 'tags' ? 'tags' : 'archive'), 'active' => $kind === 'tags' ? 'tags' : 'posts',
            'description' => __('archive_description', ['language' => $this->i18n->registry()[$locale]['name']]),
            'canonical' => '/' . $locale . $suffix . ((int) $page > 1 ? '?page=' . $page : ''),
        ]);
    }
    public function item(string $locale, string $slug, bool $page = false): string
    {
        $repository = $page ? $this->pages : $this->posts;
        $item = $repository->find($locale, $slug);
        if (!$item) { return $this->notFound('/' . $locale . '/'); }
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
            'active' => $page ? $slug : 'posts',
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
