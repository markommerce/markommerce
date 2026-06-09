<?php

declare(strict_types=1);

namespace Markommerce\Testing\Tests\Fixture\Container;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;

/**
 * A no-op ConnectionInterface implementation for unit tests that need a
 * ConnectionInterface instance but do not perform any database operations.
 */
class NullConnection implements ConnectionInterface
{
    public function connect(): void {}

    public function disconnect(): void {}

    public function isConnected(): bool
    {
        return true;
    }

    public function query(string $sql, array $bindings = []): array
    {
        return [];
    }

    public function execute(string $sql, array $bindings = []): int
    {
        return 0;
    }

    public function prepare(string $sql): StatementInterface
    {
        return new class implements StatementInterface {
            public function execute(array $bindings = []): bool { return true; }
            public function fetchAll(): array { return []; }
            public function fetch(): ?array { return null; }
            public function rowCount(): int { return 0; }
        };
    }

    public function lastInsertId(): int
    {
        return 0;
    }
}
