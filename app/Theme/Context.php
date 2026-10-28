<?php
declare(strict_types=1);
namespace App\Theme;

/** Request-local render scope; restored even when an overridden template throws. */
final class Context
{
    private static array $stack = [];
    public static function render(\Core\View $view, array $data, callable $render): string
    {
        self::$stack[] = [$view, $data];
        try { return $render(); } finally { array_pop(self::$stack); }
    }
    public static function partial(string $name, array $data = [], bool $slot = false): string
    {
        [$view, $scope] = self::$stack[array_key_last(self::$stack)] ?? throw new \LogicException('No public theme render is active.');
        return $view->partial(($slot ? 'slots/' : 'partials/') . $name, $scope + $data);
    }
}
