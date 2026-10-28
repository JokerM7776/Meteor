<?php
declare(strict_types=1);
namespace App\Services\Import;

final class HexoTags
{
    public static function convert(string $text, string $asset, array &$warnings): string
    {
        return HtmlToMarkdown::outsideCode($text, function ($text) use ($asset, &$warnings) {
            $text = preg_replace_callback('~{%\s*(codeblock|code)\b(.*?)%}(.*?){%\s*end\1\s*%}~s', function ($m) {
                preg_match('/lang:([\w+-]+)/', $m[2], $lang); return "\n```" . ($lang[1] ?? '') . "\n" . trim($m[3], "\r\n") . "\n```\n";
            }, $text);
            return HtmlToMarkdown::outsideCode($text, function ($text) use ($asset, &$warnings) {
                $text = preg_replace_callback('~{%\s*(\w+)\b(.*?)%}(.*?){%\s*end\1\s*%}~s', function ($m) use (&$warnings) {
                    if (in_array($m[1], ['blockquote', 'quote'], true)) { return "\n> " . str_replace("\n", "\n> ", trim($m[3])) . (trim($m[2]) !== '' ? "\n> — " . trim($m[2]) : '') . "\n"; }
                    if ($m[1] !== 'raw') { $warnings[] = 'Hexo tag converted to text: ' . $m[1]; } return $m[3];
                }, $text);
                $text = preg_replace_callback('~{%\s*(\w+)\s*(.*?)\s*%}~s', function ($m) use ($asset, &$warnings) {
                    preg_match_all('/"[^"]*"|\x27[^\x27]*\x27|\S+/', $m[2], $tokens); $args = array_map(fn ($v) => trim($v, "\"'"), $tokens[0]);
                    $first = $args[0] ?? ''; $label = implode(' ', array_slice($args, 1)) ?: $first;
                    switch ($m[1]) {
                        case 'asset_img': return '![' . $label . '](' . $asset . '/' . rawurlencode($first) . ')';
                        case 'asset_link': return '[' . $label . '](' . $asset . '/' . rawurlencode($first) . ')';
                        case 'asset_path': return $asset . '/' . rawurlencode($first);
                        case 'img':
                            $i = 0; foreach ($args as $n => $arg) { if (preg_match('~^(https?://|/|\./)|\.(png|jpe?g|gif|webp|avif)(\?|$)~i', $arg)) { $i = $n; break; } }
                            $url = $args[$i] ?? ''; $rest = array_filter(array_slice($args, $i + 1), fn ($v) => !is_numeric($v)); return '![' . implode(' ', $rest) . '](' . $url . ')';
                        case 'link':
                            foreach ($args as $i => $arg) { if (preg_match('~^(https?://|/|#|mailto:)~i', $arg)) { return '[' . implode(' ', array_slice($args, 0, $i)) . '](' . $arg . ')'; } }
                            $warnings[] = 'Hexo link has no URL.'; return implode(' ', $args);
                        case 'youtube': $url = 'https://www.youtube.com/watch?v=' . rawurlencode($first); break;
                        case 'vimeo': $url = 'https://vimeo.com/' . rawurlencode($first); break;
                        case 'iframe': $url = $first; break;
                        default: $warnings[] = 'Unsupported Hexo tag: ' . $m[1]; return '';
                    }
                    return '[' . $url . '](' . $url . ')';
                }, $text);
                if (preg_match('/{{.*?}}/s', $text)) { $warnings[] = 'Unresolved Hexo {{ expression }} kept as text.'; }
                return $text;
            });
        });
    }
}
