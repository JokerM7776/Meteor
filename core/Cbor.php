<?php
declare(strict_types=1);
namespace Core;

/** Bounded decoder for definite-length WebAuthn CBOR. */
final class Cbor
{
    public static function decode(string $data): mixed
    {
        $offset = 0;
        $value = self::read($data, $offset);
        if ($offset !== strlen($data)) { throw new \InvalidArgumentException('Trailing CBOR data.'); }
        return $value;
    }

    public static function read(string $data, int &$offset, int $depth = 0): mixed
    {
        if ($depth > 32) { throw new \InvalidArgumentException('CBOR nesting too deep.'); }
        $head = ord(self::take($data, $offset, 1));
        $major = $head >> 5; $info = $head & 31;
        if ($info >= 28) { throw new \InvalidArgumentException('Unsupported CBOR length.'); }
        $n = $info < 24 ? $info : match ($info) {
            24 => ord(self::take($data, $offset, 1)),
            25 => unpack('n', self::take($data, $offset, 2))[1],
            26 => unpack('N', self::take($data, $offset, 4))[1],
            27 => unpack('J', self::take($data, $offset, 8))[1],
        };
        if ($n < 0 && $major !== 7) { throw new \InvalidArgumentException('CBOR integer overflow.'); }
        if ($major === 0) { return $n; }
        if ($major === 1) { return -1 - $n; }
        if ($major === 2 || $major === 3) {
            $text = self::take($data, $offset, $n);
            if ($major === 3 && !mb_check_encoding($text, 'UTF-8')) { throw new \InvalidArgumentException('Invalid CBOR text.'); }
            return $text;
        }
        if ($major === 4 || $major === 5) {
            if ($n > 10000) { throw new \InvalidArgumentException('CBOR collection too large.'); }
            $result = [];
            for ($i = 0; $i < $n; $i++) {
                $key = $major === 5 ? self::read($data, $offset, $depth + 1) : $i;
                if (!is_int($key) && !is_string($key)) { throw new \InvalidArgumentException('Invalid CBOR map key.'); }
                if (array_key_exists($key, $result)) { throw new \InvalidArgumentException('Duplicate CBOR key.'); }
                $result[$key] = self::read($data, $offset, $depth + 1);
            }
            return $result;
        }
        if ($major === 6) { return self::read($data, $offset, $depth + 1); }
        if ($major === 7) {
            return match ($info) { 20 => false, 21 => true, 22, 23 => null,
                25 => self::half($n),
                26 => unpack('G', pack('N', $n))[1], 27 => unpack('E', pack('J', $n))[1],
                default => $n };
        }
        throw new \InvalidArgumentException('Unsupported CBOR.');
    }

    private static function half(int $bits): float
    {
        $sign = ($bits & 32768) ? -1 : 1;
        $exponent = ($bits >> 10) & 31; $fraction = $bits & 1023;
        if ($exponent === 31) { return $fraction ? NAN : $sign * INF; }
        return $sign * ($exponent ? (1 + $fraction / 1024) * 2 ** ($exponent - 15) : $fraction * 2 ** -24);
    }

    private static function take(string $data, int &$offset, int $length): string
    {
        if ($length > strlen($data) - $offset) { throw new \InvalidArgumentException('Truncated CBOR.'); }
        $value = substr($data, $offset, $length); $offset += $length;
        return $value;
    }
}
