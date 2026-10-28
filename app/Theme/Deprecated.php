<?php
declare(strict_types=1);
namespace App\Theme;

/** Future __get implementations call read(); API v1 currently defines no aliases. */
final class Deprecated
{
    private static array $logged = [];
    /** Tests point this at a temporary file; normally storage/logs/themes.log. */
    public static ?string $logFile = null;
    public static function read(object $object, string $name, array $aliases): mixed
    {
        if (!isset($aliases[$name])) { throw new \LogicException('Unknown theme property: ' . $name); }
        $key = get_class($object) . '::' . $name;
        if (!isset(self::$logged[$key])) {
            self::$logged[$key] = true;
            self::log('Deprecated ' . $key . '; use ' . $aliases[$name]);
        }
        return $object->{$aliases[$name]};
    }
    public static function log(string $message): void
    {
        $file = self::$logFile ?? dirname(__DIR__, 2) . '/storage/logs/themes.log';
        if (!is_dir(dirname($file))) { @mkdir(dirname($file), 0700, true); }
        @file_put_contents($file, gmdate('Y-m-d H:i:s') . ' ' . str_replace(["\r", "\n"], ' ', $message) . "\n", FILE_APPEND | LOCK_EX);
    }
}
