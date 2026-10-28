<?php
declare(strict_types=1);
namespace App\Services\Import;
use Core\Database;

final class Importer
{
    private string $public;
    private string $storage;
    public function __construct(private Database $db, ?string $public = null, ?string $storage = null)
    {
        $root = dirname(__DIR__, 3); $this->public = $public ?? $root . '/public'; $this->storage = $storage ?? $root . '/storage';
    }
    public function run(string $type, array $input, array $options = []): Report
    {
        $locales = $this->db->all('SELECT code, name, native_name, is_default FROM locales ORDER BY sort_order, code');
        $default = array_values(array_filter($locales, fn ($l) => (bool) $l['is_default']))[0]['code'] ?? 'en';
        $automatic = in_array($options['locale'] ?? 'auto', ['auto', ''], true);
        $options['locale'] = $automatic ? $default : $options['locale'];
        if (!in_array($options['locale'], array_column($locales, 'code'), true)) { throw new \InvalidArgumentException('Choose an existing target language.'); }
        if (!in_array($options['conflict'] ?? 'skip', ['skip', 'rename'], true)) { throw new \InvalidArgumentException('Choose Skip or Import with a new slug.'); }
        \Core\Dates::load($this->db);
        $source = match ($type) {
            'hexo' => new HexoSource(new Files($input['file']), array_column($locales, 'code'), $options['locale'], $automatic),
            'typecho' => new TypechoSource($input, $options['locale'], !empty($input['uploads']) ? new Files($input['uploads']) : null, $input['site'] ?? ''),
            default => throw new \InvalidArgumentException('Choose Hexo or Typecho.'),
        };
        if ($source instanceof HexoSource) { $options['locale'] = $source->target; }
        $items = $source->items(); $report = new Report(!empty($options['check'])); $report->warnings = $source->warnings;
        $language = array_column($locales, null, 'code')[$options['locale']];
        $origin = $source instanceof HexoSource ? $source->languageOrigin : ($automatic ? 'the site’s default language' : 'your choice');
        $report->warnings[] = 'Posts without a language ' . ($report->check ? 'will be imported' : 'were imported') . ' as ' . ($language['native_name'] ?: $language['name']) . ' (' . $options['locale'] . '), based on ' . $origin . '.';
        if ($source instanceof TypechoSource) { $report->count('translations', 'skipped', $source->skipped); }
        (new Writer($this->db, $report, $options, $source->image(...), $this->public, $this->storage))->run($items);
        return $report;
    }
}
