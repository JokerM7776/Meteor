<?php
declare(strict_types=1);
namespace App\Services\Import;
use App\Services\MediaLibrary;

final class Images
{
    private array $known = [];
    private array $added = [];
    private array $before = [];
    private bool $validateOnly = false;
    private bool $resolved = false;
    public function __construct(private MediaLibrary $media, private string $storage, private Report $report, private \Closure $resolve) {}
    public function begin(): void { $this->before = $this->known; $this->added = []; }
    public function rollback(): void
    {
        foreach ($this->added as $id) { $this->media->delete($id); } $this->known = $this->before;
    }
    public function rewrite(array &$item, bool $validateOnly = false): void
    {
        $this->validateOnly = $validateOnly;
        $item['markdown'] = HtmlToMarkdown::outsideCode($item['markdown'], function ($md) use (&$item) {
            return preg_replace_callback('~!\[((?:\\\\.|[^\]])*)\]\(<?([^\s)>]+)>?(?:\s+["\x27][^\n]*?["\x27])?\)~', function ($m) use (&$item) {
                $rewritten = $this->url($m[2], $m[1], $item);
                $offset = strlen($m[1]) + 4 + (str_starts_with(substr($m[0], strlen($m[1]) + 4), '<') ? 1 : 0);
                return substr_replace($m[0], $rewritten, $offset, strlen($m[2]));
            }, $md);
        });
        if ($item['cover']) {
            $original = $item['cover']; $item['cover'] = $this->url($original, $item['title'], $item);
            if (!$this->resolved || !str_starts_with($item['cover'], MediaLibrary::DIRECTORY)) {
                $item['cover'] = null; $item['warnings'][] = 'Cover not imported: ' . $original;
            }
        }
    }
    private function url(string $url, string $alt, array &$item): string
    {
        $this->resolved = false;
        $file = ($this->resolve)($url, $item);
        if ($file === null) { return $url; }
        [$name, $bytes] = $file;
        if ($bytes === null) { $item['warnings'][] = 'Missing image: ' . $url; if (!$this->validateOnly) { $this->report->count('images', 'skipped'); } return $url; }
        $size = @getimagesizefromstring($bytes); $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (!$size || !isset(MediaLibrary::TYPES[$mime])) { $item['warnings'][] = 'Not a supported image: ' . $url; if (!$this->validateOnly) { $this->report->count('images', 'skipped'); } return $url; }
        $this->resolved = true;
        if ($this->validateOnly) { return MediaLibrary::DIRECTORY . 'checked-image'; }
        $hash = sha1($bytes);
        if (isset($this->known[$hash])) { return $this->known[$hash]; }
        if ($this->report->check) { $this->report->count('images'); return $this->known[$hash] = MediaLibrary::DIRECTORY . 'checked-' . $hash; }
        if (!is_dir($this->storage) || !is_writable($this->storage)) { throw new \RuntimeException('The storage folder must be writable to import images.'); }
        $temp = tempnam($this->storage, 'import-image-');
        if ($temp === false) { throw new \RuntimeException('Cannot create an import temporary file.'); }
        try {
            if (file_put_contents($temp, $bytes) !== strlen($bytes)) { throw new \RuntimeException('Cannot write import temporary file.'); }
            $row = $this->media->import($temp, basename($name), ['alt' => mb_substr($alt, 0, 255), 'category' => 'Imported']);
        } finally { unlink($temp); }
        $this->added[] = (int) $row['id']; $this->report->count('images');
        if ($row['note'] !== '') { $item['warnings'][] = $row['note']; }
        return $this->known[$hash] = MediaLibrary::url($row['file_name']);
    }
}
