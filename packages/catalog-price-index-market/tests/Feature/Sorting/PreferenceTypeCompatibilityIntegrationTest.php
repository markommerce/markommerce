<?php

declare(strict_types=1);

namespace Markommerce\CatalogPriceIndexMarket\Tests\Feature\Sorting;

use Marko\Config\ConfigRepository;
use Markommerce\CatalogPriceIndex\Sorting\AscendingIndexedPriceSortOrder;
use Markommerce\CatalogPriceIndex\Sorting\DescendingIndexedPriceSortOrder;
use Markommerce\CatalogPriceIndexMarket\Sorting\ScopedAscendingIndexedPriceSortOrder;
use Markommerce\CatalogPriceIndexMarket\Sorting\ScopedDescendingIndexedPriceSortOrder;
use Markommerce\Testing\Container\ContainerBootstrapper;
use Markommerce\Testing\Module\ModuleResolver;
use Markommerce\Testing\Tests\Fixture\Container\NullConnection;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function resolvePreferenceCompatManifests(): array
{
    $vendorDir = dirname(__DIR__, 5) . '/vendor';
    $resolver  = new ModuleResolver($vendorDir);

    return $resolver->resolveFrom(['markommerce/catalog-price-index-market']);
}

function resolvePreferenceCompatConfig(): ConfigRepository
{
    return new ConfigRepository([
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
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('both packages boot together without a TypeError and the preference replacement is type-compatible', function (): void {
    // This is the regression test for the production bug:
    // catalog-price-index/module.php's boot closure type-hints the concrete
    // AscendingIndexedPriceSortOrder and DescendingIndexedPriceSortOrder.
    // When catalog-price-index-market is installed, the preference swaps them for
    // scoped variants. If the scoped variants are NOT subtypes of the concrete classes,
    // the container would throw a TypeError at boot time.

    $manifests  = resolvePreferenceCompatManifests();
    $config     = resolvePreferenceCompatConfig();
    $connection = new NullConnection();

    $bootstrapper = new ContainerBootstrapper();

    // Must NOT throw a TypeError — the scoped classes are now subtypes of the originals
    $container = $bootstrapper->bootedContainer($manifests, $config, $connection);

    // The container must resolve AscendingIndexedPriceSortOrder as the scoped variant
    $ascending = $container->get(AscendingIndexedPriceSortOrder::class);
    expect($ascending)->toBeInstanceOf(ScopedAscendingIndexedPriceSortOrder::class);
    expect($ascending)->toBeInstanceOf(AscendingIndexedPriceSortOrder::class);

    // The container must resolve DescendingIndexedPriceSortOrder as the scoped variant
    $descending = $container->get(DescendingIndexedPriceSortOrder::class);
    expect($descending)->toBeInstanceOf(ScopedDescendingIndexedPriceSortOrder::class);
    expect($descending)->toBeInstanceOf(DescendingIndexedPriceSortOrder::class);
})->group('integration-destructive');
