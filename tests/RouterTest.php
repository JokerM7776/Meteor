<?php
test('Router: locale prefixes, named params, query strings and decoding', function () {
    $router = new Core\Router(['en', 'fr-CA']);
    $router->add('GET', '~^/posts/(?P<slug>[^/]+)$~u', fn ($params, $locale) => $locale . ':' . $params['slug'], true);
    $route = $router->match('GET', '/fr-CA/posts/%E4%B8%AD%E6%96%87?page=2');
    same(200, $route['status']); same('fr-CA', $route['locale']); same(['slug' => '中文'], $route['params']);
    same('en:test', $router->dispatch('GET', '/en/posts/test'));
    same(200, $router->match('HEAD', '/en/posts/test')['status']);
    foreach (['/off/posts/test', '/posts/test', '/en/posts/a%2fb', '/en/posts/a%00b', '/en/posts/test/extra'] as $uri) {
        same(404, $router->match('GET', $uri)['status']);
    }
});
test('Router: method mismatch and nonlocalized routes', function () {
    $router = new Core\Router(['en']);
    $router->add('GET', '~^/$~', fn () => 'home', true);
    $router->add('GET', '~^/admin/login$~', fn () => 'login');
    $router->add('POST', '~^/admin/login$~', fn () => 'submit');
    same('en', $router->match('GET', '/en')['locale']);
    same(404, $router->match('GET', '/en/admin/login')['status']);
    same(null, $router->match('GET', '/admin/login')['locale']);
    same(['GET', 'HEAD', 'POST'], $router->match('DELETE', '/admin/login')['allow']);
    same(405, $router->match('POST', '/en/')['status']);
    same(404, $router->match('GET', '/unknown')['status']);
});
