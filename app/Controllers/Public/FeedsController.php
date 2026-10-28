<?php
declare(strict_types=1);
namespace App\Controllers\Public;
use Core\I18n;

final class FeedsController
{
    public function __construct(private SiteController $site, private I18n $i18n, private bool $admin = false) {}
    private function xml(string $text): string { return htmlspecialchars($text, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8'); }
    public function rss(string $locale): string
    {
        if (!in_array($locale, $this->i18n->enabledLocales(), true)) { return $this->site->notFound('/' . $locale . '/'); }
        header('Content-Type: application/rss+xml; charset=UTF-8');
        $xml = '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel><title>'
            . $this->xml($this->site->settings->get('site_title', $locale, 'Meteor Blog')) . '</title><link>'
            . $this->xml(absolute_url('/' . $locale . '/')) . '</link><language>' . $this->xml($locale)
            . '</language><description>' . $this->xml($this->site->settings->get('home_intro', $locale) ?: $this->site->settings->get('site_tagline', $locale) ?: $this->site->settings->get('site_title', $locale, 'Meteor Blog')) . '</description>';
        foreach (array_slice($this->site->posts->published($locale), 0, 20) as $post) {
            $url = $this->xml(absolute_url('/' . $locale . '/posts/' . rawurlencode($post['slug'])));
            $xml .= '<item><title>' . $this->xml($post['title']) . '</title><link>' . $url . '</link><guid isPermaLink="true">'
                . $url . '</guid><description>' . $this->xml($post['summary']) . '</description><pubDate>'
                . gmdate(DATE_RSS, strtotime($post['published_at'] . ' UTC')) . '</pubDate></item>';
        }
        return $xml . '</channel></rss>';
    }
    public function sitemap(): string
    {
        header('Content-Type: application/xml; charset=UTF-8');
        $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">';
        $homes = array_map(fn ($code) => ['code' => $code], $this->i18n->enabledLocales());
        foreach ($this->i18n->enabledLocales() as $locale) {
            $xml .= $this->entry('/' . $locale . '/', '/', $homes);
            foreach ([$this->site->posts, $this->site->pages] as $repository) {
                foreach ($repository->published($locale) as $item) {
                    $suffix = ($repository === $this->site->posts ? '/posts/' : '/') . rawurlencode($item['slug']);
                    $xml .= $this->entry('/' . $locale . $suffix, $suffix, $repository->translations((int) $item['id']));
                }
            }
        }
        return $xml . '</urlset>';
    }
    private function entry(string $path, string $suffix, array $translations): string
    {
        $xml = '<url><loc>' . $this->xml(absolute_url($path)) . '</loc>';
        foreach ($translations as $translation) {
            $code = $translation['code'];
            $href = $this->xml(absolute_url('/' . $code . $suffix));
            $xml .= '<xhtml:link rel="alternate" hreflang="' . $this->xml($code) . '" href="' . $href . '"/>';
            if ($code === $this->i18n->defaultLocale()) { $xml .= '<xhtml:link rel="alternate" hreflang="x-default" href="' . $href . '"/>'; }
        }
        return $xml . '</url>';
    }
    public function css(): string
    {
        $css = $this->site->settings->get('custom_css');
        $etag = '"' . hash('sha256', $css) . '"';
        header('Content-Type: text/css; charset=UTF-8');
        header('Cache-Control: ' . ($this->admin ? 'private, no-store' : 'public, max-age=300'));
        header('ETag: ' . $etag);
        if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) { http_response_code(304); return ''; }
        return $css;
    }
    /** Site colors from Settings → Appearance. */
    public function theme(): string
    {
        $css = \App\Services\Appearance::css($this->site->settings);
        $etag = '"' . hash('sha256', $css) . '"';
        header('Content-Type: text/css; charset=UTF-8');
        header('Cache-Control: public, max-age=31536000, immutable');
        header('ETag: ' . $etag);
        if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) { http_response_code(304); return ''; }
        return $css;
    }
    /**
     * GET /themes/<slug>/<path>: a CSS, image or font file of the active theme (or, for a signed-in admin,
     * of any usable theme, for previews). Never PHP, JSON, HTML, JavaScript or dotfiles.
     */
    public function themeAsset(string $slug, string $path): string
    {
        $themes = $this->site->themes();
        $theme = $themes->find($slug);
        $allowed = $theme && $theme['problem'] === '' && ($this->admin || $themes->activeSlug() === $slug);
        $file = $allowed ? \App\Services\Themes::assetFile($theme['path'], rawurldecode($path)) : null;
        if ($file === null) {
            http_response_code(404); header('Content-Type: text/plain; charset=UTF-8'); header('Cache-Control: no-store');
            return "Not found\n";
        }
        $type = \App\Services\Themes::ASSETS[strtolower(pathinfo($file, PATHINFO_EXTENSION))];
        $hash = hash_file('sha256', $file);
        header('Content-Type: ' . $type);
        header('X-Content-Type-Options: nosniff');
        if ($type === 'image/svg+xml') { header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src data:"); }
        // A ?v= that matches the file's hash (what $theme->asset() writes) can be cached for good.
        header('Cache-Control: ' . ($this->admin ? 'private, max-age=0' : (($_GET['v'] ?? '') === $hash ? 'public, max-age=31536000, immutable' : 'public, max-age=300')));
        header('ETag: "' . $hash . '"');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', (int) filemtime($file)) . ' GMT');
        if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === '"' . $hash . '"') { http_response_code(304); return ''; }
        return (string) file_get_contents($file);
    }
    /** GET /theme-settings.css: the active (or previewed) theme's color settings as CSS variables. */
    public function themeSettings(): string
    {
        $themes = $this->site->themes();
        $css = $themes->object($themes->current($this->admin))->settingsCss();
        $etag = '"' . hash('sha256', $css) . '"';
        header('Content-Type: text/css; charset=UTF-8');
        header('Cache-Control: ' . ($this->admin ? 'private, no-store' : 'public, max-age=300'));
        header('ETag: ' . $etag);
        if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) { http_response_code(304); return ''; }
        return $css;
    }
    /** The visitor's light/dark choice, kept in a cookie. Works without JavaScript. */
    public function scheme(): string
    {
        $set = (string) ($_GET['set'] ?? '');
        $back = (string) ($_GET['back'] ?? '/');
        if (!preg_match('~^/(?!/)~', $back) || preg_match('/[\p{C}\s\\\\]/u', $back) || strlen($back) > 2048) { $back = '/'; }
        if (isset(\App\Services\Appearance::SCHEMES[$set])) {
            setcookie('meteor_theme', $set, ['expires' => time() + 31536000, 'path' => '/', 'samesite' => 'Lax', 'httponly' => true,
                'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
        }
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex, nofollow');
        header('Location: ' . $back, true, 303);
        return '';
    }
}
