<?php

declare(strict_types=1);

namespace Markommerce\Indexer\Contracts;

use InvalidArgumentException;

interface IndexRepositoryInterface
{
    /**
     * Insert multiple rows into the given index table in a single SQL statement.
     *
     * @param string $table Validated table name
     * @param list<string> $columns Column names
     * @param list<array<mixed>> $rows Rows of values (same order as $columns)
     * @throws InvalidArgumentException if table or any column name is invalid
     */
    public function insertRows(
        string $table,
        array $columns,
        array $rows,
    ): int;

    /**
     * Delete rows from the index table where the given id column matches any of the provided ids.
     *
     * @param list<int> $ids
     * @throws InvalidArgumentException if table or column name is invalid
     */
    public function deleteByEntityIds(
        string $table,
        string $idColumn,
        array $ids,
    ): void;

    /**
     * Truncate the entire index table.
     *
     * @throws InvalidArgumentException if table name is invalid
     */
    public function truncate(string $table): void;
}
