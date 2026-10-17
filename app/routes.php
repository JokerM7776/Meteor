<?php
declare(strict_types=1);

use App\Controllers\Public\{SiteController, FeedsController};

$site = new SiteController($db, $i18n, $view, $isAdmin);
$feeds = new FeedsController($site, $i18n);
$router->onNotFound($site->notFound(...));
$router->add('GET', '~^/$~', static function () use ($site, $i18n): string {
    $locale = $site->settings->get('match_browser_language', null, '0') === '1'
        ? $i18n->negotiate($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '') : $i18n->defaultLocale();
    header('Vary: Accept-Language');
    header('Location: /' . rawurlencode($locale) . '/', true, 302);
    return '';
});
$router->add('GET', '~^/sitemap\.xml$~', fn () => $feeds->sitemap());
$router->add('GET', '~^/custom\.css$~', fn () => $feeds->css());
$router->add('GET', '~^/$~', fn ($p, $l) => $site->home($l), true);
$router->add('GET', '~^/rss\.xml$~', fn ($p, $l) => $feeds->rss($l), true);
$router->add('GET', '~^/(?P<kind>posts|tags)$~', fn ($p, $l) => $site->archive($l, $p['kind']), true);
$router->add('GET', '~^/(?P<kind>categories|tags)/(?P<slug>[^/]+)$~u', fn ($p, $l) => $site->archive($l, $p['kind'], $p['slug']), true);
$router->add('GET', '~^/posts/(?P<slug>[^/]+)$~u', fn ($p, $l) => $site->item($l, $p['slug']), true);
$router->add('GET', '~^/(?P<slug>[^/]+)$~u', fn ($p, $l) => $site->item($l, $p['slug'], true), true);
