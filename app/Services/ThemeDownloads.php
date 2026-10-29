<?php
declare(strict_types=1);
namespace App\Services;
use Core\{Database, GitHubUpdates, ReleasePackage, ThemeCatalog, Version};

final class ThemeDownloads
{
    private Themes $themes;
    private string $root;
    public function __construct(private Database $db, private GitHubUpdates $github, ?string $root = null)
    {
        $this->themes = new Themes($db, $root);
        $this->root = $this->themes->root();
    }

    public function check(): array
    {
        try { $entries = ThemeCatalog::parse($this->github->themeIndex(ThemeCatalog::MAX_BYTES)); }
        catch (\RuntimeException $e) { throw self::networkError($e, 'Could not read the theme catalog on GitHub', 'Try again later, or upload a theme zip by hand.'); }
        $data = json_decode(ThemeCatalog::encode($entries), true);
        $data['themes'] = (object) $entries;
        $data['checked_at'] = gmdate('Y-m-d\TH:i:s\Z');
        self::write($this->root . '/.catalog.json', $data);
        return $entries;
    }

    public function catalog(): ?array
    {
        $file = $this->root . '/.catalog.json';
        if (!is_file($file) || filesize($file) > ThemeCatalog::MAX_BYTES + 100) { return null; }
        $json = (string) file_get_contents($file);
        $data = json_decode($json);
        if (!$data instanceof \stdClass || !ThemeCatalog::utc($data->checked_at ?? null)) { return null; }
        unset($data->checked_at);
        try { $entries = ThemeCatalog::parse(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)); }
        catch (\RuntimeException | \JsonException) { return null; }
        return ['checked_at' => json_decode($json, true)['checked_at'], 'themes' => $entries];
    }

    public function installed(): array { return self::readInstalled($this->root); }

    public function rows(): array
    {
        $installed = $this->installed(); $rows = [];
        foreach ($this->catalog()['themes'] ?? [] as $slug => $entry) {
            $exists = file_exists($this->root . '/' . $slug) || is_link($this->root . '/' . $slug);
            $meta = $installed[$slug] ?? null;
            $state = 'new'; $message = '';
            if ($exists) {
                if ($meta === null) { $state = 'local'; $message = 'A theme named ' . $slug . ' is already installed here'; }
                else { $state = $meta['sha256'] === $entry['sha256'] || version_compare($entry['version'], $meta['version'], '<=') ? 'current' : 'update'; }
            }
            if (!in_array($entry['api'], Themes::API, true) || version_compare(Version::current(), $entry['requires'], '<')) {
                $state = 'incompatible'; $message = 'Needs Meteor ' . $entry['requires'];
            }
            $rows[$slug] = $entry + ['state' => $state, 'message' => $message];
        }
        return $rows;
    }

    public function install(string $slug): string
    {
        $entry = $this->rows()[$slug] ?? null;
        if ($entry === null) { throw new \InvalidArgumentException('This theme is not in the catalog. Check for themes again.'); }
        if (in_array($entry['state'], ['local', 'incompatible'], true)) { throw new \InvalidArgumentException($entry['message']); }
        if ($entry['state'] === 'current') { return $entry['name'] . ' is already installed.'; }
        $file = tempnam(dirname(__DIR__, 2) . '/storage', 'theme-catalog-');
        if ($file === false) { throw new \RuntimeException('Cannot create a temporary theme file.'); }
        try {
            try { $bytes = $this->github->themeZip($entry['zip'], $entry['size']); }
            catch (\RuntimeException $e) {
                if ($e->getMessage() === 'Download exceeds the size limit.') { throw new \RuntimeException(ThemeCatalogPackage::MISMATCH, 0, $e); }
                throw self::networkError($e, 'Could not download ' . $entry['name'] . ' ' . $entry['version'] . ' from GitHub', 'Try again later, or download it from https://github.com/' . $entry['repo'] . ' and upload the zip by hand.');
            }
            ThemeCatalogPackage::verify($bytes, $entry);
            ReleasePackage::write($file, $bytes);
            ThemeCatalogPackage::inspect($file, $this->db, $slug, $entry);
            $this->themes->installZip($file, $entry['state'] === 'update');
            self::remember($this->root, $slug, $entry['version'], $entry['sha256'], $entry['repo']);
            return $entry['state'] === 'update'
                ? 'Updated ' . $entry['name'] . ' to ' . $entry['version'] . '. Your settings are kept.'
                : 'Installed ' . $entry['name'] . ' ' . $entry['version'] . '. Preview it, or activate it.';
        } finally { unlink($file); }
    }

    /** Records that a theme came from the catalog (or is the bundled official starter). */
    public static function remember(string $root, string $slug, string $version, string $sha256, string $repo): void
    {
        $installed = self::readInstalled($root);
        $installed[$slug] = ['version' => $version, 'sha256' => $sha256, 'repo' => $repo, 'installed_at' => gmdate('Y-m-d\TH:i:s\Z')];
        self::write($root . '/.installed.json', (object) $installed);
    }

    public static function forget(string $root, string $slug): void
    {
        $installed = self::readInstalled($root);
        if (!isset($installed[$slug])) { return; }
        unset($installed[$slug]);
        self::write($root . '/.installed.json', (object) $installed);
    }

    private static function readInstalled(string $root): array
    {
        $file = $root . '/.installed.json';
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
        $valid = [];
        foreach (is_array($data) ? $data : [] as $slug => $entry) {
            if (!preg_match(Themes::SLUG, (string) $slug) || !is_array($entry)) { continue; }
            if (!is_string($entry['version'] ?? null) || !preg_match('/^\d+\.\d+\.\d+$/D', $entry['version'])
                || !is_string($entry['sha256'] ?? null) || !preg_match('/^[a-f0-9]{64}$/D', $entry['sha256'])
                || !is_string($entry['repo'] ?? null) || !ThemeCatalog::repository($entry['repo']) || !ThemeCatalog::utc($entry['installed_at'] ?? null)) { continue; }
            $valid[$slug] = $entry;
        }
        return $valid;
    }

    /** Keeps only the first sentence of the GitHub error ("GitHub returned HTTP 404") so advice is not repeated. */
    private static function networkError(\RuntimeException $e, string $what, string $advice): \RuntimeException
    {
        $reason = rtrim(explode('. ', $e->getMessage(), 2)[0], '.');
        return new \RuntimeException($what . ' (' . $reason . '). ' . $advice, 0, $e);
    }

    private static function write(string $file, array|object $data): void
    {
        $temporary = $file . '.tmp-' . bin2hex(random_bytes(6));
        try {
            ReleasePackage::write($temporary, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n");
            if (!@rename($temporary, $file)) { throw new \RuntimeException('Cannot write ' . basename($file) . '. Check that storage/ is writable.'); }
        } finally { if (is_file($temporary)) { unlink($temporary); } }
    }
}
