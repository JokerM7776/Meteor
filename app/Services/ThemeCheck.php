<?php
declare(strict_types=1);
namespace App\Services;
use App\Theme\Deprecated;
use Core\{Database, Dates, I18n, ReleasePackage, Router, View};

/** Runs theme code only against a disposable site, without loading the real site's configuration. */
final class ThemeCheck
{
    private const CAPTCHA = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
    public function check(string $source): array
    {
        $root = dirname(__DIR__, 2);
        $temp = $root . '/storage/theme-check-' . bin2hex(random_bytes(12));
        $saved = [$_GET, $_POST, $_COOKIE, $_SERVER, $_SESSION ?? [], $GLOBALS['i18n'] ?? null, $GLOBALS['config'] ?? null,
            Themes::$directory, Deprecated::$reporter, Dates::format(), Dates::hour12(), Dates::zone()->getName(), http_response_code()];
        $findings = [];
        try {
            ThemePackage::mkdir($temp . '/themes');
            $db = self::database($temp . '/site.sqlite');
            Themes::$directory = $temp . '/themes';
            $themes = new Themes($db);
            if (is_dir($source)) {
                $findings = [...ThemePackage::errors($source), ...Themes::syntaxErrors($source)];
                $json = json_decode((string) @file_get_contents($source . '/theme.json'), true);
                $slug = $json['slug'] ?? basename($source);
                if (!is_string($slug) || !preg_match(Themes::SLUG, $slug)) { return [...$findings, 'Invalid theme slug.']; }
                try {
                    foreach (ThemePackage::files($source) as $relative => $file) {
                        ThemePackage::mkdir(dirname($temp . '/themes/' . $slug . '/' . $relative));
                        if (!copy($file, $temp . '/themes/' . $slug . '/' . $relative)) { throw new \RuntimeException('Cannot copy ' . $relative); }
                    }
                } catch (\Throwable $e) { return [...$findings, $e->getMessage()]; }
                $theme = $themes->find($slug);
                if (!$theme || $theme['problem'] !== '') { return [...$findings, $theme['problem'] ?? 'theme.json is missing.']; }
            } else {
                try { $theme = $themes->installZip($source, false); $slug = $theme['slug']; }
                catch (\Throwable $e) { return [$e->getMessage()]; }
            }
            (new DemoSeeder($db))->run(true);
            foreach (['posts_per_page' => '3', 'font_source' => 'system', 'author_name' => 'Meteor Team', 'author_bio' => 'Theme check author.',
                'post_author_card' => '1', 'comments_enabled' => '1', 'comments_captcha' => '0', 'app_secret' => str_repeat('a', 64)] as $key => $value) {
                $db->upsert('settings', ['key' => $key, 'locale' => null, 'value' => $value], ['key', 'locale_scope']);
            }
            $db->query('UPDATE posts SET comments_enabled = 1');
            $db->query("INSERT INTO comments (post_id, locale, author_name, body, status) VALUES (1, 'en', 'Reader', 'A comment.', 'approved')");
            $themes->activate($slug);
            $_GET = $_POST = $_COOKIE = $_SESSION = [];
            $i18n = new I18n($db, require $root . '/lang/en.php', $root . '/lang', $temp . '/lang');
            $GLOBALS['i18n'] = $i18n; $GLOBALS['config'] = ['app_url' => 'https://meteor.example'];
            Dates::configure();
            $view = new View($root . '/views'); $router = new Router($i18n->enabledLocales()); $isAdmin = false;
            require $root . '/app/routes.php';
            Deprecated::$reporter = static function (string $message) use (&$findings): void { $findings[] = $message; };
            $paths = ['/en/', '/zh-hans/', '/en/posts', '/en/posts?page=2', '/en/categories/engineering', '/en/tags/php', '/en/tags',
                '/en/posts/building-a-zero-js-blog', '/en/about', '/en/search?q=PHP', '/en/search?q=unfindablexyz', '/zh-hans/no/such/page'];
            foreach ($paths as $path) {
                $_SERVER['REQUEST_URI'] = $path;
                parse_str((string) parse_url($path, PHP_URL_QUERY), $_GET);
                try { $html = $router->dispatch('GET', $path); $findings = [...$findings, ...self::htmlErrors($html, $theme, $path)]; }
                catch (\Throwable $e) { $findings[] = $path . ': ' . $e->getFile() . ':' . $e->getLine() . ': ' . $e->getMessage(); }
            }
            try {
                $html = $site->render('comment-check', 'en', ['captcha' => ['widget' => 'cf-turnstile', 'site_key' => 'check', 'script' => self::CAPTCHA],
                    'postUrl' => '/en/posts/building-a-zero-js-blog', 'action' => '/en/posts/building-a-zero-js-blog/comments',
                    'values' => ['name' => 'Reader', 'email' => '', 'url' => '', 'body' => 'A comment.', 'token' => 'check', 'parent_id' => 0],
                    'error' => '', 'noindex' => true, 'canonical' => null]);
                // The CAPTCHA provider's script is Meteor's own and allowed by this page's CSP; anything else is not.
                if (!str_contains($html, '<script src="' . self::CAPTCHA . '"')) { $findings[] = 'comment check: the CAPTCHA script is missing, so comments cannot be sent when CAPTCHA is on. Keep <script src="<?= e($check->script) ?>" async defer></script>.'; }
                $findings = [...$findings, ...self::htmlErrors(str_replace('<script src="' . self::CAPTCHA . '" async defer></script>', '', $html), $theme, 'comment check')];
            } catch (\Throwable $e) { $findings[] = $e->getFile() . ':' . $e->getLine() . ': ' . $e->getMessage(); }
            return array_values(array_unique($findings));
        } catch (\Throwable $e) { return [...$findings, $e->getMessage()]; }
        finally {
            [$_GET, $_POST, $_COOKIE, $_SERVER, $_SESSION, $GLOBALS['i18n'], $GLOBALS['config'], Themes::$directory, Deprecated::$reporter, $format, $hour12, $zone, $status] = $saved;
            Dates::configure($format, $hour12 ? '12' : '24', $zone);
            if ($status !== false) { http_response_code($status); }
            unset($site, $router, $themes, $db);
            ReleasePackage::removeTree($temp);
        }
    }

    public static function database(string $path = ':memory:'): Database
    {
        $db = new Database(['driver' => 'sqlite', 'path' => $path]);
        $sql = preg_replace('/^\s*--.*$/m', '', (string) file_get_contents(dirname(__DIR__, 2) . '/database/schema.sqlite.sql'));
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) { $db->query($statement); }
        return $db;
    }

    public static function htmlErrors(string $html, array $theme, string $page): array
    {
        $errors = [];
        foreach (['~<script~i' => 'script element', '~\bstyle\s*=~i' => 'inline style attribute', '~<style~i' => 'style element',
            '~<[a-z][^>]*\s+on[a-z]+\s*=~i' => 'event attribute'] as $pattern => $label) {
            if (preg_match($pattern, $html)) { $errors[] = $page . ': forbidden ' . $label . '.'; }
        }
        preg_match_all('~/themes/' . preg_quote($theme['slug'], '~') . '/([^\s"\'<>?#]+)~i', html_entity_decode($html, ENT_QUOTES, 'UTF-8'), $matches);
        foreach (array_unique($matches[1]) as $path) {
            if (Themes::assetFile($theme['path'], rawurldecode($path)) === null) { $errors[] = $page . ': missing theme asset ' . $path; }
        }
        return $errors;
    }
}
