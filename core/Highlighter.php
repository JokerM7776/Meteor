<?php
declare(strict_types=1);
namespace Core;

/** A small lexer, not a language parser. Matching escaped tokens never reparses generated spans. */
final class Highlighter
{
    public static function highlight(string $code, string $language): string
    {
        $escaped = \e($code);
        if (!in_array($language, ['php', 'js', 'css', 'html', 'sql', 'bash'], true)) { return $escaped; }
        $comments = match ($language) {
            'html' => '&lt;!--[\s\S]*?--&gt;',
            'sql' => '--[^\n]*|/\*[\s\S]*?\*/',
            'bash' => '\#[^\n]*',
            'css' => '/\*[\s\S]*?\*/',
            default => '//[^\n]*|/\*[\s\S]*?\*/' . ($language === 'php' ? '|\#[^\n]*' : ''),
        };
        $strings = <<<'REGEX'
(?:&quot;(?:\\(?:&quot;|[\s\S])|(?!&quot;)[^\\])*&quot;|&#039;(?:\\(?:&#039;|[\s\S])|(?!&#039;)[^\\])*&#039;|`(?:\\[\s\S]|[^`\\])*`)
REGEX;
        $keywords = match ($language) {
            'php' => 'if|else|elseif|foreach|as|while|for|return|function|fn|class|public|private|protected|static|new|use|namespace|echo|true|false|null|throw|try|catch|declare',
            'js' => 'if|else|for|while|return|function|const|let|var|class|new|true|false|null|undefined|async|await|export|import|from|throw|try|catch',
            'sql' => 'select|from|where|join|left|on|insert|into|values|update|set|delete|create|table|primary|key|order|by|desc|asc|and|or|null|not|as|limit',
            'bash' => 'if|then|else|fi|for|in|do|done|case|esac|echo|export|function|return',
            'css' => 'color|background|display|grid|flex|padding|margin|font|border|media|import|apply',
            'html' => 'html|head|body|main|article|section|div|span|p|a|img|link|meta|title|nav|header|footer|details|summary',
        };
        $pattern = '~(?<comment>' . str_replace('~', '\\~', $comments) . ')|(?<string>' . $strings
            . ')|(?<variable>\$[a-zA-Z_][a-zA-Z0-9_]*|--[a-zA-Z][a-zA-Z0-9-]*)|(?<keyword>\b(?:' . $keywords . ')\b)~i';
        return preg_replace_callback($pattern, static function (array $match): string {
            foreach (['comment', 'string', 'variable', 'keyword'] as $type) {
                if (isset($match[$type]) && $match[$type] !== '') { return '<span class="syntax-' . $type . '">' . $match[0] . '</span>'; }
            }
            return $match[0];
        }, $escaped) ?? $escaped;
    }
}
