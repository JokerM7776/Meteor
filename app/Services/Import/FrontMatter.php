<?php
declare(strict_types=1);
namespace App\Services\Import;

final class FrontMatter
{
    public static function parse(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", str_starts_with($text, "\xEF\xBB\xBF") ? substr($text, 3) : $text); $warnings = []; $data = [];
        if (preg_match('/^;;;\s*\n(.*?)\n;;;[^\n]*(?:\n|$)/s', $text, $m)) {
            $data = json_decode($m[1], true);
            if (!is_array($data)) { $warnings[] = 'Invalid JSON front matter.'; $data = []; }
            return [$data, substr($text, strlen($m[0])), $warnings];
        }
        if (preg_match('/^(?:---[ \t]*\n|[\w-]+:)/u', $text) && preg_match('/^(?:---[^\n]*\n)?(.*?)\n---[^\n]*(?:\n|$)/s', $text, $m)) {
            [$data, $warnings] = self::yaml($m[1]); $text = substr($text, strlen($m[0]));
        }
        return [$data, $text, $warnings];
    }
    public static function yaml(string $text): array
    {
        $lines = explode("\n", $text); $data = []; $warnings = [];
        for ($i = 0; $i < count($lines); $i++) {
            $line = self::comment($lines[$i]);
            if (trim($line) === '') { continue; }
            if (!preg_match('/^([\w-]+):\s*(.*)$/u', $line, $m)) { $warnings[] = 'Unsupported front matter: ' . trim($line); continue; }
            $key = $m[1]; $value = trim($m[2]);
            if (in_array($value, ['|', '>', '|-', '>-'], true)) {
                $block = [];
                while (isset($lines[$i + 1]) && (preg_match('/^\s+/', $lines[$i + 1]) || $lines[$i + 1] === '')) { $block[] = $lines[++$i]; }
                $indent = null; foreach ($block as $b) { if (trim($b) !== '') { $indent = min($indent ?? PHP_INT_MAX, strlen($b) - strlen(ltrim($b))); } }
                $block = array_map(fn ($b) => substr($b, $indent ?? 0), $block);
                $data[$key] = rtrim(implode($value[0] === '>' ? ' ' : "\n", $block)); continue;
            }
            if ($value === '' && isset($lines[$i + 1]) && preg_match('/^[ \t]+[\w-]+:/u', $lines[$i + 1])) {
                $block = [];
                while (isset($lines[$i + 1]) && (preg_match('/^[ \t]+/', $lines[$i + 1]) || trim($lines[$i + 1]) === '')) { $block[] = $lines[++$i]; }
                $indent = strlen($block[0]) - strlen(ltrim($block[0]));
                [$data[$key], $nestedWarnings] = self::yaml(implode("\n", array_map(fn ($line) => substr($line, $indent), $block)));
                $warnings = array_merge($warnings, $nestedWarnings); continue;
            }
            if ($value === '') {
                $list = []; $nested = null;
                while (isset($lines[$i + 1]) && preg_match('/^(\s*)-\s*(.*)$/', self::comment($lines[$i + 1]), $l)) {
                    $i++; $indent = strlen($l[1]);
                    if ($nested !== null && $indent > $nested) { $list[count($list) - 1][] = self::scalar($l[2], $warnings); }
                    else { $nested = trim($l[2]) === '' ? $indent : null; $list[] = $nested !== null ? [] : self::scalar($l[2], $warnings); }
                }
                $data[$key] = $list; continue;
            }
            $data[$key] = self::scalar($value, $warnings);
        }
        return [$data, array_values(array_unique($warnings))];
    }
    private static function comment(string $line): string
    {
        $quote = ''; $out = '';
        for ($i = 0; $i < strlen($line); $i++) {
            $c = $line[$i];
            if ($c === '\\' && $quote === '"' && isset($line[$i + 1])) { $out .= $c . $line[++$i]; continue; }
            if ($quote === '' && $c === '#' && ($i === 0 || ctype_space($line[$i - 1]))) { break; }
            if ($c === '"' || $c === "'") { if ($quote === '') { $quote = $c; } elseif ($quote === $c) { $quote = ''; } }
            $out .= $c;
        }
        return rtrim($out);
    }
    private static function scalar(string $s, array &$warnings): mixed
    {
        $s = trim($s);
        if (str_starts_with($s, '[') && str_ends_with($s, ']')) {
            $parts = []; $part = ''; $quote = ''; $depth = 0;
            foreach (str_split(substr($s, 1, -1)) as $c) {
                if (($c === '"' || $c === "'") && !str_ends_with($part, '\\')) { $quote = $quote === $c ? '' : ($quote ?: $c); }
                if ($quote === '') { if ($c === '[') { $depth++; } if ($c === ']') { $depth--; } }
                if ($c === ',' && $quote === '' && $depth === 0) { $parts[] = self::scalar($part, $warnings); $part = ''; } else { $part .= $c; }
            }
            if (trim($part) !== '') { $parts[] = self::scalar($part, $warnings); } return $parts;
        }
        if (str_starts_with($s, "'") && str_ends_with($s, "'")) { return str_replace("''", "'", substr($s, 1, -1)); }
        if (str_starts_with($s, '"') && str_ends_with($s, '"')) { return json_decode($s, true) ?? stripcslashes(substr($s, 1, -1)); }
        if (in_array(strtolower($s), ['true', 'false'], true)) { return strtolower($s) === 'true'; }
        if (is_numeric($s)) { return $s + 0; }
        if (preg_match('/^[{&*!]|[\[\]]/', $s)) { $warnings[] = 'Unsupported YAML value: ' . $s; }
        return $s;
    }
}
