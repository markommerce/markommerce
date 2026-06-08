<?php

declare(strict_types=1);

namespace Markommerce\CatalogPriceIndex\Tests\Unit\Repositories;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Markommerce\CatalogPriceIndex\Entity\ProductPriceIndexEntry;
use Markommerce\CatalogPriceIndex\Repositories\ProductPriceIndexRepository;
use RuntimeException;

// ─── Spy Connection ───────────────────────────────────────────────────────────

class SpyConnection implements ConnectionInterface
{
    /** @var array<array{sql: string, bindings: array<mixed>}> */
    public array $executed = [];

    /** @var array<array{sql: string, bindings: array<mixed>}> */
    public array $queried = [];

    /** @var list<array<string, mixed>> */
    public array $queryResults = [];

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
        $this->queried[] = ['sql' => $sql, 'bindings' => $bindings];

        return array_shift($this->queryResults) ?? [];
    }

    public function execute(
        string $sql,
        array $bindings = [],
    ): int {
        $this->executed[] = ['sql' => $sql, 'bindings' => $bindings];

        return count($bindings);
    }

    public function prepare(string $sql): StatementInterface
    {
        throw new RuntimeException('Not implemented');
    }

    public function lastInsertId(): int
    {
        return 1;
    }
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

function makeEntry(int $productId, ?string $amount = null, string $currencyCode = 'USD', ?array $scopes = null): ProductPriceIndexEntry
{
    $entry = new ProductPriceIndexEntry();
    $entry->productId = $productId;
    $entry->amount = $amount;
    $entry->currencyCode = $currencyCode;
    $entry->scopes = $scopes;

    return $entry;
}

function makeRepository(SpyConnection $conn): ProductPriceIndexRepository
{
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator($metadataFactory);

    return new ProductPriceIndexRepository($conn, $metadataFactory, $hydrator);
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('removes all entries on truncate', function (): void {
    $conn = new SpyConnection();
    $repo = makeRepository($conn);

    $repo->truncate();

    expect($conn->executed)->toHaveCount(1);

    $stmt = $conn->executed[0];

    expect($stmt['sql'])
        ->toContain('TRUNCATE')
        ->toContain('catalog_product_price_index');
});

it('returns null when no entry exists for a product id', function (): void {
    $conn = new SpyConnection();
    // queryResults is empty — simulates no rows found
    $repo = makeRepository($conn);

    $result = $repo->findByProductId(999);

    expect($result)->toBeNull();
});

it('finds an index entry by product id', function (): void {
    $conn = new SpyConnection();
    // Pre-load query results for the findOneBy call
    $conn->queryResults = [
        [
            ['id' => 1, 'product_id' => 7, 'amount' => '12.50', 'currency_code' => 'USD', 'scopes' => null],
        ],
    ];

    $repo = makeRepository($conn);

    $found = $repo->findByProductId(7);

    expect($found)->not->toBeNull()
        ->and($found->productId)->toBe(7)
        ->and($found->amount)->toBe('12.50')
        ->and($found->currencyCode)->toBe('USD');
});

it('does nothing when upserting an empty list', function (): void {
    $conn = new SpyConnection();
    $repo = makeRepository($conn);

    $repo->upsertMany([]);

    expect($conn->executed)->toHaveCount(0);
});

it('persists per market amounts in the scopes json', function (): void {
    $conn = new SpyConnection();
    $repo = makeRepository($conn);

    $scopes = ['market:us' => ['amount' => '29.99'], 'market:eu' => ['amount' => '24.99']];
    $entry = makeEntry(5, '19.99', 'USD', $scopes);

    $repo->upsertMany([$entry]);

    expect($conn->executed)->toHaveCount(1);

    $stmt = $conn->executed[0];

    // The SQL must use the ::jsonb cast for scopes
    expect($stmt['sql'])->toContain('?::jsonb');

    // The scopes JSON must be in the bindings
    $jsonEncoded = json_encode($scopes);
    expect($stmt['bindings'])->toContain($jsonEncoded);
});

it('updates existing entries on product id conflict', function (): void {
    $conn = new SpyConnection();
    $repo = makeRepository($conn);

    $entry = makeEntry(1, '10.00', 'USD');
    $repo->upsertMany([$entry]);

    $updatedEntry = makeEntry(1, '15.00', 'EUR');
    $repo->upsertMany([$updatedEntry]);

    // Two separate upsert calls, each is a single statement
    expect($conn->executed)->toHaveCount(2);

    $secondStmt = $conn->executed[1];

    expect($secondStmt['sql'])
        ->toContain('ON CONFLICT (product_id) DO UPDATE SET')
        ->toContain('amount = EXCLUDED.amount')
        ->toContain('currency_code = EXCLUDED.currency_code');

    // Verify the second upsert bindings contain the updated values
    expect($secondStmt['bindings'])->toContain(1)    // product_id
        ->toContain('15.00')                          // updated amount
        ->toContain('EUR');                           // updated currency
});

it('inserts new index entries in a single bulk statement', function (): void {
    $conn = new SpyConnection();
    $repo = makeRepository($conn);

    $entries = [
        makeEntry(1, '10.00', 'USD'),
        makeEntry(2, '20.00', 'EUR'),
        makeEntry(3, '30.00', 'GBP'),
    ];

    $repo->upsertMany($entries);

    expect($conn->executed)->toHaveCount(1);

    $stmt = $conn->executed[0];

    expect($stmt['sql'])
        ->toContain('INSERT INTO')
        ->toContain('catalog_product_price_index')
        ->toContain('ON CONFLICT (product_id) DO UPDATE SET');
});
