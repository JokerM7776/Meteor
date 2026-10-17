<?php
declare(strict_types=1);
namespace Core;

final class Router
{
    private array $routes = [];

    /** Pass I18n::enabledLocales($isAdmin); each entry is a locale code. */
    public function __construct(private array $enabledLocales = []) {}

    /** Patterns are anchored regexes with named captures, e.g. ~^/posts/(?P<slug>[^/]+)$~u. */
    public function add(string $method, string $pattern, callable $handler, bool $localized = false): self
    {
        $this->routes[] = [strtoupper($method), $pattern, $handler, $localized];
        return $this;
    }

    /** Resolves without sending headers, allowing controllers and tests to inspect errors. */
    public function match(string $method, string $uri): array
    {
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path) || !str_starts_with($path, '/') || preg_match('/%(?:2f|5c|00)/i', $path)) {
            return ['status' => 404];
        }
        $path = rawurldecode($path);
        $locale = null;
        $localizedPath = $path;
        if (preg_match('~^/([^/]+)(/.*)?$~u', $path, $prefix) && in_array($prefix[1], $this->enabledLocales, true)) {
            $locale = $prefix[1];
            $localizedPath = $prefix[2] ?? '/';
        }
        $allowed = [];
        foreach ($this->routes as [$verb, $pattern, $handler, $localized]) {
            if ($localized && $locale === null) {
                continue;
            }
            if (!preg_match($pattern, $localized ? $localizedPath : $path, $captures)) {
                continue;
            }
            $allowed[] = $verb;
            if ($verb === 'GET') {
                $allowed[] = 'HEAD';
            }
            if ($verb !== strtoupper($method) && !(strtoupper($method) === 'HEAD' && $verb === 'GET')) {
                continue;
            }
            return ['status' => 200, 'handler' => $handler, 'locale' => $localized ? $locale : null,
                'params' => array_filter($captures, 'is_string', ARRAY_FILTER_USE_KEY)];
        }
        return $allowed ? ['status' => 405, 'allow' => array_values(array_unique($allowed))] : ['status' => 404];
    }

    public function dispatch(string $method, string $uri): mixed
    {
        $route = $this->match($method, $uri);
        if ($route['status'] !== 200) {
            http_response_code($route['status']);
            if (isset($route['allow'])) {
                header('Allow: ' . implode(', ', $route['allow']));
            }
            return $route['status'] === 404 ? 'Not found' : 'Method not allowed';
        }
        return ($route['handler'])($route['params'], $route['locale']);
    }
}
