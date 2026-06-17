<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Tests\Support;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;

/**
 * Hand-written fake connection that records every SELECT it is asked to run and
 * returns a pre-seeded aggregate count. Used to exercise CategoryProductRowCounter
 * without a real database.
 */
class FakeCountConnection implements ConnectionInterface
{
    /** @var list<array{sql: string, bindings: array<mixed>}> */
    public array $queries = [];

    /**
     * @param int $aggregate The COUNT(*) value the next query() call should report.
     */
    public function __construct(
        public int $aggregate = 0,
    ) {}

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
        $this->queries[] = ['sql' => $sql, 'bindings' => $bindings];

        return [['aggregate' => $this->aggregate]];
    }

    /**
     * @param array<mixed> $bindings
     */
    public function execute(
        string $sql,
        array $bindings = [],
    ): int
    {
        return 0;
    }

    public function prepare(string $sql): StatementInterface
    {
        throw new \LogicException('FakeCountConnection::prepare() is not supported.');
    }

    public function lastInsertId(): int
    {
        return 0;
    }
}
