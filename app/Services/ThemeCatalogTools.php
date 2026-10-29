<?php
declare(strict_types=1);
namespace App\Services;
use Core\{CurlHttpClient, GitHubUpdates, ReleasePackage, ThemeCatalog};

/** Author and maintainer CLI operations; downloads are always explicit. */
final class ThemeCatalogTools
{
    public static function entry(string $repo, string $url, ?string $local): string
    {
        if (!ThemeCatalog::repository($repo) || !ThemeCatalog::zipUrl($url, $repo)) { throw new \InvalidArgumentException('Use owner/repo and a .zip release URL from that repository.'); }
        $file = $local ?? self::download($url, ThemePackage::LIMIT);
        try {
            $manifest = ThemeCatalogPackage::inspect($file, ThemeCheck::database());
            $entry = array_intersect_key($manifest, array_flip(['name', 'description', 'author', 'license', 'version', 'api', 'requires']));
            $entry += ['description' => '', 'author' => ''];
            $entry += ['repo' => $repo, 'zip' => $url, 'size' => filesize($file), 'sha256' => hash_file('sha256', $file), 'updated' => gmdate('Y-m-d\TH:i:s\Z')];
            $entries = ThemeCatalog::parse(ThemeCatalog::encode([$manifest['slug'] => $entry]));
            return json_encode((object) $entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
        } finally { if ($local === null) { unlink($file); } }
    }

    public static function check(string $index, array $arguments): int
    {
        $entries = ThemeCatalog::parse((string) file_get_contents($index));
        if (!$arguments) { echo "Theme catalog valid: " . count($entries) . (count($entries) === 1 ? " theme.\n" : " themes.\n"); return 0; }
        if (array_shift($arguments) !== '--download') { throw new \InvalidArgumentException('Use catalog check [--download [slug ...]].'); }
        foreach ($arguments as $slug) { if (!isset($entries[$slug])) { throw new \InvalidArgumentException('Theme ' . $slug . ' is not in the catalog.'); } }
        $failed = false;
        foreach ($entries as $slug => $entry) {
            if ($arguments && !in_array($slug, $arguments, true)) { continue; }
            $file = null; $findings = [];
            // The checker renders PHP: retain output until all fixtures have sent their headers.
            ob_start();
            try {
                $file = self::download($entry['zip'], $entry['size']);
                ThemeCatalogPackage::verify((string) file_get_contents($file), $entry);
                ThemeCatalogPackage::inspect($file, ThemeCheck::database(), $slug, $entry);
                $findings = (new ThemeCheck())->check($file);
            } catch (\Throwable $e) { $findings[] = $e->getMessage(); }
            finally { ob_end_clean(); if ($file !== null) { unlink($file); } }
            foreach ($findings as $finding) { echo $slug . ': ' . str_replace(["\r", "\n"], ' ', $finding) . "\n"; }
            echo $slug . ': ' . count($findings) . " findings.\n";
            $failed = $failed || (bool) $findings;
        }
        echo $failed ? "Fix the findings above.\n" : "All catalog themes are valid.\n";
        return $failed ? 1 : 0;
    }

    private static function download(string $url, int $limit): string
    {
        $bytes = (new GitHubUpdates(new CurlHttpClient()))->themeZip($url, $limit);
        $file = tempnam(dirname(__DIR__, 2) . '/storage', 'theme-entry-');
        if ($file === false) { throw new \RuntimeException('Cannot create a temporary theme file.'); }
        try { ReleasePackage::write($file, $bytes); return $file; }
        catch (\Throwable $e) { unlink($file); throw $e; }
    }
}
