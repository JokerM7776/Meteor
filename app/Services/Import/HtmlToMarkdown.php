<?php
declare(strict_types=1);
namespace App\Services\Import;

final class HtmlToMarkdown
{
    public static function convert(string $html, array &$warnings): string
    {
        if (!class_exists(\DOMDocument::class)) { $warnings[] = 'ext-dom is missing; HTML formatting was removed.'; return self::clean(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')); }
        $previous = libxml_use_internal_errors(true);
        try {
            $dom = new \DOMDocument(); $dom->loadHTML('<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>', LIBXML_NONET);
            $text = self::children($dom->getElementsByTagName('body')->item(0), $warnings);
        } finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
        $warnings = array_values(array_unique($warnings));
        return self::clean($text);
    }
    private static function children(?\DOMNode $node, array &$warnings): string
    {
        $out = ''; if ($node) { foreach ($node->childNodes as $child) { $out .= self::node($child, $warnings, (bool) preg_match('/(?:^|\n)[ \t]*$/', $out)); } } return $out;
    }
    private static function node(\DOMNode $node, array &$warnings, bool $lineStart = true): string
    {
        if ($node instanceof \DOMText) {
            $text = preg_replace('/\s+/u', ' ', $node->textContent);
            $text = str_replace(['\\', '`', '*', '_', '[', ']', '~', '^', '=='], ['\\\\', '\\`', '\\*', '\\_', '\\[', '\\]', '\\~', '\\^', '\\=\\='], $text);
            for ($parent = $node->parentNode; $parent; $parent = $parent->parentNode) {
                if (in_array($parent->nodeName, ['td', 'th'], true)) { $text = str_replace('|', '\\|', $text); break; }
            }
            if ($lineStart) {
                $text = preg_replace('/^([ \t]*)([#>]|[-+](?= )|\d+\.(?= ))/', '$1\\\\$2', $text);
                $text = preg_replace('/^([ \t]*)\\\\(\d+)\./', '$1$2\\\\.', $text);
            }
            return $text;
        }
        if (!$node instanceof \DOMElement) { return ''; }
        $tag = strtolower($node->tagName);
        if (in_array($tag, ['script', 'style', 'noscript'], true)) { return ''; }
        if ($tag === 'pre') {
            $code = $node->getElementsByTagName('code')->item(0); $class = $node->getAttribute('class') . ' ' . ($code?->getAttribute('class') ?? '');
            preg_match('/(?:language-|lang-)([\w+-]+)/', $class, $m);
            $text = rtrim($node->textContent, "\n"); preg_match_all('/`+/', $text, $runs); $fence = str_repeat('`', max([3, ...array_map(fn ($s) => strlen($s) + 1, $runs[0])]));
            return "\n\n$fence" . ($m[1] ?? '') . "\n$text\n$fence\n\n";
        }
        if ($tag === 'code') {
            $text = $node->textContent; preg_match_all('/`+/', $text, $runs);
            $fence = str_repeat('`', max([1, ...array_map(fn ($run) => strlen($run) + 1, $runs[0])]));
            $padding = str_starts_with($text, '`') || str_ends_with($text, '`') ? ' ' : '';
            return $fence . $padding . $text . $padding . $fence;
        }
        if (in_array($tag, ['iframe', 'video', 'audio', 'embed'], true)) { $warnings[] = "$tag converted to a link."; $src = $node->getAttribute('src'); return $src === '' ? '' : '[' . $src . '](' . self::url($src) . ')'; }
        $inner = self::children($node, $warnings);
        if (preg_match('/^h([1-6])$/', $tag, $m)) { return "\n\n" . str_repeat('#', (int) $m[1]) . ' ' . trim($inner) . "\n\n"; }
        return match ($tag) {
            'p', 'div', 'section', 'center', 'figure', 'figcaption' => "\n\n" . trim($inner) . "\n\n",
            'br' => "  \n", 'strong', 'b' => '**' . trim($inner) . '**', 'em', 'i' => '*' . trim($inner) . '*',
            'del', 's', 'strike' => '~~' . trim($inner) . '~~', 'a' => in_array(trim($node->getAttribute('href')), ['', '#'], true) ? $inner : '[' . $inner . '](' . self::url($node->getAttribute('href')) . ')',
            'img' => '![' . str_replace(['[', ']'], ['\\[', '\\]'], $node->getAttribute('alt')) . '](' . self::url($node->getAttribute('src')) . ')',
            'ul', 'ol' => self::list($node, $warnings), 'li' => trim($inner),
            'blockquote' => "\n\n> " . str_replace("\n", "\n> ", trim($inner)) . "\n\n", 'hr' => "\n\n---\n\n",
            'table' => self::table($node, $warnings), 'thead', 'tbody', 'tr', 'th', 'td', 'span', 'kbd', 'sup', 'sub' => $inner,
            default => self::unknown($tag, $inner, $warnings),
        };
    }
    private static function unknown(string $tag, string $inner, array &$warnings): string { $warnings[] = "Unsupported HTML tag: $tag."; return $inner; }
    private static function url(string $url): string { return str_replace([' ', '(', ')', '<', '>'], ['%20', '%28', '%29', '%3C', '%3E'], $url); }
    private static function list(\DOMElement $node, array &$warnings): string
    {
        $out = "\n"; $n = 0;
        foreach ($node->childNodes as $li) {
            if (!$li instanceof \DOMElement || $li->tagName !== 'li') { continue; }
            $prefix = $node->tagName === 'ol' ? ++$n . '. ' : '- ';
            $out .= $prefix . str_replace("\n", "\n" . str_repeat(' ', strlen($prefix)), trim(self::children($li, $warnings))) . "\n";
        }
        return $out;
    }
    private static function table(\DOMElement $node, array &$warnings): string
    {
        $rows = [];
        foreach ($node->getElementsByTagName('tr') as $tr) {
            $cells = []; foreach ($tr->childNodes as $cell) { if ($cell instanceof \DOMElement && in_array($cell->tagName, ['td', 'th'], true)) { $cells[] = str_replace("\n", ' ', trim(self::children($cell, $warnings))); } }
            if ($cells) { $rows[] = $cells; }
        }
        if (!$rows) { return ''; } $width = max(array_map('count', $rows)); $out = "\n\n";
        foreach ($rows as $i => $cells) { $out .= '| ' . implode(' | ', array_pad($cells, $width, '')) . " |\n"; if ($i === 0) { $out .= '| ' . implode(' | ', array_fill(0, $width, '---')) . " |\n"; } }
        return $out . "\n";
    }
    /** Protect code before converting embedded HTML or template tags. */
    public static function outsideCode(string $markdown, callable $convert): string
    {
        $saved = []; $prefix = "\x1A" . bin2hex(random_bytes(8)) . ":";
        $text = preg_replace_callback('/^ {0,3}(`{3,}|~{3,})[^\n]*(?:\n|$).*?(?:^ {0,3}\1[`~]*[ \t]*(?:\n|$)|\z)|(`+)(?!`)(?:(?!\2).)*?\2(?!`)/ms', function ($m) use (&$saved, $prefix) { $key = $prefix . count($saved) . "\x1A"; $saved[$key] = $m[0]; return $key; }, $markdown);
        return strtr($convert($text), $saved);
    }
    public static function inline(string $markdown, array &$warnings): string
    {
        $definitions = []; $prefix = "\x1Adef" . bin2hex(random_bytes(8));
        $result = self::outsideCode($markdown, function ($text) use (&$warnings, &$definitions, $prefix) {
            $text = preg_replace_callback('/^ {0,3}\[[^\]\n]+\]:[^\n]*$/m', function ($m) use (&$definitions, $prefix) {
                $key = $prefix . count($definitions) . "\x1A"; $definitions[$key] = $m[0]; return $key;
            }, $text);
            $text = preg_replace_callback('~<(p|div|section|center|figure|table|ul|ol|blockquote|pre|h[1-6]|script|style|noscript)\b[^>]*>.*?</\1\s*>~is', function ($m) use (&$warnings) { return self::convert($m[0], $warnings); }, $text);
            return self::outsideCode($text, function ($text) use (&$warnings) {
                $text = preg_replace_callback('~<(a|b|strong|i|em|code|del|kbd|sup|sub|span)\b[^>]*>.*?</\1\s*>|<(?:img|br|hr|iframe|video|audio|embed)\b[^>]*>(?:</(?:iframe|video|audio|embed)>)?~is', function ($m) use (&$warnings) {
                    if (preg_match('~^<br\b~i', $m[0])) { return "  \n"; }
                    return self::convert($m[0], $warnings);
                }, $text);
                return self::outsideCode($text, function ($text) use (&$warnings) {
                    return preg_replace_callback('~</?[A-Za-z][A-Za-z0-9:-]*(?:\s[^<>]*|/?)>~', function ($m) use (&$warnings) { $warnings[] = 'Leftover HTML removed: ' . $m[0]; return ''; }, $text);
                });
            });
        });
        return strtr(self::clean($result), $definitions);
    }
    public static function clean(string $text): string
    {
        return trim(self::outsideCode($text, function ($part) {
            $part = preg_replace_callback('/[ \t]+$/m', fn ($m) => $m[0] === '  ' ? '  ' : '', $part);
            return preg_replace('/\n{3,}/', "\n\n", $part);
        }));
    }
    public static function summary(string &$body, mixed $explicit = null): string
    {
        $parts = preg_split('/<!--\s*more\s*-->/i', $body, 2); $body = preg_replace('/<!--\s*more\s*-->/i', '', $body);
        $text = is_scalar($explicit) && trim((string) $explicit) !== '' ? (string) $explicit : (count($parts) > 1 ? $parts[0] : '');
        $text = preg_replace('/{%.*?%}|<!--.*?-->/s', '', $text);
        $text = preg_replace('/!?\[([^\]]*)\]\([^)]*\)/', '$1', $text);
        $text = trim(preg_replace('/\s+/u', ' ', preg_replace('/[`*_#>~]/', '', html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'))));
        if (mb_strlen($text) <= 300) { return $text; } $text = mb_substr($text, 0, 300); return preg_replace('/\s+\S*$/u', '', $text);
    }
}
