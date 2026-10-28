<?php
declare(strict_types=1);
namespace App\Services\Import;
use Core\Dates;

final class HexoSource
{
    public array $warnings = [];
    private string $root;
    private array $config = [];
    public string $languageOrigin = 'the site’s default language';
    public function __construct(private Files $files, private array $locales, public string $target, bool $automatic = false)
    {
        $roots = [];
        foreach ([...$files->directories(), ...$files->names()] as $name) { if (preg_match('~^((?:.*/)?)_posts/~', $name, $m)) { $roots[$m[1]] = true; } }
        if (!$roots) { throw new \RuntimeException('No _posts folder found in the Hexo source.'); }
        $roots = array_keys($roots); usort($roots, fn ($a, $b) => [substr_count($a, '/'), strlen($a), $a] <=> [substr_count($b, '/'), strlen($b), $b]);
        $this->root = $roots[0];
        $config = $files->read(Files::join($this->root . '../_config.yml')) ?? $files->read('_config.yml');
        if ($config !== null) {
            $selected = []; $keep = false;
            foreach (explode("\n", $config) as $line) {
                if (preg_match('/^([\w-]+):/u', $line, $key)) { $keep = in_array($key[1], ['timezone', 'url', 'post_asset_folder', 'language'], true); }
                if ($keep) { $selected[] = $line; }
            }
            [$this->config] = FrontMatter::yaml(implode("\n", $selected));
        }
        $language = $this->config['language'] ?? '';
        if (is_array($language)) { $language = reset($language); }
        $mapped = is_string($language) ? LocaleMap::match($language, $locales) : null;
        if ($automatic && $mapped !== null) { $this->target = $mapped; $this->languageOrigin = '_config.yml'; }
        elseif (!$automatic) { $this->languageOrigin = 'your choice'; }
    }
    public function items(): array
    {
        $items = [];
        foreach ($this->files->names() as $path) {
            if (!str_starts_with($path, $this->root) || !preg_match('/\.(md|markdown)$/i', $path)) { continue; }
            $relative = substr($path, strlen($this->root)); $post = preg_match('~^_(posts|drafts)/~', $relative);
            if (!$post && (preg_match('~(?:^|/)_~', $relative) || strcasecmp($relative, 'README.md') === 0)) { continue; }
            [$front, $md, $warnings] = FrontMatter::parse($this->files->read($path));
            if (($front['layout'] ?? null) === false) { continue; }
            $file = pathinfo($path, PATHINFO_FILENAME); $fallback = preg_replace('/^\d{4}-\d{2}-\d{2}-/', '', $file);
            if (!$post && strtolower($file) === 'index') { $fallback = basename(dirname($path)); }
            $slug = (string) ($front['slug'] ?? $front['permalink'] ?? $fallback);
            $slug = rawurldecode(basename(rtrim((string) (parse_url($slug, PHP_URL_PATH) ?: $slug), '/')));
            $language = $front['lang'] ?? $front['language'] ?? null;
            if ($language === null && preg_match('~^_(?:posts|drafts)/([^/]+)/~', $relative, $folder)) {
                $language = LocaleMap::match($folder[1], $this->locales);
            }
            $locale = $this->language(is_scalar($language) ? (string) $language : '', $warnings);
            $tags = self::flatten($front['tags'] ?? []); $categories = $front['categories'] ?? $front['category'] ?? [];
            $categories = self::flatten($categories); $category = array_shift($categories);
            if ($categories) { $warnings[] = 'Category hierarchy or extra categories flattened into tags.'; $tags = array_merge($tags, $categories); }
            $summary = HtmlToMarkdown::summary($md, $front['description'] ?? $front['excerpt'] ?? $front['summary'] ?? null);
            $asset = '/' . preg_replace('/\.(md|markdown)$/i', '', substr($path, strlen($this->root)));
            $md = HexoTags::convert($md, $asset, $warnings); $md = HtmlToMarkdown::inline(ReferenceLinks::convert($md), $warnings);
            $dateInput = $front['date'] ?? (preg_match('/^\d{4}-\d{2}-\d{2}/', $file, $m) ? $m[0] : 'now');
            $date = $this->date($dateInput, $warnings); $updated = $this->date($front['updated'] ?? $dateInput, $warnings);
            $cover = null; foreach (['cover', 'thumbnail', 'banner', 'index_img', 'cover_image', 'top_img'] as $key) { if (is_string($front[$key] ?? null)) { $cover = $front[$key]; break; } }
            $items[] = ['kind' => $post ? 'post' : 'page', 'group' => ($post ? 'post:' : 'page:') . $slug, 'locale' => $locale, 'slug' => $slug,
                'title' => is_scalar($front['title'] ?? null) ? (string) $front['title'] : $fallback, 'summary' => $summary, 'markdown' => $md,
                'status' => str_starts_with($relative, '_drafts/') || ($front['published'] ?? true) === false ? 'draft' : 'published',
                'date' => $date, 'updated' => $updated, 'category' => $category, 'category_description' => '', 'tags' => array_unique($tags), 'cover' => $cover,
                'pinned' => (bool) ($front['sticky'] ?? $front['top'] ?? $front['pin'] ?? $front['pinned'] ?? false),
                'comments_enabled' => ($front['comments'] ?? true) !== false, 'sort_order' => (int) ($front['order'] ?? 0), 'comments' => [], 'source' => $path, 'warnings' => $warnings];
        }
        return $items;
    }
    private static function flatten(mixed $value): array
    {
        if (!is_array($value)) { return is_scalar($value) && trim((string) $value) !== '' ? [trim((string) $value)] : []; }
        $out = []; foreach ($value as $v) { $out = array_merge($out, self::flatten($v)); } return $out;
    }
    private function language(string $lang, array &$warnings): string
    {
        if ($lang === '') { return $this->target; }
        $mapped = LocaleMap::match($lang, $this->locales);
        if ($mapped !== null) { return $mapped; }
        $warnings[] = 'Unknown language ' . $lang . '; using ' . $this->target . '.'; return $this->target;
    }
    private function date(mixed $value, array &$warnings): string
    {
        try { $zone = isset($this->config['timezone']) ? new \DateTimeZone((string) $this->config['timezone']) : Dates::zone(); return Dates::toUtc(new \DateTimeImmutable((string) $value, $zone)); }
        catch (\Throwable) { $warnings[] = 'Invalid date or timezone; using current time.'; return gmdate('Y-m-d H:i:s'); }
    }
    public function image(string $url, array $item): ?array
    {
        $site = rtrim((string) ($this->config['url'] ?? ''), '/');
        if ($site !== '' && str_starts_with($url, $site . '/')) { $url = substr($url, strlen($site)); }
        if (preg_match('~^(https?:)?//~i', $url)) { return null; }
        $path = rawurldecode((string) (parse_url($url, PHP_URL_PATH) ?? $url));
        $asset = preg_replace('/\.(md|markdown)$/i', '', $item['source']);
        $candidates = str_starts_with($path, '/') ? [$this->root . ltrim($path, '/')] : [$asset . '/' . $path, dirname($item['source']) . '/' . $path];
        foreach ($candidates as $candidate) { $candidate = Files::join($candidate); if ($this->files->has($candidate)) { return [$candidate, $this->files->read($candidate, \App\Services\MediaLibrary::MAX_BYTES)]; } }
        return [$url, null];
    }
}
