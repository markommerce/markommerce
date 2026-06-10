<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Tests\Feature\Factories;

use Markommerce\Catalog\Tests\Support\ProductFactory;
use Markommerce\CatalogPriceIndex\Contracts\ProductPriceIndexRepositoryInterface;
use Markommerce\Testing\Database\TestConnection;
use Markommerce\Testing\Fixtures\Exceptions\MissingModuleException;
use Markommerce\Testing\IntegrationTestCase;
use Markommerce\Testing\Profile\StoreProfile;

function fixtureFactoriesVendorDir(): string
{
    return dirname(__DIR__, 5) . '/vendor';
}

function fixtureFactoriesSimpleProfile(): StoreProfile
{
    return StoreProfile::simple(fixtureFactoriesVendorDir());
}

function fixtureFactoriesPriceIndexProfile(): StoreProfile
{
    return StoreProfile::of(
        fixtureFactoriesVendorDir(),
        'markommerce/catalog',
        'markommerce/catalog-price-index',
        'marko/database-pgsql',
    );
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('writes an indexed price row when the profile supports it', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(fixtureFactoriesPriceIndexProfile());
    $testCase->setUpIntegration();

    try {
        $product = ProductFactory::new($testCase->store)
            ->withIndexedPrice('29.99')
            ->create();

        expect($product->id)->not->toBeNull();

        /** @var ProductPriceIndexRepositoryInterface $indexRepo */
        $indexRepo = $testCase->store->get(ProductPriceIndexRepositoryInterface::class);
        $entry = $indexRepo->findByProductId((int) $product->id);

        expect($entry)->not->toBeNull();
        expect($entry?->amount)->toBe('29.9900');
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('fails clearly when indexed price is requested without the price-index module', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(fixtureFactoriesSimpleProfile());
    $testCase->setUpIntegration();

    try {
        $caught = null;

        try {
            ProductFactory::new($testCase->store)
                ->withIndexedPrice('9.99')
                ->create();
        } catch (MissingModuleException $e) {
            $caught = $e;
        }

        expect($caught)->not->toBeNull()->toBeInstanceOf(MissingModuleException::class);

        assert($caught instanceof MissingModuleException);
        expect($caught->getMessage())->not->toBeEmpty();
        expect($caught->getContext())->not->toBeEmpty();
        expect($caught->getSuggestion())->not->toBeEmpty();
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');
