<?php
declare(strict_types=1);
namespace App\Services;
use App\Repositories\Announcements;
use Core\Database;

final class AnnouncementEditor
{
    public function __construct(private Database $db) {}

    /** Creates ($id = 0) or updates one announcement. Returns its id. */
    public function save(int $id, array $input): int
    {
        if ($id > 0 && !$this->db->one('SELECT id FROM announcements WHERE id = ?', [$id])) {
            throw new \InvalidArgumentException('This announcement no longer exists.');
        }
        $title = $this->line($input['title'] ?? '', 120, 'Title');
        $message = str_replace(["\r\n", "\r"], "\n", trim(is_string($input['message'] ?? null) ? $input['message'] : ''));
        if ($message === '') { throw new \InvalidArgumentException('Enter a message.'); }
        if (!mb_check_encoding($message, 'UTF-8') || mb_strlen($message) > 1000 || preg_match('/[^\P{Cc}\n\t]/u', $message)) {
            throw new \InvalidArgumentException('Message: use up to 1,000 characters.');
        }
        $buttonText = $this->line($input['button_text'] ?? '', 40, 'Button text');
        $buttonUrl = trim(is_string($input['button_url'] ?? null) ? $input['button_url'] : '');
        if (($buttonText === '') !== ($buttonUrl === '')) {
            throw new \InvalidArgumentException('Button: enter both the text and the link, or leave both empty for no button.');
        }
        if ($buttonUrl !== '' && !SiteLinks::validUrl($buttonUrl)) {
            throw new \InvalidArgumentException('Button link: use a URL starting with /, #, https://, http:// or mailto:');
        }
        $codes = '';
        if (($input['scope'] ?? 'all') !== 'all') {
            $chosen = is_array($input['locales'] ?? null) ? array_map('strval', $input['locales']) : [];
            $known = array_column($this->db->all('SELECT code FROM locales ORDER BY sort_order, code'), 'code');
            if (array_diff($chosen, $known)) { throw new \InvalidArgumentException('Unknown language.'); }
            $chosen = array_values(array_intersect($known, $chosen));
            if ($chosen === []) { throw new \InvalidArgumentException('Choose at least one language, or show the announcement in all languages.'); }
            $codes = implode(' ', $chosen);
        }
        $values = [$title, $message, $buttonText, $buttonUrl, $codes, (string) ($input['visible'] ?? '1') === '0' ? 0 : 1];
        if ($id > 0) {
            $this->db->query('UPDATE announcements SET title = ?, message = ?, button_text = ?, button_url = ?, locale_codes = ?, is_visible = ?, updated_at = ' . $this->db->now() . ' WHERE id = ?', [...$values, $id]);
            return $id;
        }
        if ((int) $this->db->one('SELECT COUNT(*) AS n FROM announcements')['n'] >= Announcements::LIMIT) {
            throw new \InvalidArgumentException('Use at most ' . Announcements::LIMIT . ' announcements. Delete one you no longer need first.');
        }
        $order = (int) ($this->db->one('SELECT MAX(sort_order) AS n FROM announcements')['n'] ?? 0) + 1;
        return $this->db->insert('INSERT INTO announcements (title, message, button_text, button_url, locale_codes, is_visible, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)', [...$values, $order]);
    }

    /** Saves the list view: order and the Show checkbox of each row. */
    public function saveList(array $input): void
    {
        $items = is_array($input['items'] ?? null) ? $input['items'] : [];
        $ids = array_column($this->db->all('SELECT id FROM announcements'), 'id');
        $this->db->transaction(function () use ($items, $ids) {
            foreach ($items as $id => $data) {
                if (!is_array($data) || !in_array((int) $id, array_map('intval', $ids), true)) { throw new \InvalidArgumentException('Unknown announcement. Reload the page and try again.'); }
                if (array_key_exists('visible', $data)) { $this->db->query('UPDATE announcements SET is_visible = ? WHERE id = ?', [(string) $data['visible'] === '1' ? 1 : 0, (int) $id]); }
                if (array_key_exists('sort_order', $data)) {
                    if (!preg_match('/^\d{1,4}$/D', (string) $data['sort_order'])) { throw new \InvalidArgumentException('Order must be a whole number.'); }
                    $this->db->query('UPDATE announcements SET sort_order = ? WHERE id = ?', [(int) $data['sort_order'], (int) $id]);
                }
            }
            foreach ($this->db->all('SELECT id FROM announcements ORDER BY sort_order, id') as $index => $row) {
                $this->db->query('UPDATE announcements SET sort_order = ? WHERE id = ?', [$index + 1, $row['id']]);
            }
        });
    }

    public function delete(int $id): void
    {
        $this->db->query('DELETE FROM announcements WHERE id = ?', [$id]);
    }

    private function line(mixed $value, int $max, string $label): string
    {
        $value = trim(is_string($value) ? $value : '');
        if (!mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > $max || preg_match('/\p{Cc}/u', $value)) {
            throw new \InvalidArgumentException($label . ': use up to ' . $max . ' characters on one line.');
        }
        return $value;
    }
}
