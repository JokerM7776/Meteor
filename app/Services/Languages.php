<?php
declare(strict_types=1);
namespace App\Services;
use Core\Database;

final class Languages
{
    public function __construct(private Database $db) {}
    public function save(string $action, array $input): void
    {
        $code = $input['code'] ?? '';
        $row = $this->db->one('SELECT * FROM locales WHERE code = ?', [$code]);
        $this->db->transaction(function () use ($action, $input, $code, $row) {
            if ($action === 'default') {
                if (!$row || $row['status'] !== 'live') { throw new \InvalidArgumentException('The default language must be live.'); }
                $this->db->query('UPDATE locales SET is_default = 0');
                $this->db->query('UPDATE locales SET is_default = 1 WHERE code = ?', [$code]);
                $this->db->upsert('settings', ['key' => 'match_browser_language', 'locale' => null, 'value' => isset($input['match_browser_language']) ? '1' : '0'], ['key', 'locale_scope']);
            } elseif ($action === 'status') {
                $status = $input['status'] ?? '';
                if (!$row || !in_array($status, ['live', 'preview', 'off'], true) || ($row['is_default'] && $status !== 'live')) { throw new \InvalidArgumentException('The default language must stay live.'); }
                $this->db->query('UPDATE locales SET status = ? WHERE code = ?', [$status, $code]);
            } elseif ($action === 'delete') {
                if (!$row || $row['is_default']) { throw new \InvalidArgumentException('The default language cannot be deleted.'); }
                foreach (['post_translations', 'page_translations', 'category_translations', 'tag_translations'] as $table) {
                    if ($this->db->one("SELECT id FROM $table WHERE locale = ?", [$code])) { throw new \InvalidArgumentException('This language has translations. Set it to Off to keep them.'); }
                }
                foreach (['posts', 'pages'] as $table) {
                    if ($this->db->one("SELECT id FROM $table WHERE base_locale = ?", [$code])) { throw new \InvalidArgumentException('This language is used as a base. Set it to Off instead.'); }
                }
                $this->db->query('DELETE FROM locales WHERE code = ?', [$code]);
            } elseif ($action === 'order') {
                $codes = array_column($this->db->all('SELECT code FROM locales'), 'code');
                $order = is_array($input['order'] ?? null) ? array_values($input['order']) : [];
                $order = array_map(fn ($code) => is_string($code) ? $code : '', $order);
                if (count($order) !== count($codes) || count(array_unique($order)) !== count($order) || array_diff($codes, $order)) {
                    throw new \InvalidArgumentException('Reload the page and try again: the language list has changed.');
                }
                foreach ($order as $i => $language) { $this->db->query('UPDATE locales SET sort_order = ? WHERE code = ?', [$i, $language]); }
            } elseif ($action === 'up' || $action === 'down') {
                $rows = $this->db->all('SELECT code FROM locales ORDER BY sort_order, code');
                $index = array_search($code, array_column($rows, 'code'), true);
                if ($index === false) { throw new \InvalidArgumentException('Unknown language.'); }
                $next = $index + ($action === 'up' ? -1 : 1);
                if (isset($rows[$next])) { [$rows[$index], $rows[$next]] = [$rows[$next], $rows[$index]]; }
                foreach ($rows as $i => $language) { $this->db->query('UPDATE locales SET sort_order = ? WHERE code = ?', [$i, $language['code']]); }
            } else {
                if (!preg_match('/^[a-z]{2,3}(-[a-z0-9]{2,8})*$/D', $code) || strlen($code) > 35) { throw new \InvalidArgumentException('Enter a valid language code, such as ja or pt-br.'); }
                $name = trim($input['name'] ?? ''); $native = trim($input['native_name'] ?? ''); $direction = $input['direction'] ?? 'ltr';
                if (!$name || !$native || mb_strlen($name) > 191 || mb_strlen($native) > 191 || !in_array($direction, ['ltr', 'rtl'], true)) { throw new \InvalidArgumentException('Enter both names (up to 191 characters) and a valid direction.'); }
                if ($action === 'add') {
                    if ($row) { throw new \InvalidArgumentException('Language code already exists.'); }
                    $max = $this->db->one('SELECT MAX(sort_order) AS n FROM locales');
                    $this->db->query('INSERT INTO locales (code, name, native_name, direction, status, sort_order) VALUES (?, ?, ?, ?, ?, ?)', [$code, $name, $native, $direction, 'preview', (int) $max['n'] + 1]);
                } else {
                    if (!$row) { throw new \InvalidArgumentException('Unknown language.'); }
                    $this->db->query('UPDATE locales SET name = ?, native_name = ?, direction = ? WHERE code = ?', [$name, $native, $direction, $code]);
                }
            }
        });
    }
}
