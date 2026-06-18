<?php

declare(strict_types=1);

use Marko\Database\Repository\RepositoryQueryBuilder;
use Markommerce\Catalog\Pagination\CategoryProductRowCounter;
use Markommerce\Catalog\Tests\Support\FakeCountConnection;

/**
 * Build a RepositoryQueryBuilder test double whose compileSubquery() returns a
 * fixed SQL string and appends the given bindings. An empty constructor skips
 * the parent's required dependencies (compileSubquery is the only method used).
 *
 * @param array<mixed> $bindings
 */
function makeStubQueryBuilder(string $subquerySql, array $bindings = []): RepositoryQueryBuilder
{
    return new class ($subquerySql, $bindings) extends RepositoryQueryBuilder
    {
        /** @param array<mixed> $stubBindings */
        public function __construct(
            private readonly string $subquerySql,
            private readonly array $stubBindings,
        ) {}

        public function compileSubquery(array &$bindings): string
        {
            foreach ($this->stubBindings as $binding) {
                $bindings[] = $binding;
            }

            return $this->subquerySql;
        }
    };
}

it('wraps the compiled subquery in a COUNT(*) aggregate query', function (): void {
    $connection = new FakeCountConnection(aggregate: 7);
    $counter = new CategoryProductRowCounter($connection);

    $query = makeStubQueryBuilder('SELECT id FROM catalog_products WHERE x = ?', [42]);

    $count = $counter->count($query);

    expect($count)->toBe(7)
        ->and($connection->queries)->toHaveCount(1);

    $ran = $connection->queries[0];
    expect($ran['sql'])->toBe(
        'SELECT COUNT(*) AS aggregate FROM (SELECT id FROM catalog_products WHERE x = ?) AS category_products_count',
    )->and($ran['bindings'])->toBe([42]);
});

it('forwards the subquery bindings to the connection', function (): void {
    $connection = new FakeCountConnection(aggregate: 3);
    $counter = new CategoryProductRowCounter($connection);

    $query = makeStubQueryBuilder('SELECT 1 WHERE a = ? AND b = ?', ['red', 99]);

    $counter->count($query);

    expect($connection->queries[0]['bindings'])->toBe(['red', 99]);
});

it('returns the integer aggregate value reported by the connection', function (): void {
    $connection = new FakeCountConnection(aggregate: 4);
    $counter = new CategoryProductRowCounter($connection);

    $count = $counter->count(makeStubQueryBuilder('SELECT id FROM catalog_products'));

    expect($count)->toBe(4)->toBeInt();
});

it('defaults to zero when the connection returns no aggregate row', function (): void {
    $connection = new class () extends FakeCountConnection
    {
        /**
         * @param array<mixed> $bindings
         * @return array<array<string, mixed>>
         */
        public function query(
            string $sql,
            array $bindings = [],
        ): array {
            $this->queries[] = ['sql' => $sql, 'bindings' => $bindings];

            return [];
        }
    };

    $counter = new CategoryProductRowCounter($connection);

    expect($counter->count(makeStubQueryBuilder('SELECT id FROM catalog_products')))->toBe(0);
});
