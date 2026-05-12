<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Tests\Support;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Connection\TransactionInterface;
use RuntimeException;
use Throwable;

class FakeConnection implements ConnectionInterface, TransactionInterface
{
    /** @var array<array<string, mixed>> */
    public array $nextQueryResult = [];

    /** @var array<array{sql: string, bindings: array<mixed>}> */
    public array $executedQueries = [];

    /** @var array<string> */
    public array $transactionLog = [];

    public int $executeReturnValue = 1;

    public ?Throwable $throwOnExecute = null;

    private bool $inTx = false;

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
    public function query(string $sql, array $bindings = []): array
    {
        return $this->nextQueryResult;
    }

    /**
     * @param array<mixed> $bindings
     * @throws Throwable
     */
    public function execute(string $sql, array $bindings = []): int
    {
        if ($this->throwOnExecute !== null) {
            throw $this->throwOnExecute;
        }

        $this->executedQueries[] = ['sql' => $sql, 'bindings' => $bindings];

        return $this->executeReturnValue;
    }

    public function prepare(string $sql): StatementInterface
    {
        throw new RuntimeException('Not implemented');
    }

    public function lastInsertId(): int
    {
        return 1;
    }

    public function beginTransaction(): void
    {
        $this->inTx = true;
        $this->transactionLog[] = 'begin';
    }

    public function commit(): void
    {
        $this->inTx = false;
        $this->transactionLog[] = 'commit';
    }

    public function rollback(): void
    {
        $this->inTx = false;
        $this->transactionLog[] = 'rollback';
    }

    public function inTransaction(): bool
    {
        return $this->inTx;
    }

    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);
            $this->commit();

            return $result;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }
}
