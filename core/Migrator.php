<?php
declare(strict_types=1);
namespace Core;
final class Migrator
{
    public function __construct(private Database $db, private string $directory) {}
    private function files(): array
    {
        $this->db->query('CREATE TABLE IF NOT EXISTS schema_migrations (name VARCHAR(191) PRIMARY KEY, applied_at VARCHAR(30) NOT NULL)');
        $files = glob($this->directory . '/[0-9][0-9][0-9]_*.php'); sort($files, SORT_STRING);
        return $files;
    }
    public function markAll(): void
    {
        foreach ($this->files() as $file) { $this->record(basename($file)); }
    }
    private function record(string $name): void
    {
        $this->db->upsert('schema_migrations', ['name' => $name, 'applied_at' => gmdate('c')], ['name']);
    }
    public function run(): array
    {
        $done = [];
        foreach ($this->files() as $file) {
            $name = basename($file);
            if ($this->db->one('SELECT name FROM schema_migrations WHERE name = ?', [$name])) { continue; }
            $apply = function () use ($file, $name): void {
                $migration = require $file;
                if (!is_callable($migration)) { throw new \RuntimeException('Invalid migration: ' . $name); }
                $migration($this->db); $this->record($name);
            };
            // MySQL DDL implicitly commits. Migrations must be safe to retry after a partial failure.
            if ($this->db->driver() === 'sqlite') { $this->db->transaction($apply); } else { $apply(); }
            $done[] = $name;
        }
        return $done;
    }
}
