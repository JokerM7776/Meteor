<?php
declare(strict_types=1);

// The built-in server may serve existing assets, but never another PHP file.
if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $file = is_string($path) ? realpath(__DIR__ . rawurldecode($path)) : false;
    if ($file && str_starts_with($file, __DIR__ . DIRECTORY_SEPARATOR) && is_file($file)
        && preg_match('/\.(?:css|png|jpe?g|gif|webp|ico|woff2?)$/i', $file)) {
        return false;
    }
}
header("Content-Security-Policy: default-src 'self'; script-src 'none'; style-src 'self' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' https:; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Content-Type: text/html; charset=UTF-8');
try {
    $app = require dirname(__DIR__) . '/bootstrap.php';
    require dirname(__DIR__) . '/app/routes.php';
    $body = $router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
    if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') { echo $body; }
} catch (Throwable $error) {
    error_log((string) $error);
    http_response_code(503);
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') { echo 'Meteor is temporarily unavailable.'; }
}
