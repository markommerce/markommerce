<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Markommerce\CatalogPriceIndexMarket\Sorting\MarketScopedPriceExpression;
use Markommerce\Criteria\Sort\NullsPlacement;
use Markommerce\Criteria\Sort\SortDirection;
use Markommerce\Criteria\Sort\SortField;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Registry\PhpScopeRegistry;
use Markommerce\Scope\Registry\ScopeRegistryInterface;

// ─── Fakes ────────────────────────────────────────────────────────────────────

function makeExprRegistryWithMarket(): ScopeRegistryInterface
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

function makeExprRegistryWithoutMarket(): ScopeRegistryInterface
{
    $config = new ConfigRepository([
        'scope' => [
            'axes' => [],
        ],
    ]);

    return new PhpScopeRegistry($config);
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('returns a COALESCE expression with ::numeric cast when market scope is active', function (): void {
    $registry = makeExprRegistryWithMarket();
    $context  = new ScopeContext($registry);
    $context->in('market', 'us');

    $expr   = new MarketScopedPriceExpression($registry, $context);
    $fields = $expr->sortFields(SortDirection::Ascending);

    expect($fields)->toHaveCount(1);
    expect($fields[0]->expression)->toContain('::numeric');
    expect($fields[0]->expression)->toContain("'market:us'");
    expect($fields[0]->expression)->toContain('COALESCE');
    expect($fields[0]->column)->toBe('catalog_product_price_index.amount');
    expect($fields[0]->direction)->toBe(SortDirection::Ascending);
    expect($fields[0]->nulls)->toBe(NullsPlacement::Last);
});

it('returns descending COALESCE when direction is Descending', function (): void {
    $registry = makeExprRegistryWithMarket();
    $context  = new ScopeContext($registry);
    $context->in('market', 'us');

    $expr   = new MarketScopedPriceExpression($registry, $context);
    $fields = $expr->sortFields(SortDirection::Descending);

    expect($fields[0]->direction)->toBe(SortDirection::Descending);
    expect($fields[0]->expression)->toContain('COALESCE');
});

it('falls back to base amount with no expression when no market scope is active', function (): void {
    $registry = makeExprRegistryWithMarket();
    $context  = new ScopeContext($registry);

    $expr   = new MarketScopedPriceExpression($registry, $context);
    $fields = $expr->sortFields(SortDirection::Ascending);

    expect($fields)->toHaveCount(1);
    expect($fields[0]->expression)->toBeNull();
    expect($fields[0]->column)->toBe('catalog_product_price_index.amount');
    expect($fields[0]->direction)->toBe(SortDirection::Ascending);
    expect($fields[0]->nulls)->toBe(NullsPlacement::Last);
});

it('falls back to base amount when no market axis is configured', function (): void {
    $registry = makeExprRegistryWithoutMarket();
    $context  = new ScopeContext($registry);

    $expr   = new MarketScopedPriceExpression($registry, $context);
    $fields = $expr->sortFields(SortDirection::Ascending);

    expect($fields)->toHaveCount(1);
    expect($fields[0]->expression)->toBeNull();
    expect($fields[0]->column)->toBe('catalog_product_price_index.amount');
    expect($fields[0]->direction)->toBe(SortDirection::Ascending);
    expect($fields[0]->nulls)->toBe(NullsPlacement::Last);
});

it('places products with no price last', function (): void {
    $registry = makeExprRegistryWithMarket();
    $context  = new ScopeContext($registry);
    $context->in('market', 'us');

    $expr   = new MarketScopedPriceExpression($registry, $context);
    $fields = $expr->sortFields(SortDirection::Ascending);

    expect($fields[0]->nulls)->toBe(NullsPlacement::Last);
});
