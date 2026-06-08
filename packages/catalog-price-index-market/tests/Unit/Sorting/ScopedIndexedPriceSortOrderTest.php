<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Core\Attributes\Preference;
use Marko\Database\Repository\RepositoryQueryBuilder;
use Markommerce\CatalogPriceIndex\Sorting\AscendingIndexedPriceSortOrder;
use Markommerce\CatalogPriceIndex\Sorting\DescendingIndexedPriceSortOrder;
use Markommerce\CatalogPriceIndexMarket\Sorting\ScopedAscendingIndexedPriceSortOrder;
use Markommerce\CatalogPriceIndexMarket\Sorting\ScopedDescendingIndexedPriceSortOrder;
use Markommerce\Criteria\Sort\NullsPlacement;
use Markommerce\Criteria\Sort\SortDirection;
use Markommerce\Criteria\Sort\SortField;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Registry\PhpScopeRegistry;
use Markommerce\Scope\Registry\ScopeRegistryInterface;

// ─── Fakes ────────────────────────────────────────────────────────────────────

class ScopedPriceSpyQueryBuilder extends RepositoryQueryBuilder
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
    ): static {
        $this->leftJoinCalls[] = compact('table', 'first', 'operator', 'second');

        return $this;
    }
}

function makeScopeRegistryWithMarket(string $activeMarket = 'us'): ScopeRegistryInterface
{
    $config = new ConfigRepository([
        'scope' => [
            'axes' => [
                'market' => [
                    'default' => 'default',
                    'scopes'  => [
                        'default' => [],
                        'us'      => [],
                        'eu'      => [],
                    ],
                ],
            ],
        ],
    ]);

    return new PhpScopeRegistry($config);
}

function makeScopeRegistryWithoutMarket(): ScopeRegistryInterface
{
    $config = new ConfigRepository([
        'scope' => [
            'axes' => [],
        ],
    ]);

    return new PhpScopeRegistry($config);
}

function makeScopeContextWithMarket(ScopeRegistryInterface $registry, string $market): ScopeContext
{
    $context = new ScopeContext($registry);
    $context->in('market', $market);

    return $context;
}

function makeScopeContextWithoutMarket(ScopeRegistryInterface $registry): ScopeContext
{
    return new ScopeContext($registry);
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('replaces both base indexed price sort orders via preferences', function (): void {
    $ascReflection  = new ReflectionClass(ScopedAscendingIndexedPriceSortOrder::class);
    $descReflection = new ReflectionClass(ScopedDescendingIndexedPriceSortOrder::class);

    $ascAttributes  = $ascReflection->getAttributes(Preference::class);
    $descAttributes = $descReflection->getAttributes(Preference::class);

    expect($ascAttributes)->toHaveCount(1);
    expect($ascAttributes[0]->newInstance()->replaces)->toBe(AscendingIndexedPriceSortOrder::class);

    expect($descAttributes)->toHaveCount(1);
    expect($descAttributes[0]->newInstance()->replaces)->toBe(DescendingIndexedPriceSortOrder::class);
});

it('casts the json override amount to numeric so 100 sorts after 9', function (): void {
    $registry = makeScopeRegistryWithMarket();
    $context  = makeScopeContextWithMarket($registry, 'us');
    $sortOrder = new ScopedAscendingIndexedPriceSortOrder($registry, $context);

    $fields     = $sortOrder->sortFields();
    $expression = $fields[0]->sortExpression();

    expect($expression)->toContain('::numeric');
});

it('orders by the active market override amount when present', function (): void {
    $registry  = makeScopeRegistryWithMarket();
    $context   = makeScopeContextWithMarket($registry, 'us');
    $sortOrder = new ScopedAscendingIndexedPriceSortOrder($registry, $context);

    $fields     = $sortOrder->sortFields();

    expect($fields)->toHaveCount(1);
    expect($fields[0])->toBeInstanceOf(SortField::class);
    expect($fields[0]->expression)->toContain("'market:us'");
    expect($fields[0]->expression)->toContain('COALESCE');
    expect($fields[0]->column)->toBe('catalog_product_price_index.amount');
    expect($fields[0]->direction)->toBe(SortDirection::Ascending);
    expect($fields[0]->nulls)->toBe(NullsPlacement::Last);
});

it('falls back to the base amount when the active market has no override', function (): void {
    $registry  = makeScopeRegistryWithMarket();
    $context   = makeScopeContextWithoutMarket($registry);
    $sortOrder = new ScopedAscendingIndexedPriceSortOrder($registry, $context);

    $fields = $sortOrder->sortFields();

    expect($fields)->toHaveCount(1);
    expect($fields[0])->toBeInstanceOf(SortField::class);
    expect($fields[0]->expression)->toBeNull();
    expect($fields[0]->column)->toBe('catalog_product_price_index.amount');
    expect($fields[0]->direction)->toBe(SortDirection::Ascending);
    expect($fields[0]->nulls)->toBe(NullsPlacement::Last);
});

it('still places products with no price last', function (): void {
    $registry  = makeScopeRegistryWithMarket();
    $context   = makeScopeContextWithMarket($registry, 'us');
    $sortOrder = new ScopedAscendingIndexedPriceSortOrder($registry, $context);

    $fields = $sortOrder->sortFields();

    expect($fields[0]->nulls)->toBe(NullsPlacement::Last);
});

it('keeps the price index left join in the prepared query', function (): void {
    $registry  = makeScopeRegistryWithMarket();
    $context   = makeScopeContextWithMarket($registry, 'us');
    $sortOrder = new ScopedAscendingIndexedPriceSortOrder($registry, $context);
    $spy       = new ScopedPriceSpyQueryBuilder();

    $sortOrder->prepareQuery($spy);

    expect($spy->leftJoinCalls)->toHaveCount(1)
        ->and($spy->leftJoinCalls[0]['table'])->toBe('catalog_product_price_index')
        ->and($spy->leftJoinCalls[0]['first'])->toBe('catalog_products.id')
        ->and($spy->leftJoinCalls[0]['operator'])->toBe('=')
        ->and($spy->leftJoinCalls[0]['second'])->toBe('catalog_product_price_index.product_id');
});

it('falls back to base-amount ordering when no market axis is configured', function (): void {
    $registry  = makeScopeRegistryWithoutMarket();
    $context   = new ScopeContext($registry);
    $sortOrder = new ScopedAscendingIndexedPriceSortOrder($registry, $context);

    $fields = $sortOrder->sortFields();

    expect($fields)->toHaveCount(1);
    expect($fields[0]->expression)->toBeNull();
    expect($fields[0]->column)->toBe('catalog_product_price_index.amount');
    expect($fields[0]->direction)->toBe(SortDirection::Ascending);
    expect($fields[0]->nulls)->toBe(NullsPlacement::Last);
});
