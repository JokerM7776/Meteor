<?php
declare(strict_types=1);
namespace App\Services;
use Core\{Database, LanguagePack};

/** Adds languages that ship with Meteor (lang/<code>.json) to the site, switched Off. */
final class LanguagePacks
{
    /**
     * Same as LanguagePack::RENAMED. sync() runs right after an online update, in a request that
     * already loaded the previous version's LanguagePack class, so it may only use LanguagePack
     * methods that 0.1.x had: all(), bundled(), parse() and validCode().
     */
    public const RENAMED = ['zh' => 'zh-hans'];

    /**
     * Each bundled language is offered once: it is added as Off unless the site already has it,
     * and remembered in the `language_packs_known` setting so a deleted language stays deleted.
     * Downloads that the bundled files have caught up with are removed. Returns the codes added.
     */
    public static function sync(Database $db, ?string $directory = null, ?string $downloads = null): array
    {
        $directory ??= LanguagePack::directory();
        LanguageDownloads::prune($directory, $downloads ?? dirname($directory) . '/storage/lang');
        $builtins = require dirname(__DIR__, 2) . '/lang/en.php';
        $packs = LanguagePack::all($builtins, $directory);
        $known = self::known($db);
        // The files the site's languages read, so a renamed file (zh → zh-hans) is not added twice.
        $files = array_map(fn (string $code): string => self::RENAMED[$code] ?? $code, array_column($db->all('SELECT code FROM locales'), 'code'));
        $added = [];
        foreach ($packs as $code => $pack) {
            if (in_array($code, $known, true) || in_array($code, $files, true)) { continue; }
            self::add($db, $pack);
            $added[] = $code;
        }
        self::remember($db, array_keys($packs));
        return $added;
    }

    /** Adds a language from its file, switched Off, at the end of the list. */
    public static function add(Database $db, array $pack): void
    {
        $max = (int) ($db->one('SELECT MAX(sort_order) AS n FROM locales')['n'] ?? 0);
        $db->query('INSERT INTO locales (code, name, native_name, direction, status, sort_order) VALUES (?, ?, ?, ?, ?, ?)',
            [$pack['code'], $pack['name'], $pack['native_name'], $pack['direction'], 'off', $max + 1]);
    }

    /** Marks language files as offered, so sync never adds them again. */
    public static function remember(Database $db, array $codes): void
    {
        $all = array_values(array_unique(array_merge(self::known($db), $codes)));
        sort($all);
        $db->upsert('settings', ['key' => 'language_packs_known', 'locale' => null, 'value' => implode(',', $all)], ['key', 'locale_scope']);
    }

    private static function known(Database $db): array
    {
        $row = $db->one('SELECT value FROM settings WHERE `key` = ? AND locale_scope = ?', ['language_packs_known', '']);
        return array_values(array_filter(explode(',', $row['value'] ?? '')));
    }
}
