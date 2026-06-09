<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Markommerce\CatalogPriceIndex\Sorting\AscendingIndexedPriceSortOrder;
use Markommerce\CatalogPriceIndex\Sorting\DescendingIndexedPriceSortOrder;
use Markommerce\CatalogPriceIndexMarket\Sorting\MarketScopedPriceExpression;
use Markommerce\CatalogPriceIndexMarket\Sorting\ScopedAscendingIndexedPriceSortOrder;
use Markommerce\CatalogPriceIndexMarket\Sorting\ScopedDescendingIndexedPriceSortOrder;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Registry\PhpScopeRegistry;

// ─── Fakes ────────────────────────────────────────────────────────────────────

function makeTypeCompatRegistry(): PhpScopeRegistry
{
    $config = new ConfigRepository([
        'scope' => [
            'axes' => [
                'market' => [
                    'default' => 'default',
                    'scopes'  => [
                        'default' => [],
                        'us'      => [],
                    ],
                ],
            ],
        ],
    ]);

    return new PhpScopeRegistry($config);
}

function makeTypeCompatExpression(): MarketScopedPriceExpression
{
    $registry = makeTypeCompatRegistry();
    $context  = new ScopeContext($registry);

    return new MarketScopedPriceExpression($registry, $context);
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('ScopedAscendingIndexedPriceSortOrder is an instanceof AscendingIndexedPriceSortOrder', function (): void {
    $expr     = makeTypeCompatExpression();
    $instance = new ScopedAscendingIndexedPriceSortOrder($expr);

    expect($instance)->toBeInstanceOf(AscendingIndexedPriceSortOrder::class);
});

it('ScopedDescendingIndexedPriceSortOrder is an instanceof DescendingIndexedPriceSortOrder', function (): void {
    $expr     = makeTypeCompatExpression();
    $instance = new ScopedDescendingIndexedPriceSortOrder($expr);

    expect($instance)->toBeInstanceOf(DescendingIndexedPriceSortOrder::class);
});
