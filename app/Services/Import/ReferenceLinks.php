<?php
declare(strict_types=1);
namespace App\Services\Import;

/** Resolve source-editor reference links; Meteor only supports inline links and footnotes. */
final class ReferenceLinks
{
    public static function convert(string $markdown): string
    {
        return HtmlToMarkdown::outsideCode($markdown, function (string $text): string {
            $definitions = []; $lines = []; $used = [];
            $prefix = "\x1Aref" . bin2hex(random_bytes(8));
            $text = preg_replace_callback('~^ {0,3}\[([^\]\n]+)\]:[ \t]*<?([^\s<>]+?)>?(?:[ \t]+("[^"\n]*"|\x27[^\x27\n]*\x27|\([^\n]*\)))?[ \t]*$~m', function ($m) use (&$definitions, &$lines, $prefix) {
                $label = self::label($m[1]);
                if (str_starts_with($label, '^')) { return $m[0]; }
                $key = $prefix . count($lines) . "\x1A";
                $lines[$key] = [$label, $m[0]];
                $title = isset($m[3]) ? ' "' . str_replace('"', '&quot;', substr($m[3], 1, -1)) . '"' : '';
                $definitions[$label] ??= '(' . str_replace(['(', ')'], ['%28', '%29'], $m[2]) . $title . ')';
                return $key;
            }, $text);
            $text = preg_replace_callback('~(?<!\\\\)(!?)\[([^\]\n]+)\](?:\[([^\]\n]*)\]|(\([^\n]*?\))|(?!(?:\(|:|\[)))~', function ($m) use ($definitions, &$used) {
                if (!empty($m[4])) { return $m[0]; }
                $label = self::label(isset($m[3]) && $m[3] !== '' ? $m[3] : $m[2]);
                if (str_starts_with($label, '^') || !isset($definitions[$label])) { return $m[0]; }
                $used[$label] = true;
                return $m[1] . '[' . $m[2] . ']' . $definitions[$label];
            }, $text);
            foreach ($lines as $key => [$label, $line]) { $text = str_replace($key, isset($used[$label]) ? '' : $line, $text); }
            return $text;
        });
    }
    private static function label(string $label): string { return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $label))); }
}
