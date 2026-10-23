<?php
declare(strict_types=1);
// Bundled language files (lang/<code>.json) are added as Off languages, and the GitHub
// repository/token settings are removed: updates now always come from the official repository.
return static function (\Core\Database $db): void {
    $db->query("DELETE FROM settings WHERE `key` IN ('update_repository', 'update_token')");
    \App\Services\LanguagePacks::sync($db, dirname(__DIR__, 2) . '/lang');
};
