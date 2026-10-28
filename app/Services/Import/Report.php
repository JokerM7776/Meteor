<?php
declare(strict_types=1);
namespace App\Services\Import;

final class Report
{
    public array $rows = [];
    public array $warnings = [];
    public array $totals = [];
    public array $commentStatuses = ['approved' => 0, 'pending' => 0, 'spam' => 0];
    public function __construct(public bool $check)
    {
        foreach (['posts', 'pages', 'translations', 'categories', 'tags', 'images', 'comments'] as $kind) { $this->totals[$kind] = ['imported' => 0, 'skipped' => 0, 'failed' => 0]; }
    }
    public function count(string $kind, string $result = 'imported', int $number = 1): void { $this->totals[$kind][$result] += $number; }
    public function row(array $item, string $result): void
    {
        $this->rows[] = ['kind' => $item['kind'], 'title' => $item['title'], 'language' => $item['locale'], 'slug' => $item['slug'], 'result' => $result,
            'notes' => implode(' ', array_map(fn ($w) => preg_match('/[.!?]$/u', $w) ? $w : $w . '.', array_unique($item['warnings']))), 'source' => $item['source'], 'id' => $result === 'imported' ? ($item['id'] ?? null) : null];
    }
    public function failed(): bool { return in_array('failed', array_column($this->rows, 'result'), true); }
    public function totalsText(): string
    {
        $lines = [];
        foreach ($this->totals as $kind => $n) {
            $lines[] = sprintf('%-14s%d %s, %d skipped, %d failed', $kind, $n['imported'], $this->check ? 'would import' : 'imported', $n['skipped'], $n['failed']);
        }
        return implode("\n", $lines);
    }
    public function commentText(): string
    {
        $parts = [];
        foreach ($this->commentStatuses as $status => $count) { if ($count > 0) { $parts[] = $status . ' ' . $count; } }
        return implode(' · ', $parts);
    }
    public function text(): string
    {
        $lines = [$this->check ? 'Check results — nothing was imported yet' : 'Import finished', $this->totalsText()];
        if ($this->commentText() !== '') { $lines[] = 'Comments: ' . $this->commentText(); }
        foreach ($this->warnings as $warning) { $lines[] = 'Warning: ' . $warning; }
        foreach ($this->rows as $row) {
            unset($row['id']);
            $lines[] = implode(' | ', array_map(fn ($s) => preg_replace('/[\x00-\x1f\x7f]/', ' ', (string) $s), $row));
        }
        return implode("\n", $lines) . "\n";
    }
}
