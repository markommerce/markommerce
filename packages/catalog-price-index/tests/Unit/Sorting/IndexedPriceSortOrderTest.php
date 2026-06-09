<?php

declare(strict_types=1);

use Marko\Database\Repository\RepositoryQueryBuilder;
use Markommerce\CatalogPriceIndex\Sorting\AscendingIndexedPriceSortOrder;
use Markommerce\CatalogPriceIndex\Sorting\DescendingIndexedPriceSortOrder;
use Markommerce\Criteria\Sort\NullsPlacement;
use Markommerce\Criteria\Sort\SortDirection;
use Markommerce\Criteria\Sort\SortField;

// ─── Fakes ────────────────────────────────────────────────────────────────────

class PriceIndexSpyQueryBuilder extends RepositoryQueryBuilder
{
    /** @var list<array{table: string, first: string, operator: string, second: string}> */
    public array $leftJoinCalls = [];

    public function __construct()
    {
        // Skip parent constructor — no DB needed in unit tests.
    }

    public function leftJoin(
        string $table,
        string $first,
        string $operator,
        string $second,
    ): static
    {
        $this->leftJoinCalls[] = compact('table', 'first', 'operator', 'second');

        return $this;
    }
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('exposes the price_asc and price_desc keys with human labels', function (): void {
    $asc  = new AscendingIndexedPriceSortOrder();
    $desc = new DescendingIndexedPriceSortOrder();

    expect($asc->key())->toBe('price_asc')
        ->and($asc->label())->toBe('Price: Low to High')
        ->and($desc->key())->toBe('price_desc')
        ->and($desc->label())->toBe('Price: High to Low');
});

it('left joins the price index table when preparing the query', function (): void {
    $asc = new AscendingIndexedPriceSortOrder();
    $spy = new PriceIndexSpyQueryBuilder();

    $asc->prepareQuery($spy);

    expect($spy->leftJoinCalls)->toHaveCount(1)
        ->and($spy->leftJoinCalls[0]['table'])->toBe('catalog_product_price_index')
        ->and($spy->leftJoinCalls[0]['first'])->toBe('catalog_products.id')
        ->and($spy->leftJoinCalls[0]['operator'])->toBe('=')
        ->and($spy->leftJoinCalls[0]['second'])->toBe('catalog_product_price_index.product_id');
});

it('sorts by the indexed amount column in the configured direction', function (): void {
    $asc  = new AscendingIndexedPriceSortOrder();
    $desc = new DescendingIndexedPriceSortOrder();

    $ascFields  = $asc->sortFields();
    $descFields = $desc->sortFields();

    expect($ascFields)->toHaveCount(1)
        ->and($ascFields[0])->toBeInstanceOf(SortField::class)
        ->and($ascFields[0]->column)->toBe('catalog_product_price_index.amount')
        ->and($ascFields[0]->direction)->toBe(SortDirection::Ascending)
        ->and($ascFields[0]->nulls)->toBe(NullsPlacement::Last);

    expect($descFields)->toHaveCount(1)
        ->and($descFields[0])->toBeInstanceOf(SortField::class)
        ->and($descFields[0]->column)->toBe('catalog_product_price_index.amount')
        ->and($descFields[0]->direction)->toBe(SortDirection::Descending)
        ->and($descFields[0]->nulls)->toBe(NullsPlacement::Last);
});

it('reports that it does not support keyset pagination', function (): void {
    $asc  = new AscendingIndexedPriceSortOrder();
    $desc = new DescendingIndexedPriceSortOrder();

    expect($asc->supportsKeyset())->toBeFalse()
        ->and($desc->supportsKeyset())->toBeFalse();
});
