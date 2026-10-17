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
$adminRequest = preg_match('~^/admin(?:/|$)~', rawurldecode((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) === 1;
$scriptPolicy = $adminRequest ? "'self'" : "'none'";
header("Content-Security-Policy: default-src 'self'; script-src $scriptPolicy; style-src 'self' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' https:; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Content-Type: text/html; charset=UTF-8');
if ($adminRequest) {
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
}
try {
    require_once dirname(__DIR__) . '/core/autoload.php';
    $root = dirname(__DIR__);
    $requestPath = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    $installer = new Core\Installer($root);
    if (!$installer->installed() && !str_starts_with($requestPath, '/assets/')) {
        if ($requestPath !== '/install') { header('Location: /install', true, 302); exit; }
        header('Cache-Control: no-store');
        Core\Session::start('meteor_session', !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        echo (new App\Controllers\InstallController($root, $installer))->handle();
        exit;
    }
    if ($requestPath === '/install') { http_response_code(404); echo 'Not found'; exit; }
    if (is_file($root . '/storage/maintenance.flag') && !$adminRequest) {
        http_response_code(503); header('Retry-After: 120'); header('Cache-Control: no-store');
        if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') { echo 'Meteor is being updated. Please try again shortly.'; }
        exit;
    }
    $app = require dirname(__DIR__) . '/bootstrap.php';
    header('Cache-Control: ' . ($adminRequest ? 'no-store' : ($isAdmin ? 'private, no-store' : 'public, max-age=300')));
    if ($adminRequest) { header('X-Robots-Tag: noindex, nofollow'); }
    else { header('Vary: Cookie', false); }
    if ($adminRequest && !$isAdmin && !in_array(rawurldecode((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)), ['/admin/login', '/admin/passkey/login/options', '/admin/passkey/login/verify'], true)) {
        header('Location: /admin/login', true, 302); exit;
    }
    require dirname(__DIR__) . '/app/routes.php';
    $body = $router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
    if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') { echo $body; }
} catch (Throwable $error) {
    error_log((string) $error);
    http_response_code(503);
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') { echo 'Meteor is temporarily unavailable.'; }
}
