<?php
declare(strict_types=1);

// The built-in server may serve existing assets, but never another PHP file.
if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $file = is_string($path) ? realpath(__DIR__ . rawurldecode($path)) : false;
    if ($file && str_starts_with($file, __DIR__ . DIRECTORY_SEPARATOR) && is_file($file)
        && preg_match('/\.(?:css|js|svg|png|jpe?g|gif|webp|avif|ico|woff2?)$/i', $file)) {
        return false;
    }
}
$adminRequest = preg_match('~^/admin(?:/|$)~', rawurldecode((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) === 1;
require_once dirname(__DIR__) . '/core/helpers.php';
header('Content-Security-Policy: ' . security_policy($adminRequest ? "'self'" : "'none'"));
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cross-Origin-Opener-Policy: same-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
header('Content-Type: text/html; charset=UTF-8');
if ($adminRequest) {
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
}
try {
    require_once dirname(__DIR__) . '/core/autoload.php';
    $root = dirname(__DIR__);
    $requestPath = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    // Every URL is root-relative, so refuse to run under a sub-folder such as /meteor/public/index.php.
    if (preg_match('~^(.*?)(?:/public)?/index\.php$~', (string) ($_SERVER['SCRIPT_NAME'] ?? ''), $match)
        && $match[1] !== '' && str_starts_with($requestPath . '/', $match[1] . '/')) {
        [$problem, $base] = ['subfolder', $match[1]];
        require $root . '/views/setup.php';
        exit;
    }
    $installer = new Core\Installer($root);
    if (!$installer->installed() && !str_starts_with($requestPath, '/assets/')) {
        // The installer lives at / because host firewalls (e.g. Imunify360) often block /install.
        if ($requestPath !== '/') { header('Location: /', true, 302); exit; }
        header('Cache-Control: no-store');
        Core\Session::start('meteor_session', !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        echo (new App\Controllers\InstallController($root, $installer))->handle();
        exit;
    }
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
    if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') { echo compressed($body); }
} catch (Throwable $error) {
    error_log((string) $error);
    http_response_code(503);
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') { echo 'Meteor is temporarily unavailable.'; }
}
