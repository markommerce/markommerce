<?php

declare(strict_types=1);

namespace Markommerce\Indexer\Tests\Support;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;

class FakeConnection implements ConnectionInterface
{
    /** @var list<array{sql: string, bindings: array<mixed>}> */
    public array $executed = [];

    public int $affectedRows = 1;

    public function connect(): void {}

    public function disconnect(): void {}

    public function isConnected(): bool
    {
        return true;
    }

    /**
     * @param array<mixed> $bindings
     * @return array<array<string, mixed>>
     */
    public function query(
        string $sql,
        array $bindings = [],
    ): array
    {
        return [];
    }

    /**
     * @param array<mixed> $bindings
     */
    public function execute(
        string $sql,
        array $bindings = [],
    ): int
    {
        $this->executed[] = ['sql' => $sql, 'bindings' => $bindings];

        return $this->affectedRows;
    }

    public function prepare(string $sql): StatementInterface
    {
        return new FakeStatement();
    }

    public function lastInsertId(): int
    {
        return 1;
    }
}
