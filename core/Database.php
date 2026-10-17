<?php
declare(strict_types=1);
namespace Core;

use PDO;
use PDOStatement;

final class Database
{
    private PDO $pdo;
    private string $driver;

    public function __construct(array $config)
    {
        $this->driver = $config['driver'];
        if (!in_array($this->driver, ['mysql', 'sqlite'], true)) {
            throw new \InvalidArgumentException('Unsupported database driver.');
        }
        foreach (['host', 'port', 'name'] as $key) {
            if (str_contains((string) ($config[$key] ?? ''), ';')) {
                throw new \InvalidArgumentException('Invalid database connection setting.');
            }
        }
        $dsn = $this->driver === 'sqlite' ? 'sqlite:' . $config['path']
            : "mysql:host={$config['host']};port={$config['port']};dbname={$config['name']};charset=utf8mb4";
        $this->pdo = new PDO($dsn, $config['user'] ?? null, $config['password'] ?? null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        if ($this->driver === 'sqlite') {
            $this->pdo->exec('PRAGMA foreign_keys = ON');
            $this->pdo->exec('PRAGMA journal_mode = WAL');
            $this->pdo->exec('PRAGMA busy_timeout = 5000');
        } else {
            $this->pdo->exec("SET time_zone = '+00:00'");
        }
    }

    public function driver(): string { return $this->driver; }
    public function now(): string { return $this->driver === 'mysql' ? 'UTC_TIMESTAMP()' : "datetime('now')"; }

    public function query(string $sql, array $params = []): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return $statement;
    }

    public function one(string $sql, array $params = []): ?array
    {
        return $this->query($sql, $params)->fetch() ?: null;
    }

    public function all(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function insert(string $sql, array $params = []): int
    {
        $this->query($sql, $params);
        return (int) $this->pdo->lastInsertId();
    }

    public function transaction(callable $callback): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $callback($this);
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    /** Identifiers must be application constants, never request data. */
    private function identifier(string $value): string
    {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/iD', $value)) {
            throw new \InvalidArgumentException('Invalid SQL identifier.');
        }
        return '`' . $value . '`';
    }

    public function upsert(string $table, array $values, array $uniqueColumns): void
    {
        if (!$values || !$uniqueColumns) {
            throw new \InvalidArgumentException('Upsert needs values and their unique key.');
        }
        $columns = array_map($this->identifier(...), array_keys($values));
        $updates = array_diff(array_keys($values), $uniqueColumns);
        $sql = 'INSERT INTO ' . $this->identifier($table) . ' (' . implode(', ', $columns)
            . ') VALUES (' . implode(', ', array_fill(0, count($values), '?')) . ')';
        if ($this->driver === 'mysql') {
            $updates = $updates ?: $uniqueColumns;
            $sql .= ' ON DUPLICATE KEY UPDATE ' . implode(', ', array_map(fn ($c) => $this->identifier($c) . '=VALUES(' . $this->identifier($c) . ')', $updates));
        } else {
            $sql .= ' ON CONFLICT (' . implode(', ', array_map($this->identifier(...), $uniqueColumns)) . ') DO ';
            $sql .= $updates ? 'UPDATE SET ' . implode(', ', array_map(fn ($c) => $this->identifier($c) . '=excluded.' . $this->identifier($c), $updates)) : 'NOTHING';
        }
        $this->query($sql, array_values($values));
    }
}
