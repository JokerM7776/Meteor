<?php
declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'Core\\')) {
        $name = substr($class, 5);
        if (preg_match('/^[A-Za-z][A-Za-z0-9]*$/D', $name)) {
            $file = __DIR__ . '/' . $name . '.php';
            if (is_file($file)) { require_once $file; }
        }
    }
});
require_once __DIR__ . '/helpers.php';

spl_autoload_register(static function (string $class): void {
    if (preg_match('/^App\\\\[A-Za-z0-9\\\\]+$/D', $class)) {
        $file = dirname(__DIR__) . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) { require_once $file; }
    }
});
