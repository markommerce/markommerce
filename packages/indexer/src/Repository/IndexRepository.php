<?php

declare(strict_types=1);

namespace Markommerce\Indexer\Repository;

use InvalidArgumentException;
use Marko\Database\Connection\ConnectionInterface;
use Markommerce\Indexer\Contracts\IndexRepositoryInterface;

class IndexRepository implements IndexRepositoryInterface
{
    private const string IDENTIFIER_PATTERN = '/^[a-zA-Z_][a-zA-Z0-9_]*$/';

    public function __construct(private ConnectionInterface $connection) {}

    /**
     * @param list<string> $columns
     * @param list<array<mixed>> $rows
     */
    public function insertRows(
        string $table,
        array $columns,
        array $rows,
    ): int
    {
        $this->validateIdentifier($table);
        foreach ($columns as $col) {
            $this->validateIdentifier($col);
        }

        if ($rows === []) {
            return 0;
        }

        $columnList = implode(', ', array_map(fn (string $c) => "\"$c\"", $columns));
        $rowPlaceholders = [];
        $bindings = [];

        foreach ($rows as $row) {
            $placeholders = implode(', ', array_fill(0, count($row), '?'));
            $rowPlaceholders[] = "($placeholders)";
            foreach ($row as $value) {
                $bindings[] = $value;
            }
        }

        $sql = sprintf(
            'INSERT INTO "%s" (%s) VALUES %s',
            $table,
            $columnList,
            implode(', ', $rowPlaceholders),
        );

        $this->connection->execute($sql, $bindings);

        return count($rows);
    }

    /**
     * @param list<int> $ids
     */
    public function deleteByEntityIds(
        string $table,
        string $idColumn,
        array $ids,
    ): void
    {
        $this->validateIdentifier($table);
        $this->validateIdentifier($idColumn);

        if ($ids === []) {
            return;
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $sql = sprintf('DELETE FROM "%s" WHERE "%s" IN (%s)', $table, $idColumn, $placeholders);

        $this->connection->execute($sql, $ids);
    }

    public function truncate(string $table): void
    {
        $this->validateIdentifier($table);
        $this->connection->execute(sprintf('TRUNCATE TABLE "%s"', $table));
    }

    private function validateIdentifier(string $identifier): void
    {
        if (preg_match(self::IDENTIFIER_PATTERN, $identifier) !== 1) {
            throw new InvalidArgumentException(
                "Invalid SQL identifier: '$identifier'. Identifiers must match /^[a-zA-Z_][a-zA-Z0-9_]*$/.",
            );
        }
    }
}
