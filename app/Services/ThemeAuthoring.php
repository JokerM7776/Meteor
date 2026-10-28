<?php
declare(strict_types=1);
namespace App\Services;

/** File operations shared by the admin and command-line authoring tools. */
trait ThemeAuthoring
{
    public const TEMPLATES = [
        'Layout' => ['layouts/public' => 'The full HTML page frame'],
        'Pages' => ['public/home' => 'Home page', 'public/archive' => 'Posts, categories, tags and pagination', 'public/item' => 'A post or static page',
            'public/search' => 'Search results', 'public/not-found' => 'Page not found', 'public/comment-check' => 'Comment verification'],
        'Partials' => ['partials/announcements' => 'Site announcements', 'partials/author-card' => 'Author profile', 'partials/comments' => 'Comments and comment form',
            'partials/cover' => 'Article cover image', 'partials/favicon' => 'Browser icon', 'partials/fonts' => 'Font resources', 'partials/mark' => 'Site mark',
            'partials/nav' => 'Navigation links', 'partials/new-tab' => 'External window indicator', 'partials/read-link' => 'Read and translation links'],
    ];

    public static function syntaxErrors(string $folder): array
    {
        $errors = [];
        try { $files = ThemePackage::files($folder); } catch (\InvalidArgumentException $e) { return [$e->getMessage()]; }
        foreach ($files as $path => $file) {
            if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'php') { continue; }
            try { token_get_all((string) file_get_contents($file), TOKEN_PARSE); }
            catch (\ParseError $e) { $errors[] = $path . ' line ' . $e->getLine() . ': ' . $e->getMessage(); }
        }
        return $errors;
    }

    private function moveFolder(string $from, string $to): bool
    {
        return $this->rename !== null ? ($this->rename)($from, $to) : @rename($from, $to);
    }

    public function installZip(string $zipPath, bool $replace): array
    {
        ThemePackage::mkdir($this->root);
        $temp = $this->root . '/.upload-' . bin2hex(random_bytes(12));
        try {
            $slug = ThemePackage::extract($zipPath, $temp);
            $staged = new self($this->db, $temp);
            $theme = $staged->usable($slug);
            $errors = self::syntaxErrors($theme['path']);
            if ($errors) { throw new \InvalidArgumentException(implode("\n", $errors)); }
            $target = $this->root . '/' . $slug;
            if (is_link($target) || (file_exists($target) && !is_dir($target))) { throw new \InvalidArgumentException('The destination is not a theme folder.'); }
            $exists = is_dir($target);
            if ($exists && !$replace) { throw new \InvalidArgumentException($theme['name'] . ' is already installed. Tick Replace to update it.'); }
            $old = $this->root . '/.old-' . bin2hex(random_bytes(12));
            if ($exists && !$this->moveFolder($target, $old)) { throw new \InvalidArgumentException('Cannot move the installed theme. Check folder permissions.'); }
            try {
                if (!$this->moveFolder($theme['path'], $target)) { throw new \InvalidArgumentException('Cannot install the theme. Check folder permissions.'); }
            } catch (\Throwable $e) {
                if ($exists && !$this->moveFolder($old, $target)) { throw new \RuntimeException('Could not restore the old theme; it is kept at ' . $old, 0, $e); }
                throw $e;
            }
            if ($exists) { \Core\ReleasePackage::removeTree($old); }
            return $this->find($slug) + ['replaced' => $exists];
        } finally { \Core\ReleasePackage::removeTree($temp); }
    }

    public function delete(string $slug): void
    {
        $theme = $this->find($slug);
        if (!$theme) { throw new \InvalidArgumentException('This theme is not installed.'); }
        if ($this->activeSlug() === $slug) { throw new \InvalidArgumentException('Switch to another theme before deleting it.'); }
        \Core\ReleasePackage::removeTree($theme['path']);
        $this->db->query('DELETE FROM settings WHERE `key` LIKE ?', ['theme.' . $slug . '.%']);
        if (($_SESSION['theme_preview'] ?? '') === $slug) { unset($_SESSION['theme_preview']); }
    }

    public function create(string $name, string $slug = ''): array
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 100) { throw new \InvalidArgumentException('Use a name of 1 to 100 characters.'); }
        if ($slug === '') { $slug = trim(substr(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), 0, 63), '-') ?: 'theme-' . bin2hex(random_bytes(4)); }
        if (!preg_match(self::SLUG, $slug)) { throw new \InvalidArgumentException('Folder name must use lowercase letters, numbers and hyphens (up to 63 characters).'); }
        ThemePackage::mkdir($this->root);
        $target = $this->root . '/' . $slug;
        if (!@mkdir($target, 0755)) { throw new \InvalidArgumentException('A theme folder named “' . $slug . '” already exists or cannot be created.'); }
        $version = explode('.', \Core\Version::current());
        $author = $this->db->one("SELECT value FROM settings WHERE `key` = 'author_name' AND locale_scope = ''")['value'] ?? '';
        try {
            $json = ['name' => $name, 'slug' => $slug, 'version' => '1.0.0', 'api' => 1, 'requires' => ($version[0] ?? '0') . '.' . ($version[1] ?? '6') . '.0',
                'author' => $author, 'license' => '', 'description' => '', 'settings' => []];
            if (file_put_contents($target . '/theme.json', json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n") === false
                || file_put_contents($target . '/theme.css', "/* Add your theme styles here. Public pages allow no inline styles or scripts. */\n") === false) {
                throw new \InvalidArgumentException('Cannot write the theme. Check folder permissions.');
            }
        } catch (\Throwable $e) { \Core\ReleasePackage::removeTree($target); throw $e; }
        return $this->find($slug);
    }

    public function copyTemplate(string $slug, string $template): string
    {
        $theme = $this->usable($slug);
        if (!array_key_exists($template, array_merge(...array_values(self::TEMPLATES)))) { throw new \InvalidArgumentException('Choose a built-in public template.'); }
        $relative = $template . '.php'; $target = $theme['path'] . '/' . $relative;
        $parent = dirname($target);
        if (is_link($parent)) { throw new \InvalidArgumentException('Symlinks are not allowed.'); }
        ThemePackage::mkdir($parent);
        $handle = @fopen($target, 'x');
        if (!$handle) { throw new \InvalidArgumentException($theme['name'] . ' already has ' . $relative . '.'); }
        try { $code = file_get_contents(dirname(__DIR__, 2) . '/views/' . $relative); if (fwrite($handle, $code) !== strlen($code)) { throw new \InvalidArgumentException('Cannot copy the template.'); } }
        finally { fclose($handle); }
        return 'Edit storage/themes/' . $slug . '/' . $relative . '.';
    }

    /** Caller owns the temporary zip and must unlink it after streaming. */
    public function download(string $slug): string
    {
        ThemePackage::zipRequired();
        $theme = $this->find($slug) ?? throw new \InvalidArgumentException('This theme is not installed.');
        $file = tempnam(dirname(__DIR__, 2) . '/storage', 'theme-download-');
        $zip = new \ZipArchive();
        try {
            if ($file === false || $zip->open($file, \ZipArchive::OVERWRITE) !== true) { throw new \InvalidArgumentException('Cannot create the download.'); }
            foreach (ThemePackage::files($theme['path'], true) as $relative => $path) { if (!$zip->addFile($path, $slug . '/' . $relative)) { throw new \InvalidArgumentException('Cannot add ' . $relative); } }
            if (!$zip->close()) { throw new \InvalidArgumentException('Cannot finish the download.'); }
            return $file;
        } catch (\Throwable $e) { if ($file !== false) { @unlink($file); } throw $e; }
    }

    public static function files(array $theme): array
    {
        $result = [];
        try { $files = ThemePackage::files($theme['path']); } catch (\InvalidArgumentException) { return []; }
        foreach ($files as $path => $_) {
            if (preg_match('~^(layouts/public|public/.+|partials/.+|slots/.+)\.php$~', $path)) { $result[] = substr($path, 0, -4); }
        }
        if (isset($files['theme.css'])) { $result[] = 'theme.css'; }
        if ($theme['strings'] ?? []) { $result[] = 'interface text'; }
        return $result;
    }
}
