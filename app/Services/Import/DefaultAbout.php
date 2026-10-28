<?php
declare(strict_types=1);
namespace App\Services\Import;
use App\Services\AboutPage;
use Core\Database;

final class DefaultAbout
{
    public static function id(Database $db): ?int
    {
        $page = $db->one('SELECT id FROM pages WHERE slug = ?', ['about']);
        if (!$page) { return null; }
        $rows = $db->all('SELECT title, summary, content_md, status FROM page_translations WHERE page_id = ?', [$page['id']]);
        if (!$rows) { return null; }
        foreach ($rows as $row) {
            if ($row['title'] !== 'About' || $row['summary'] !== '' || $row['content_md'] !== AboutPage::MARKDOWN || $row['status'] !== 'draft') { return null; }
        }
        return (int) $page['id'];
    }
}
