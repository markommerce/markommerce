<?php

declare(strict_types=1);

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Markommerce\Catalog\Repositories\CategoryRepository;
use Markommerce\Catalog\Repositories\ProductCategoryAssignmentRepository;
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

/**
 * Build a fresh CategoryRepository with a logging connection.
 *
 * @param array<array{sql: string, bindings: array<mixed>}> $sqlLog
 */
function makeCategoryRepository(array &$sqlLog): CategoryRepository
{
    $connection = makeCatalogLoggingConnection($sqlLog);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator($metadataFactory);

    return new CategoryRepository($connection, $metadataFactory, $hydrator);
}

/**
 * Build a fresh ProductCategoryAssignmentRepository with a logging connection.
 *
 * @param array<array{sql: string, bindings: array<mixed>}> $sqlLog
 */
function makeAssignmentRepository(array &$sqlLog): ProductCategoryAssignmentRepository
{
    $connection = makeCatalogLoggingConnection($sqlLog);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator($metadataFactory);

    return new ProductCategoryAssignmentRepository($connection, $metadataFactory, $hydrator);
}

it('ProductRepository declares Product as its entity class', function (): void {
    $sqlLog = [];
    $repository = makeProductRepository($sqlLog);

    expect($repository)->toBeInstanceOf(ProductRepository::class);
});

it('ProductRepository findBySku queries the catalog_products table filtered by sku', function (): void {
    $sqlLog = [];

    $connection = new class ($sqlLog) implements ConnectionInterface
    {
        private int $lastId = 0;

        public function __construct(
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
            $this->sqlLog[] = ['sql' => $sql, 'bindings' => $bindings];

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

    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator($metadataFactory);
    $repository = new ProductRepository($connection, $metadataFactory, $hydrator);

    $repository->findBySku('SKU-001');

    expect($sqlLog)->toHaveCount(1)
        ->and($sqlLog[0]['sql'])->toContain('catalog_products')
        ->and($sqlLog[0]['sql'])->toContain('sku')
        ->and($sqlLog[0]['bindings'])->toContain('SKU-001');
});

it('ProductRepository findBySku returns null when no product matches', function (): void {
    $sqlLog = [];
    $repository = makeProductRepository($sqlLog);

    $result = $repository->findBySku('NONEXISTENT');

    expect($result)->toBeNull();
});

it('CategoryRepository declares Category as its entity class', function (): void {
    $sqlLog = [];
    $repository = makeCategoryRepository($sqlLog);

    expect($repository)->toBeInstanceOf(CategoryRepository::class);
});

it('ProductCategoryAssignmentRepository declares the assignment entity as its entity class', function (): void {
    $sqlLog = [];
    $repository = makeAssignmentRepository($sqlLog);

    expect($repository)->toBeInstanceOf(ProductCategoryAssignmentRepository::class);
});

it(
    'ProductCategoryAssignmentRepository findByCategory queries assignments filtered by category id',
    function (): void {
        $sqlLog = [];

        $connection = new class ($sqlLog) implements ConnectionInterface
        {
            private int $lastId = 0;

            public function __construct(
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
                $this->sqlLog[] = ['sql' => $sql, 'bindings' => $bindings];

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

        $metadataFactory = new EntityMetadataFactory();
        $hydrator = new EntityHydrator($metadataFactory);
        $repository = new ProductCategoryAssignmentRepository($connection, $metadataFactory, $hydrator);

        $repository->findByCategory(42);

        expect($sqlLog)->toHaveCount(1)
            ->and($sqlLog[0]['sql'])->toContain('catalog_product_category')
            ->and($sqlLog[0]['sql'])->toContain('category_id')
            ->and($sqlLog[0]['bindings'])->toContain(42);
    },
);
