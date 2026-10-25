<?php
declare(strict_types=1);
namespace App\Controllers;
use Core\{Csrf, Installer, View};
final class InstallController
{
    public function __construct(private string $root, private Installer $installer) {}
    public function handle(): string
    {
        $error = ''; $message = ''; $values = $_POST;
        $scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        // Fields are prefixed db_: some host firewalls reject any request with both "host" and "port".
        $values += ['db_driver' => 'mysql', 'db_host' => '127.0.0.1', 'db_port' => '3306', 'db_name' => 'meteor', 'db_user' => 'meteor', 'db_path' => 'storage/meteor.sqlite',
            'url' => $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), 'title' => 'Meteor Blog', 'username' => ''];
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            try {
                if ($this->installer->installFromEnv($values['url'])) {
                    $_SESSION['flash'] = 'Meteor installed from .env. Sign in with your new admin account.';
                    header('Location: /admin/login', true, 303); return '';
                }
            } catch (\Throwable $e) { $error = 'Install from .env failed: ' . $e->getMessage(); }
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::verify($_POST['csrf_token'] ?? null)) { http_response_code(403); $error = 'Invalid CSRF token. Reload the form.'; }
            else {
                try {
                    $path = (string) $values['db_path'];
                    if ($path === ':memory:') { throw new \InvalidArgumentException('Use a persistent SQLite file.'); }
                    if (!str_starts_with($path, '/')) { $path = $this->root . '/' . $path; }
                    if (str_starts_with((realpath(dirname($path)) ?: dirname($path)) . '/', $this->root . '/public/')) { throw new \InvalidArgumentException('Keep the SQLite database outside the public directory.'); }
                    $db = ['driver' => $values['db_driver'], 'host' => $values['db_host'], 'port' => $values['db_port'], 'name' => $values['db_name'], 'user' => $values['db_user'], 'password' => $_POST['db_password'] ?? '', 'path' => $path];
                    if (($_POST['action'] ?? '') === 'test') { $this->installer->connect($db); $message = 'Connection successful. Re-enter the database password before installing.'; }
                    else {
                        if (($_POST['password'] ?? '') !== ($_POST['confirm'] ?? '')) { throw new \InvalidArgumentException('Admin passwords must match.'); }
                        $this->installer->install($db, $values['url'], $values['title'], $values['username'], $_POST['password'] ?? '');
                        $_SESSION['flash'] = 'Meteor installed. Sign in with your new admin account.';
                        header('Location: /admin/login', true, 303); return '';
                    }
                } catch (\Throwable $e) { $error = $e->getMessage(); }
            }
        }
        unset($values['db_password'], $values['password'], $values['confirm']);
        return (new View($this->root . '/views'))->render('install', ['values' => $values, 'checks' => $this->installer->requirements(), 'error' => $error, 'message' => $message]);
    }
}
