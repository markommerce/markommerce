<?php

declare(strict_types=1);

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\SchemaBuilder;
use Marko\Database\Schema\SchemaRegistry;
use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Repositories\ProductRepository;
use Markommerce\CatalogScope\Entity\CategoryScopedOverrides;
use Markommerce\CatalogScope\Entity\ProductScopedOverrides;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * @param array<array{sql: string, bindings: array<mixed>}> $sqlLog
 */
function makeScopedLoggingConnection(array &$sqlLog): ConnectionInterface
{
    return new class ($sqlLog) implements ConnectionInterface
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

// ---------------------------------------------------------------------------
// Tests: Requirements 5–6 (linkExtendersFrom)
// ---------------------------------------------------------------------------

it(
    'links ProductScopedOverrides as an extender of Product after EntityMetadataFactory::linkExtendersFrom runs against the discovered entity list',
    function (): void {
        $factory = new EntityMetadataFactory();
        $factory->linkExtendersFrom([
            Product::class,
            ProductScopedOverrides::class,
        ]);
    
        $metadata = $factory->parse(Product::class);
    
        expect($metadata->extenders)->toContain(ProductScopedOverrides::class);
    }
);

it(
    'links CategoryScopedOverrides as an extender of Category after EntityMetadataFactory::linkExtendersFrom runs against the discovered entity list',
    function (): void {
        $factory = new EntityMetadataFactory();
        $factory->linkExtendersFrom([
            Category::class,
            CategoryScopedOverrides::class,
        ]);
    
        $metadata = $factory->parse(Category::class);
    
        expect($metadata->extenders)->toContain(CategoryScopedOverrides::class);
    }
);

// ---------------------------------------------------------------------------
// Test: Requirement 7 (SchemaRegistry merges scopes column)
// ---------------------------------------------------------------------------

it(
    'merges the scopes column into the catalog_products parent table when SchemaRegistry::registerEntities() runs with both Product and ProductScopedOverrides',
    function (): void {
        $factory = new EntityMetadataFactory();
        $schemaBuilder = new SchemaBuilder();
        $registry = new SchemaRegistry($factory, $schemaBuilder);
    
        $registry->registerEntities([
            Product::class,
            ProductScopedOverrides::class,
        ]);
    
        $table = $registry->getTable('catalog_products');
    
        expect($table)->not->toBeNull();
    
        $columnNames = array_map(fn ($col) => $col->name, $table->columns);
    
        expect($columnNames)->toContain('scopes');
    }
);

// ---------------------------------------------------------------------------
// Tests: Requirements 8–10 (Hydration companion attachment)
// ---------------------------------------------------------------------------

it(
    'attaches a ProductScopedOverrides companion to a hydrated Product when the SELECT row includes the scopes column',
    function (): void {
        $factory = new EntityMetadataFactory();
        $factory->linkExtenders(Product::class, [ProductScopedOverrides::class]);
        $hydrator = new EntityHydrator($factory);
    
        $row = [
            'id' => 1,
            'sku' => 'SKU-001',
            'name' => 'Test Product',
            'description' => null,
            'scopes' => '{"locale:de":{"name":"Testprodukt"}}',
        ];
    
        $metadata = $factory->parse(Product::class);
        $product = $hydrator->hydrate(Product::class, $row, $metadata);
    
        /** @var ProductScopedOverrides $companion */
        $companion = $product->companion(ProductScopedOverrides::class);
    
        expect($companion)->not->toBeNull()
            ->and($companion)->toBeInstanceOf(ProductScopedOverrides::class)
            ->and($companion->override('locale:de', 'name'))->toBe('Testprodukt');
    }
);

it(
    'silently omits the companion when a partial SELECT does not include the scopes column (documented gotcha; ScopeResolver falls back to raw value)',
    function (): void {
        $factory = new EntityMetadataFactory();
        $factory->linkExtenders(Product::class, [ProductScopedOverrides::class]);
        $hydrator = new EntityHydrator($factory);
    
        // Row without 'scopes' column — partial SELECT
    $row = [
            'id' => 1,
            'sku' => 'SKU-001',
            'name' => 'Test Product',
            'description' => null,
        ];
    
        $metadata = $factory->parse(Product::class);
        $product = $hydrator->hydrate(Product::class, $row, $metadata);
    
        $companion = $product->companion(ProductScopedOverrides::class);
    
        expect($companion)->toBeNull();
    }
);

it(
    'attaches a ProductScopedOverrides companion with scopes=null when the row\'s scopes column is NULL (no overrides set)',
    function (): void {
        $factory = new EntityMetadataFactory();
        $factory->linkExtenders(Product::class, [ProductScopedOverrides::class]);
        $hydrator = new EntityHydrator($factory);
    
        $row = [
            'id' => 1,
            'sku' => 'SKU-001',
            'name' => 'Test Product',
            'description' => null,
            'scopes' => null,
        ];
    
        $metadata = $factory->parse(Product::class);
        $product = $hydrator->hydrate(Product::class, $row, $metadata);
    
        $companion = $product->companion(ProductScopedOverrides::class);
    
        expect($companion)->not->toBeNull()
            ->and($companion)->toBeInstanceOf(ProductScopedOverrides::class)
            ->and($companion->overrides())->toBe([]);
    }
);

// ---------------------------------------------------------------------------
// Test: Requirement 11 (round-trip via catalog ProductRepository)
// ---------------------------------------------------------------------------

it(
    'round-trips a scoped override on Product through save + re-fetch via the catalog ProductRepository (which selects all columns)',
    function (): void {
        $insertedScopes = null;
        $lastId = 0;
    
        $connection = new class ($insertedScopes, $lastId) implements ConnectionInterface
        {
            public function __construct(
                private mixed &$insertedScopes,
                private int &$lastId,
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
                if (str_contains($sql, 'WHERE id = ?')) {
                    return [[
                        'id' => $this->lastId,
                        'sku' => 'SKU-001',
                        'name' => 'Shirt',
                        'description' => null,
                        'scopes' => $this->insertedScopes,
                    ]];
                }
    
                return [];
            }
    
            public function execute(
                string $sql,
                array $bindings = [],
            ): int {
                if (str_starts_with($sql, 'INSERT')) {
                    $this->lastId++;
                    $this->insertedScopes = end($bindings);
                }
    
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
        $metadataFactory->linkExtenders(Product::class, [ProductScopedOverrides::class]);
        $hydrator = new EntityHydrator($metadataFactory);
        $repository = new ProductRepository($connection, $metadataFactory, $hydrator);
    
        // Build and save a product with scoped overrides
    $product = new Product();
        $product->sku = 'SKU-001';
        $product->name = 'Shirt';
    
        $overrides = new ProductScopedOverrides();
        $overrides->setOverride('locale:de', 'name', 'Hemd');
        $product->attachCompanion($overrides);
    
        $repository->save($product);
    
        // Re-fetch via catalog ProductRepository
    /** @var Product $found */
        $found = $repository->find($product->id);
    
        /** @var ProductScopedOverrides $foundOverrides */
        $foundOverrides = $found->companion(ProductScopedOverrides::class);
    
        expect($found)->not->toBeNull()
            ->and($foundOverrides)->not->toBeNull()
            ->and($foundOverrides->override('locale:de', 'name'))->toBe('Hemd');
    }
);
