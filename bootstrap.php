<?php
declare(strict_types=1);

require_once __DIR__ . '/core/autoload.php';
$config = require __DIR__ . '/config/config.php';
$GLOBALS['config'] = $config;
$db = new Core\Database($config['database']);
$secure = parse_url($config['app_url'], PHP_URL_SCHEME) === 'https'
    || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
if ($secure && PHP_SAPI !== 'cli') { header('Strict-Transport-Security: max-age=31536000'); }
$requestPath = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
$adminRequest = preg_match('~^/admin(?:/|$)~', $requestPath) === 1;
// With a secret sign-in address, /admin starts no session for strangers, so it looks like any missing page.
$loginPath = App\Services\LoginPath::current($db);
$loginPage = $loginPath !== '' && $requestPath === '/' . $loginPath;
$remember = Core\Remember::forRequest($db, $config);
if (($adminRequest && $loginPath === '') || $loginPage || isset($_COOKIE[$config['session_name']]) || $remember->present()) {
    Core\Session::start($config['session_name'], $secure);
}
if ($loginPage) { $_SESSION['login_gate'] = true; }
Core\Dates::load($db);
$i18n = new Core\I18n($db, require __DIR__ . '/lang/en.php');
$GLOBALS['i18n'] = $i18n;
$auth = new Core\Auth($db);
$user = $auth->user();
// The session expired but the browser was remembered: sign in again quietly.
if ($user === null && $remember->present() && $remember->resume()) { $user = $auth->user(); }
$isAdmin = $user !== null;
if ($adminRequest && $loginPath !== '' && !$isAdmin
    && !(in_array($requestPath, App\Services\LoginPath::PASSKEY, true) && !empty($_SESSION['login_gate']))) {
    $adminRequest = false; // served by the public router, which answers 404
}
$adminRequest = $adminRequest || $loginPage;
$router = new Core\Router($i18n->enabledLocales($isAdmin));
$view = new Core\View(__DIR__ . '/views');
return compact('config', 'db', 'i18n', 'router', 'view', 'isAdmin', 'adminRequest', 'loginPage');
