<?php
declare(strict_types=1);
namespace App\Repositories;
use App\Services\SiteLinks;
use Core\Database;

/** Announcements shown in a bar above the header. An empty language list means every language. */
final class Announcements
{
    public const LIMIT = 20;

    public function __construct(private Database $db) {}

    public function all(): array
    {
        return array_map(self::decode(...), $this->db->all('SELECT * FROM announcements ORDER BY sort_order, id'));
    }

    public function find(int $id): ?array
    {
        $row = $this->db->one('SELECT * FROM announcements WHERE id = ?', [$id]);
        return $row ? self::decode($row) : null;
    }

    /** Visible announcements for one language, in order, with {locale} filled in the button URL. */
    public function live(string $locale): array
    {
        $shown = [];
        foreach ($this->db->all('SELECT * FROM announcements WHERE is_visible = 1 ORDER BY sort_order, id') as $row) {
            $row = self::decode($row);
            if ($row['locales'] !== [] && !in_array($locale, $row['locales'], true)) { continue; }
            $url = str_replace('{locale}', $locale, $row['button_url']);
            $button = $row['button_text'] !== '' && SiteLinks::validUrl($url);
            $shown[] = ['id' => $row['id'], 'title' => $row['title'], 'message' => $row['message'],
                'button_text' => $button ? $row['button_text'] : '', 'button_url' => $button ? $url : '',
                'external' => $button && preg_match('~^https?://~i', $url) === 1];
        }
        return $shown;
    }

    /** @return list<string> */
    public static function codes(string $value): array
    {
        return array_values(array_filter(explode(' ', $value), fn ($code) => $code !== ''));
    }

    private static function decode(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['is_visible'] = (int) $row['is_visible'];
        $row['locales'] = self::codes((string) $row['locale_codes']);
        return $row;
    }
}
