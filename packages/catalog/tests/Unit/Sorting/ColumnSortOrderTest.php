<?php

declare(strict_types=1);

use Marko\Database\Repository\RepositoryQueryBuilder;
use Markommerce\Catalog\Sorting\ColumnSortOrder;
use Markommerce\Criteria\Sort\SortDirection;
use Markommerce\Criteria\Sort\SortField;

// ─── Fakes ────────────────────────────────────────────────────────────────────

class SpyQueryBuilder extends RepositoryQueryBuilder
{
    public int $joinCallCount = 0;

    public function __construct()
    {
        // Skip parent constructor — no DB needed in unit tests.
    }

    public function join(
        string $table,
        string $first,
        string $operator,
        string $second,
    ): static {
        $this->joinCallCount++;

        return $this;
    }

    public function leftJoin(
        string $table,
        string $first,
        string $operator,
        string $second,
    ): static {
        $this->joinCallCount++;

        return $this;
    }

    public function rightJoin(
        string $table,
        string $first,
        string $operator,
        string $second,
    ): static {
        $this->joinCallCount++;

        return $this;
    }
}

it('exposes its configured key and label', function (): void {
    $order = new ColumnSortOrder(
        key: 'position',
        label: 'Position',
        column: 'catalog_product_category.position',
        direction: SortDirection::Ascending,
        supportsKeyset: false,
    );

    expect($order->key())->toBe('position')
        ->and($order->label())->toBe('Position');
});

it('returns a single sort field for its configured column and direction', function (): void {
    $order = new ColumnSortOrder(
        key: 'position',
        label: 'Position',
        column: 'catalog_product_category.position',
        direction: SortDirection::Ascending,
        supportsKeyset: false,
    );

    $fields = $order->sortFields();

    expect($fields)->toHaveCount(1)
        ->and($fields[0])->toBeInstanceOf(SortField::class)
        ->and($fields[0]->column)->toBe('catalog_product_category.position')
        ->and($fields[0]->direction)->toBe(SortDirection::Ascending);
});

it('reports its configured keyset support', function (): void {
    $nonKeyset = new ColumnSortOrder(
        key: 'position',
        label: 'Position',
        column: 'catalog_product_category.position',
        direction: SortDirection::Ascending,
        supportsKeyset: false,
    );

    $withKeyset = new ColumnSortOrder(
        key: 'name_asc',
        label: 'Name A-Z',
        column: 'products.name',
        direction: SortDirection::Ascending,
        supportsKeyset: true,
    );

    expect($nonKeyset->supportsKeyset())->toBeFalse()
        ->and($withKeyset->supportsKeyset())->toBeTrue();
});

it('adds no joins to the query for a plain column order', function (): void {
    $order = new ColumnSortOrder(
        key: 'position',
        label: 'Position',
        column: 'catalog_product_category.position',
        direction: SortDirection::Ascending,
        supportsKeyset: false,
    );

    $spy = new SpyQueryBuilder();
    $order->prepareQuery($spy);

    expect($spy->joinCallCount)->toBe(0);
});
