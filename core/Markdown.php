<?php
declare(strict_types=1);
namespace Core;

/** Deliberately small Markdown subset; all HTML originates here, never from the input. */
final class Markdown
{
    private array $ids = [];
    private array $headings = [];

    public function compile(string $markdown): array
    {
        $this->ids = $this->headings = [];
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $markdown));
        $html = $this->blocks($lines);
        $text = html_entity_decode(preg_replace('/<[^>]*>/', ' ', $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $cjk = preg_match_all('/[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}]/u', $text);
        $latin = preg_replace('/[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}]/u', ' ', $text);
        $words = preg_match_all('/[\p{L}\p{N}]+(?:[\x{2019}\x{0027}-][\p{L}\p{N}]+)*/u', $latin);
        return ['html' => $html, 'toc_html' => $this->toc(),
            'reading_minutes' => max(1, (int) ceil($words / 220 + $cjk / 400))];
    }

    private function blocks(array $lines): string
    {
        $html = '';
        for ($i = 0, $count = count($lines); $i < $count;) {
            $line = $lines[$i++];
            if (trim($line) === '') { continue; }
            if (preg_match('/^\s*(`{3,}|~{3,})([a-zA-Z0-9_-]*)\s*$/', $line, $m)) {
                $code = [];
                $closing = '/^\s*' . preg_quote($m[1][0], '/') . '{' . strlen($m[1]) . ',}\s*$/';
                while ($i < $count && !preg_match($closing, $lines[$i])) { $code[] = $lines[$i++]; }
                if ($i < $count) { $i++; }
                $class = $m[2] === '' ? '' : ' class="language-' . \e(strtolower($m[2])) . '"';
                $html .= '<pre><code' . $class . '>' . Highlighter::highlight(implode("\n", $code), strtolower($m[2])) . "</code></pre>\n";
            } elseif (preg_match('/^ {0,3}(#{1,6})\s+(.+?)\s*#*\s*$/u', $line, $m)) {
                $level = strlen($m[1]);
                $inline = $this->inline($m[2]);
                $title = html_entity_decode(strip_tags($inline), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $base = \slugify($title);
                $id = $base;
                $suffix = 1;
                while (isset($this->ids[$id])) { $id = $base . '-' . ++$suffix; }
                $this->ids[$id] = true;
                if ($level === 2 || $level === 3) { $this->headings[] = [$level, $id, $title]; }
                $html .= '<h' . $level . ' id="' . \e($id) . '">' . $inline . '</h' . $level . ">\n";
            } elseif (preg_match('/^ {0,3}(?:(?:\*\s*){3,}|(?:-\s*){3,}|(?:_\s*){3,})$/', $line)) {
                $html .= "<hr>\n";
            } elseif (preg_match('/^\s*>\s?(.*)$/', $line, $m)) {
                $quote = [$m[1]];
                while ($i < $count && preg_match('/^\s*>\s?(.*)$/', $lines[$i], $m)) { $quote[] = $m[1]; $i++; }
                $html .= '<blockquote>' . $this->blocks($quote) . "</blockquote>\n";
            } elseif ($this->listItem($line) !== null) {
                $i--;
                $html .= $this->listing($lines, $i, $this->listItem($line)[0], 0);
            } else {
                $paragraph = [$line];
                while ($i < $count && trim($lines[$i]) !== '' && !$this->startsBlock($lines[$i])) { $paragraph[] = $lines[$i++]; }
                $html .= '<p>' . $this->inline(implode("\n", $paragraph)) . "</p>\n";
            }
        }
        return $html;
    }

    private function startsBlock(string $line): bool
    {
        return $this->listItem($line) !== null || (bool) preg_match('/^\s*(?:#{1,6}\s|>|`{3,}|~{3,}|(?:\*\s*){3,}$|(?:-\s*){3,}$|(?:_\s*){3,}$)/', $line);
    }

    private function listItem(string $line): ?array
    {
        if (!preg_match('/^( *)([-+*]|\d+\.)\s+(.+)$/', str_replace("\t", '    ', $line), $m)) { return null; }
        return [strlen($m[1]), ctype_digit($m[2][0]) ? 'ol' : 'ul', $m[3]];
    }

    private function listing(array $lines, int &$i, int $indent, int $depth): string
    {
        $type = $this->listItem($lines[$i])[1];
        $html = '<' . $type . '>';
        while ($i < count($lines) && ($item = $this->listItem($lines[$i])) !== null && $item[0] === $indent && $item[1] === $type) {
            $html .= '<li>' . $this->inline($item[2]);
            $i++;
            while ($i < count($lines) && ($child = $this->listItem($lines[$i])) !== null && $child[0] > $indent) {
                if ($depth === 0) { $html .= $this->listing($lines, $i, $child[0], 1); }
                else { $html .= ' ' . $this->inline($child[2]); $i++; }
            }
            $html .= '</li>';
        }
        return $html . '</' . $type . ">\n";
    }

    private function safeUrl(string $url, bool $image): bool
    {
        $url = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($url === '' || preg_match('/[\x00-\x20\x7f\\\\]/', $url) || str_starts_with($url, '//')) { return false; }
        $scheme = parse_url($url, PHP_URL_SCHEME);
        return $scheme === null || (is_string($scheme) && in_array(strtolower($scheme), $image ? ['http', 'https'] : ['http', 'https', 'mailto'], true));
    }

    private function inline(string $text): string
    {
        // A scanner protects code and generated attributes from later emphasis replacements.
        $pattern = '~`([^`\n]+)`|(!?)\[([^\]\n]*)\]\(([^\s()]+)(?:\s+"([^"\n]*)")?\)|\*\*(.+?)\*\*|__(.+?)__|\*([^*\n]+)\*|(?<!\w)_([^_\n]+)_(?!\w)~u';
        $html = ''; $offset = 0;
        preg_match_all($pattern, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL);
        foreach ($matches as $m) {
            $html .= \e(substr($text, $offset, $m[0][1] - $offset));
            if ($m[1][0] !== null) { $html .= '<code>' . \e($m[1][0]) . '</code>'; }
            elseif ($m[3][0] !== null) {
                $image = $m[2][0] === '!';
                if (!$this->safeUrl($m[4][0], $image)) { $html .= \e($m[0][0]); }
                else {
                    $href = $m[4][0];
                    if (parse_url($href, PHP_URL_SCHEME) === null && !str_starts_with($href, '#')) { $href = \url($href); }
                    $title = $m[5][0] === null ? '' : ' title="' . \e($m[5][0]) . '"';
                    $html .= $image ? '<img src="' . \e($href) . '" alt="' . \e($m[3][0]) . '"' . $title . ' loading="lazy">'
                        : '<a href="' . \e($href) . '"' . $title . '>' . \e($m[3][0]) . '</a>';
                }
            } else {
                $bold = $m[6][0] ?? $m[7][0];
                $html .= $bold !== null ? '<strong>' . $this->inline($bold) . '</strong>' : '<em>' . $this->inline($m[8][0] ?? $m[9][0]) . '</em>';
            }
            $offset = $m[0][1] + strlen($m[0][0]);
        }
        return $html . \e(substr($text, $offset));
    }

    private function toc(): string
    {
        if (!$this->headings) { return ''; }
        $html = '<ul>'; $open = false; $nested = false;
        foreach ($this->headings as [$level, $id, $title]) {
            if ($level === 2) {
                if ($nested) { $html .= '</li></ul>'; $nested = false; }
                if ($open) { $html .= '</li>'; }
                $html .= '<li>'; $open = true;
            } elseif ($open) {
                $html .= $nested ? '</li>' : '<ul>';
                $html .= '<li>'; $nested = true;
            } else {
                // A leading h3 is a root entry, so the TOC never contains an orphan list.
                $html .= '<li>'; $open = true;
            }
            $html .= '<a href="#' . \e($id) . '">' . \e($title) . '</a>';
        }
        return $html . ($nested ? '</li></ul>' : '') . '</li></ul>';
    }
}
