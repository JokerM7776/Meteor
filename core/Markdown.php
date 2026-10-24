<?php
declare(strict_types=1);
namespace Core;

/**
 * Deliberately small Markdown subset; all HTML originates here, never from the input.
 * Extended mode adds tables, footnotes, heading ids, definition lists, task lists, strikethrough,
 * highlight, sub/superscript, emoji shortcodes, backslash escapes and automatic links.
 */
final class Markdown
{
    private array $ids = [];
    private array $headings = [];
    /** Footnote text by label, and the labels in the order they are first referenced. */
    private array $notes = [];
    private array $noteOrder = [];
    private array $noteRefs = [];

    /** $labels: 'footnotes' and 'footnote_back' text in the content's language. */
    public function __construct(private bool $extended = false, private array $labels = []) {}

    public function compile(string $markdown): array
    {
        $this->ids = $this->headings = $this->notes = $this->noteOrder = $this->noteRefs = [];
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $markdown));
        if ($this->extended) { $lines = $this->extractNotes($lines); }
        $html = $this->blocks($lines) . $this->footnotes();
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
                $custom = null;
                if ($this->extended && preg_match('/^(.*?)\s*\{#([\p{L}\p{N}_-]{1,100})\}$/u', $m[2], $hm) && $hm[1] !== '') { [, $m[2], $custom] = $hm; }
                $inline = $this->inline($m[2]);
                $title = html_entity_decode(strip_tags($inline), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $base = $custom ?? \slugify($title);
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
            } elseif ($this->extended && ($aligns = $this->tableAt($lines, $i - 1)) !== null) {
                $html .= $this->table($lines, $i, $aligns);
            } elseif ($this->listItem($line) !== null) {
                $i--;
                $html .= $this->listing($lines, $i, $this->listItem($line)[0], 0);
            } elseif ($this->extended && $i < $count && preg_match(self::DEFINITION, $lines[$i])) {
                $html .= $this->definitions($lines, $i);
            } else {
                $paragraph = [$line];
                while ($i < $count && trim($lines[$i]) !== '' && !$this->startsBlock($lines[$i]) && ($this->tableAt($lines, $i) === null)) { $paragraph[] = $lines[$i++]; }
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
            if ($this->extended && preg_match('/^\[([ xX])\]\s+(.*)$/', $item[2], $task)) {
                $html .= '<li class="task"><input type="checkbox" disabled' . ($task[1] === ' ' ? '' : ' checked') . '> ' . $this->inline($task[2]);
            } else { $html .= '<li>' . $this->inline($item[2]); }
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
        $extra = $this->extended ? '\\\\(?<esc>[!-\/:-@\[-`{-~])|<(?<auto>https?://[^\s<>]+|[^\s<>@:]+@[^\s<>@]+\.[A-Za-z]{2,})>|\[\^(?<note>[^\]\s]+)\]|' : '';
        $more = $this->extended ? '|~~(?<del>.+?)~~|==(?<mark>[^\s=](?:[^\n]*?[^\s=])?)==|(?<!~)~(?<sub>[^~\s]+)~(?!~)|\^(?<sup>[^\^\s]+)\^'
            . '|(?<![\w/@])(?<url>https?://[^\s<>"]+)|:(?<emoji>[a-z0-9_+-]+):' : '';
        $pattern = '#' . $extra . '`(?<code>[^`\n]+)`|(?<bang>!?)\[(?<label>[^\]\n]*)\]\((?<href>[^\s()]+)(?:\s+"(?<title>[^"\n]*)")?\)'
            . '|\*\*(?<b1>.+?)\*\*|__(?<b2>.+?)__|\*(?<i1>[^*\n]+)\*|(?<!\w)_(?<i2>[^_\n]+)_(?!\w)' . $more . '#u';
        $html = ''; $offset = 0;
        preg_match_all($pattern, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL);
        foreach ($matches as $m) {
            if ($m[0][1] < $offset) { continue; }
            $html .= \e(substr($text, $offset, $m[0][1] - $offset));
            $end = $m[0][1] + strlen($m[0][0]);
            $g = static fn (string $name): ?string => $m[$name][0] ?? null;
            if ($g('esc') !== null) { $html .= \e($g('esc')); }
            elseif ($g('auto') !== null) {
                $href = str_contains($g('auto'), '://') ? $g('auto') : 'mailto:' . $g('auto');
                $html .= $this->safeUrl($href, false) ? '<a href="' . \e($href) . '">' . \e($g('auto')) . '</a>' : \e($m[0][0]);
            } elseif ($g('note') !== null) { $html .= $this->noteRef($g('note')) ?? \e($m[0][0]); }
            elseif ($g('code') !== null) { $html .= '<code>' . \e($g('code')) . '</code>'; }
            elseif ($g('label') !== null) {
                $image = $g('bang') === '!';
                if (!$this->safeUrl($g('href'), $image)) { $html .= \e($m[0][0]); }
                else {
                    $href = $g('href');
                    if (parse_url($href, PHP_URL_SCHEME) === null && !str_starts_with($href, '#')) { $href = \url($href); }
                    $title = $g('title') === null ? '' : ' title="' . \e($g('title')) . '"';
                    $html .= $image ? '<img src="' . \e($href) . '" alt="' . \e($g('label')) . '"' . $title . ' loading="lazy">'
                        : '<a href="' . \e($href) . '"' . $title . '>' . \e($g('label')) . '</a>';
                }
            } elseif (($bold = $g('b1') ?? $g('b2')) !== null) { $html .= '<strong>' . $this->inline($bold) . '</strong>'; }
            elseif (($italic = $g('i1') ?? $g('i2')) !== null) { $html .= '<em>' . $this->inline($italic) . '</em>'; }
            elseif ($g('del') !== null) { $html .= '<del>' . $this->inline($g('del')) . '</del>'; }
            elseif ($g('mark') !== null) { $html .= '<mark>' . $this->inline($g('mark')) . '</mark>'; }
            elseif ($g('sub') !== null) { $html .= '<sub>' . \e($g('sub')) . '</sub>'; }
            elseif ($g('sup') !== null) { $html .= '<sup>' . \e($g('sup')) . '</sup>'; }
            elseif ($g('url') !== null) {
                // Trailing punctuation and an unbalanced ")" belong to the sentence, not the link.
                $url = $g('url');
                while ($url !== '' && (preg_match('/[.,;:!?\'*_~]$/', $url) || (str_ends_with($url, ')') && substr_count($url, '(') < substr_count($url, ')')))) { $url = substr($url, 0, -1); }
                $end = $m[0][1] + strlen($url);
                $html .= $this->safeUrl($url, false) ? '<a href="' . \e($url) . '">' . \e($url) . '</a>' : \e($url);
            } else { $html .= \e(Emoji::CODES[$g('emoji')] ?? $m[0][0]); }
            $offset = $end;
        }
        return $html . \e(substr($text, $offset));
    }

    private const DEFINITION = '/^ {0,3}:\s+(\S.*)$/';

    /** Column alignments when a table starts at $i (header row + delimiter row), else null. */
    private function tableAt(array $lines, int $i): ?array
    {
        if (!$this->extended || !isset($lines[$i + 1]) || !str_contains($lines[$i], '|') || !str_contains($lines[$i + 1], '|') && count($this->cells($lines[$i])) < 2) { return null; }
        $head = $this->cells($lines[$i]); $aligns = [];
        foreach ($this->cells($lines[$i + 1]) as $cell) {
            if (!preg_match('/^(:?)-+(:?)$/', $cell, $a)) { return null; }
            $aligns[] = $a[1] && $a[2] ? 'center' : ($a[2] ? 'right' : ($a[1] ? 'left' : ''));
        }
        return count($aligns) === count($head) ? $aligns : null;
    }

    private function cells(string $line): array
    {
        $line = trim($line);
        if (str_starts_with($line, '|')) { $line = substr($line, 1); }
        if (str_ends_with($line, '|') && !str_ends_with($line, '\|')) { $line = substr($line, 0, -1); }
        return array_map(fn ($cell) => str_replace('\|', '|', trim($cell)), preg_split('/(?<!\\\\)\|/', $line));
    }

    /** $i points just after the header row. Alignment uses classes: the CSP blocks inline styles. */
    private function table(array $lines, int &$i, array $aligns): string
    {
        $row = function (string $line, string $cell) use ($aligns): string {
            $cells = array_pad(array_slice($this->cells($line), 0, count($aligns)), count($aligns), '');
            $html = '<tr>';
            foreach ($cells as $n => $text) { $html .= '<' . $cell . ($aligns[$n] ? ' class="md-' . $aligns[$n] . '"' : '') . '>' . $this->inline($text) . '</' . $cell . '>'; }
            return $html . '</tr>';
        };
        $html = '<div class="md-table"><table><thead>' . $row($lines[$i - 1], 'th') . '</thead>';
        $i++;
        $body = '';
        while ($i < count($lines) && trim($lines[$i]) !== '' && str_contains($lines[$i], '|') && !$this->startsBlock($lines[$i])) { $body .= $row($lines[$i++], 'td'); }
        return $html . ($body !== '' ? '<tbody>' . $body . '</tbody>' : '') . "</table></div>\n";
    }

    /** Definition list: a term line followed by ": definition" lines; $i points at the first definition. */
    private function definitions(array $lines, int &$i): string
    {
        $html = '<dl>'; $i--;
        while (true) {
            $html .= '<dt>' . $this->inline(trim($lines[$i++])) . '</dt>';
            while ($i < count($lines) && preg_match(self::DEFINITION, $lines[$i], $d)) { $html .= '<dd>' . $this->inline($d[1]) . '</dd>'; $i++; }
            $next = $i;
            while ($next < count($lines) && trim($lines[$next]) === '') { $next++; }
            if ($next + 1 < count($lines) && !$this->startsBlock($lines[$next]) && preg_match(self::DEFINITION, $lines[$next + 1])) { $i = $next; continue; }
            return $html . "</dl>\n";
        }
    }

    /** Removes "[^label]: text" definitions (outside code fences); indented lines continue a note. */
    private function extractNotes(array $lines): array
    {
        $kept = []; $fence = null;
        for ($i = 0, $count = count($lines); $i < $count; $i++) {
            $line = $lines[$i];
            if (preg_match('/^\s*(`{3,}|~{3,})/', $line, $f)) {
                if ($fence === null) { $fence = $f[1]; } elseif ($f[1][0] === $fence[0] && strlen($f[1]) >= strlen($fence)) { $fence = null; }
            }
            if ($fence !== null || !preg_match('/^ {0,3}\[\^([^\]\s]+)\]:\s?(.*)$/', $line, $m)) { $kept[] = $line; continue; }
            $text = [$m[2]];
            while ($i + 1 < $count && (preg_match('/^(?: {2,}|\t)(.*)$/', $lines[$i + 1], $c) || (trim($lines[$i + 1]) === '' && isset($lines[$i + 2]) && preg_match('/^(?: {2,}|\t)\S/', $lines[$i + 2])))) {
                $text[] = $c[1] ?? ''; $i++; unset($c);
            }
            $this->notes[$m[1]] ??= implode("\n", $text);
        }
        return $kept;
    }

    private function noteRef(string $label): ?string
    {
        if (!isset($this->notes[$label])) { return null; }
        $number = array_search($label, $this->noteOrder, true);
        if ($number === false) { $this->noteOrder[] = $label; $number = count($this->noteOrder) - 1; }
        $number++;
        $this->noteRefs[$label] = ($this->noteRefs[$label] ?? 0) + 1;
        $id = 'fnref-' . $number . ($this->noteRefs[$label] > 1 ? '-' . $this->noteRefs[$label] : '');
        return '<sup class="footnote-ref"><a href="#fn-' . $number . '" id="' . $id . '" role="doc-noteref">' . $number . '</a></sup>';
    }

    private function footnotes(): string
    {
        if (!$this->noteOrder) { return ''; }
        $items = '';
        // Notes may reference further notes, which are appended while this loop runs.
        for ($n = 0; $n < count($this->noteOrder); $n++) {
            $number = $n + 1;
            $body = trim($this->blocks(explode("\n", $this->notes[$this->noteOrder[$n]])));
            $back = ' <a href="#fnref-' . $number . '" class="footnote-back" role="doc-backlink" aria-label="' . \e(($this->labels['footnote_back'] ?? 'Back to text') . ' ' . $number) . '">↩︎</a>';
            $body = str_ends_with($body, '</p>') ? substr($body, 0, -4) . $back . '</p>' : $body . $back;
            $items .= '<li id="fn-' . $number . '">' . $body . '</li>';
        }
        return '<section class="footnotes" role="doc-endnotes" aria-label="' . \e($this->labels['footnotes'] ?? 'Footnotes') . '"><ol>' . $items . "</ol></section>\n";
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
