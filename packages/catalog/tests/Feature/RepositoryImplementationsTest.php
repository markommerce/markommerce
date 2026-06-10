<?php

declare(strict_types=1);

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Markommerce\Catalog\Repositories\ProductRepository;

/**
 * Create a logging connection that records executed SQL and bindings.
 *
 * @param array<array{sql: string, bindings: array<mixed>}> $sqlLog
 */
function makeCatalogLoggingConnection(array &$sqlLog): ConnectionInterface
{
    return new class ($sqlLog) implements ConnectionInterface
    {
        private int $lastId = 0;

        public function __construct(
            /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
            private array &$sqlLog,
        ) {}

        public function connect(): void {}

        public function disconnect(): void {}

        public function isConnected(): bool
        {
            return true;
        }

        public function query(
            string $sql,
            array $bindings = [],
        ): array {
            return [];
        }

        public function execute(
            string $sql,
            array $bindings = [],
        ): int {
            $this->sqlLog[] = ['sql' => $sql, 'bindings' => $bindings];
            $this->lastId++;

            return 1;
        }

        public function prepare(string $sql): StatementInterface
        {
            throw new RuntimeException('Not implemented');
        }

        public function lastInsertId(): int
        {
            return $this->lastId;
        }
    };
}

/**
 * Build a fresh ProductRepository with a logging connection.
 *
 * @param array<array{sql: string, bindings: array<mixed>}> $sqlLog
 */
function makeProductRepository(array &$sqlLog): ProductRepository
{
    $connection = makeCatalogLoggingConnection($sqlLog);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator($metadataFactory);

    return new ProductRepository($connection, $metadataFactory, $hydrator);
}

it('ProductRepository findBySku returns null when no product matches', function (): void {
    $sqlLog = [];
    $repository = makeProductRepository($sqlLog);

    $result = $repository->findBySku('NONEXISTENT');

    expect($result)->toBeNull();
});
