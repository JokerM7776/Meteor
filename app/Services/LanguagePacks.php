<?php
declare(strict_types=1);
namespace App\Services;
use Core\{Database, LanguagePack};

/** Adds languages that ship with Meteor (lang/<code>.json) to the site, switched Off. */
final class LanguagePacks
{
    /**
     * Each bundled language is offered once: it is added as Off unless the site already has it,
     * and remembered in the `language_packs_known` setting so a deleted language stays deleted.
     * Returns the codes that were added.
     */
    public static function sync(Database $db, ?string $directory = null): array
    {
        $builtins = require dirname(__DIR__, 2) . '/lang/en.php';
        $packs = LanguagePack::all($builtins, $directory);
        $row = $db->one('SELECT value FROM settings WHERE `key` = ? AND locale_scope = ?', ['language_packs_known', '']);
        $known = array_filter(explode(',', $row['value'] ?? ''));
        $added = [];
        foreach ($packs as $code => $pack) {
            if (in_array($code, $known, true) || $db->one('SELECT code FROM locales WHERE code = ?', [$code])) { continue; }
            $max = (int) ($db->one('SELECT MAX(sort_order) AS n FROM locales')['n'] ?? 0);
            $db->query('INSERT INTO locales (code, name, native_name, direction, status, sort_order) VALUES (?, ?, ?, ?, ?, ?)',
                [$code, $pack['name'], $pack['native_name'], $pack['direction'], 'off', $max + 1]);
            $added[] = $code;
        }
        $all = array_values(array_unique(array_merge($known, array_keys($packs))));
        sort($all);
        $db->upsert('settings', ['key' => 'language_packs_known', 'locale' => null, 'value' => implode(',', $all)], ['key', 'locale_scope']);
        return $added;
    }
}
