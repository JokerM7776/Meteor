<?php
declare(strict_types=1);
namespace App\Services;
use Core\{Database, Markdown};

/** The About page: an ordinary page with the slug "about", created as a draft so it can be written first. */
final class AboutPage
{
    public const MARKDOWN = "Write a few words about yourself and this site here.\n\nEdit this page in Admin → Pages, then publish it to show it in the menus.";

    /** The About page id; null when the site has no default language yet. */
    public static function ensure(Database $db): ?int
    {
        $page = $db->one("SELECT id FROM pages WHERE slug = 'about'");
        if ($page) { return (int) $page['id']; }
        $locale = $db->one('SELECT code FROM locales WHERE is_default = 1')['code'] ?? null;
        if ($locale === null) { return null; }
        $id = $db->insert('INSERT INTO pages (slug, base_locale, is_published, show_in_nav) VALUES (?, ?, ?, ?)', ['about', $locale, 1, 0]);
        $compiled = (new Markdown())->compile(self::MARKDOWN);
        $db->query('INSERT INTO page_translations (page_id, locale, title, summary, content_md, content_html, toc_html, status, reading_minutes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, $locale, 'About', '', self::MARKDOWN, $compiled['html'], $compiled['toc_html'], 'draft', $compiled['reading_minutes']]);
        return $id;
    }
}
