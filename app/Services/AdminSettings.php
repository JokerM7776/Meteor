<?php
declare(strict_types=1);
namespace App\Services;
use Core\Database;

final class AdminSettings
{
    /** Site text rows: key => [label, hint, max length, multi-line]. */
    public const TEXT = [
        'site_title' => ['Site title', 'The name of your website.', 191, false],
        'site_tagline' => ['Tagline', 'Optional. Shown below the title in the footer.', 191, false],
        'home_intro' => ['Home intro', 'A short introduction on the home page.', 2000, true],
        'footer_text' => ['Footer text', 'The paragraph below the footer brand.', 2000, true],
        'copyright' => ['Copyright', '{year} becomes the current year.', 255, false],
        'language_label' => ['Switcher label', 'Short name in the language switcher.', 191, false],
    ];
    public function __construct(private Database $db) {}

    /** Only the sections and cells present in $input are changed. */
    public function save(array $input, ?array $upload = null): void
    {
        $locales = array_column($this->db->all('SELECT code FROM locales'), 'code');
        if (stripos((string) ($input['custom_css'] ?? ''), '</style') !== false) { throw new \InvalidArgumentException('Custom CSS must not contain a closing style tag.'); }
        $text = is_array($input['text'] ?? null) ? $input['text'] : [];
        foreach ($text as $key => $values) {
            if (!isset(self::TEXT[$key]) || !is_array($values)) { throw new \InvalidArgumentException('Unknown site text.'); }
            foreach ($values as $code => $value) {
                if (!in_array((string) $code, $locales, true)) { throw new \InvalidArgumentException('Unknown language.'); }
                $value = trim(is_string($value) ? $value : '');
                if (!mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > self::TEXT[$key][2]) {
                    throw new \InvalidArgumentException(self::TEXT[$key][0] . ' (' . $code . '): use up to ' . self::TEXT[$key][2] . ' characters.');
                }
            }
        }
        $globals = array_column($this->db->all('SELECT `key`, value FROM settings WHERE locale_scope = ?', ['']), 'value', 'key');
        $icon = SiteIcon::upload($upload);
        try {
            $this->db->transaction(function () use ($input, $text, $icon) {
                if ($icon !== null || !empty($input['remove_site_icon'])) {
                    $this->db->query('DELETE FROM settings WHERE `key` = ? AND locale_scope = ?', ['site_icon', '']);
                    if ($icon !== null) { $this->db->upsert('settings', ['key' => 'site_icon', 'locale' => null, 'value' => $icon], ['key', 'locale_scope']); }
                }
                foreach ($text as $key => $values) {
                    foreach ($values as $code => $value) {
                        $value = trim((string) $value);
                        $this->db->query('DELETE FROM settings WHERE `key` = ? AND locale_scope = ?', [$key, (string) $code]);
                        if ($value !== '') { $this->db->upsert('settings', ['key' => $key, 'locale' => (string) $code, 'value' => $value], ['key', 'locale_scope']); }
                    }
                }
                if (array_key_exists('custom_css', $input)) {
                    $this->db->upsert('settings', ['key' => 'custom_css', 'locale' => null, 'value' => (string) $input['custom_css']], ['key', 'locale_scope']);
                }
            });
        } catch (\Throwable $error) { if ($icon !== null) { SiteIcon::remove($icon); } throw $error; }
        if ($icon !== null || !empty($input['remove_site_icon'])) { SiteIcon::remove($globals['site_icon'] ?? ''); }
    }
}
