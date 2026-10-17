<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/Env.php';
$root = dirname(__DIR__);
$env = \Core\Env::load($root . '/.env');
$get = static function (string $key, string $default = '') use ($env): string {
    $value = getenv($key);
    return $value === false ? ($env[$key] ?? $default) : $value;
};
$driver = $get('DB_DRIVER', 'mysql');
if (!in_array($driver, ['mysql', 'sqlite'], true)) {
    throw new InvalidArgumentException('DB_DRIVER must be mysql or sqlite.');
}
$path = $get('DB_PATH', 'storage/meteor.sqlite');
if ($path !== ':memory:' && !str_starts_with($path, '/')) {
    $path = $root . '/' . $path;
}
return [
    'app_url' => rtrim($get('APP_URL', 'http://localhost:8000'), '/'),
    'app_env' => $get('APP_ENV', 'production'),
    'database' => [
        'driver' => $driver, 'host' => $get('DB_HOST', '127.0.0.1'),
        'port' => $get('DB_PORT', '3306'), 'name' => $get('DB_NAME', 'meteor'),
        'user' => $get('DB_USER', 'meteor'), 'password' => $get('DB_PASS'), 'path' => $path,
    ],
    'session_name' => $get('SESSION_NAME', 'meteor_session'),
    'webauthn' => ['rp_id' => $get('WEBAUTHN_RP_ID', 'localhost'), 'rp_name' => $get('WEBAUTHN_RP_NAME', 'Meteor')],
];
