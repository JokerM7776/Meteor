<?php
declare(strict_types=1);
namespace App\Services;
use Core\{Database, GitHubUpdates, LanguageCatalog, LanguagePack, ReleasePackage};

/**
 * Languages from the catalog on GitHub (lang/ on the main branch). A downloaded file is stored as
 * storage/lang/<code>.json and wins over the bundled copy until a Meteor update ships a copy that
 * is at least as new. storage/lang/catalog.json keeps the last check; installed.json records the
 * date of each download.
 */
final class LanguageDownloads
{
    private string $directory;
    private string $downloads;
    private array $builtins;

    public function __construct(private Database $db, private GitHubUpdates $github, ?string $directory = null, ?string $downloads = null)
    {
        $this->directory = $directory ?? LanguagePack::directory();
        $this->downloads = $downloads ?? LanguagePack::downloads();
        $this->builtins = require dirname(__DIR__, 2) . '/lang/en.php';
    }

    /** Fetches the catalog and keeps a copy for the Languages page. */
    public function check(): array
    {
        $languages = LanguageCatalog::parse($this->fetch(LanguageCatalog::FILE, LanguageCatalog::MAX_BYTES));
        self::write($this->downloads . '/catalog.json', json_encode(['meteor_language_index' => LanguageCatalog::FORMAT,
            'checked_at' => gmdate('Y-m-d\TH:i:s\Z'), 'languages' => (object) $languages], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        return $languages;
    }

    /** The last check as ['checked_at' => …, 'languages' => [code => entry]], or null. */
    public function catalog(): ?array
    {
        $file = $this->downloads . '/catalog.json';
        if (!is_file($file)) { return null; }
        $json = (string) file_get_contents($file);
        try { $languages = LanguageCatalog::parse($json); } catch (\RuntimeException) { return null; }
        $checked = json_decode($json, true)['checked_at'] ?? null;
        return is_string($checked) ? ['checked_at' => $checked, 'languages' => $languages] : null;
    }

    /**
     * Each catalog language with its state here: 'new' (not on this site), 'current' (this site
     * has the same file, bundled or downloaded) or 'update'. `site_code` is the site's code for it.
     */
    public function rows(): array
    {
        $catalog = $this->catalog();
        if (!$catalog) { return []; }
        $rows = [];
        foreach ($catalog['languages'] as $code => $entry) {
            $hashes = [];
            foreach ([$this->downloads, $this->directory] as $directory) {
                if (is_file($directory . '/' . $code . '.json')) { $hashes[] = hash_file('sha256', $directory . '/' . $code . '.json'); }
            }
            $site = $this->siteCode($code);
            $rows[$code] = $entry + ['site_code' => $site, 'state' => $site === null ? 'new' : (in_array($entry['sha256'], $hashes, true) ? 'current' : 'update')];
        }
        return $rows;
    }

    /** Downloads one language from the last checked catalog. A new language is added as Off. */
    public function install(string $code): string
    {
        $entry = $this->catalog()['languages'][$code] ?? null;
        if ($entry === null) { throw new \InvalidArgumentException('This language is not in the catalog. Check for languages again.'); }
        $json = $this->fetch($code . '.json', LanguagePack::MAX_BYTES);
        if (!hash_equals($entry['sha256'], hash('sha256', $json))) { throw new \RuntimeException('The language file changed after the last check. Check for languages again, then retry.'); }
        $pack = LanguagePack::parse($json, $this->builtins);
        if ($pack['code'] !== $code) { throw new \RuntimeException('The downloaded file is for another language.'); }
        self::write($this->downloads . '/' . $code . '.json', $json);
        $installed = self::read($this->downloads . '/installed.json');
        $installed[$code] = ['sha256' => $entry['sha256'], 'updated' => $entry['updated']];
        self::write($this->downloads . '/installed.json', json_encode($installed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $site = $this->siteCode($code);
        if ($site === null) { LanguagePacks::add($this->db, $pack); }
        LanguagePacks::remember($this->db, [$code]);
        $texts = count($pack['strings']) . (count($pack['strings']) === 1 ? ' interface text' : ' interface texts');
        return $site === null
            ? 'Added ' . $pack['name'] . ' with ' . $texts . '. It is Off until you switch it on.'
            : 'Updated ' . $pack['name'] . ': ' . $texts . '. Your own edits are kept.';
    }

    /**
     * Removes downloads that the bundled files have caught up with, so a Meteor update with newer
     * text is not hidden by an older download.
     */
    public static function prune(string $directory, string $downloads): void
    {
        $installed = self::read($downloads . '/installed.json');
        if (!$installed) { return; }
        $bundled = LanguageCatalog::bundled($directory);
        foreach ($installed as $code => $meta) {
            if (!is_string($code) || !LanguagePack::validCode($code)) { unset($installed[$code]); continue; }
            if (!isset($bundled[$code]) || !is_string($meta['updated'] ?? null) || strcmp($bundled[$code]['updated'], $meta['updated']) < 0) { continue; }
            if (is_file($downloads . '/' . $code . '.json')) { unlink($downloads . '/' . $code . '.json'); }
            unset($installed[$code]);
        }
        self::write($downloads . '/installed.json', json_encode((object) $installed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /** The site's code for a catalog language: the same code, or the old one it was renamed from. */
    private function siteCode(string $code): ?string
    {
        foreach (array_merge([$code], array_keys(LanguagePack::RENAMED, $code, true)) as $candidate) {
            if ($this->db->one('SELECT code FROM locales WHERE code = ?', [$candidate])) { return $candidate; }
        }
        return null;
    }

    private function fetch(string $file, int $limit): string
    {
        try { return $this->github->language($file, $limit); }
        catch (\RuntimeException $e) { throw new \RuntimeException('Could not read the language catalog on GitHub (' . rtrim($e->getMessage(), '.') . '). Try again later, or import a language file by hand.', 0, $e); }
    }

    private static function read(string $file): array
    {
        if (!is_file($file)) { return []; }
        $data = json_decode((string) file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }

    private static function write(string $file, string $bytes): void
    {
        $temporary = $file . '.tmp-' . bin2hex(random_bytes(6));
        ReleasePackage::write($temporary, $bytes);
        if (!rename($temporary, $file)) { unlink($temporary); throw new \RuntimeException('Cannot write ' . basename($file) . '. Check that storage/ is writable.'); }
    }
}
