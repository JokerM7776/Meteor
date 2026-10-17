<?php
declare(strict_types=1);
return static function (\Core\Database $db): void {
    foreach (['post_translations', 'page_translations'] as $table) {
        if (!$db->hasColumn($table, 'published_at')) { $db->query("ALTER TABLE $table ADD COLUMN published_at DATETIME"); }
        $db->query("UPDATE $table SET published_at = updated_at WHERE status = ? AND published_at IS NULL", ['published']);
    }
};
