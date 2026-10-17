<?php
declare(strict_types=1);
namespace Core;
final class Version
{
    public static function current(?string $root = null): string
    {
        $value = trim((string) file_get_contents(($root ?? dirname(__DIR__)) . '/VERSION'));
        if (!preg_match('/^\d+\.\d+\.\d+$/D', $value)) { throw new \RuntimeException('Invalid VERSION file.'); }
        return $value;
    }
}
