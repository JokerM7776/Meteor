<?php
declare(strict_types=1);

namespace Core;

final class Env
{
    /** No interpolation or shell evaluation; process variables take precedence. */
    public static function parse(string $text): array
    {
        $values = [];
        foreach (preg_split('/\R/', $text) as $number => $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!preg_match('/^(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', $line, $m)) {
                throw new \InvalidArgumentException('Invalid environment line ' . ($number + 1));
            }
            $value = $m[2];
            if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
                $quote = $value[0];
                if (!preg_match('/^' . $quote . '((?:\\\\.|[^' . $quote . '])*)' . $quote . '\s*(?:#.*)?$/', $value, $quoted)) {
                    throw new \InvalidArgumentException('Unclosed environment quote on line ' . ($number + 1));
                }
                $value = $quote === '"' ? strtr($quoted[1], ['\\n' => "\n", '\\r' => "\r", '\\t' => "\t", '\\"' => '"', '\\\\' => '\\']) : $quoted[1];
            } else {
                $value = rtrim(preg_replace('/\s+#.*$/', '', $value));
            }
            $values[$m[1]] = $value;
        }
        return $values;
    }

    public static function load(string $file): array
    {
        return is_file($file) ? self::parse((string) file_get_contents($file)) : [];
    }
}
