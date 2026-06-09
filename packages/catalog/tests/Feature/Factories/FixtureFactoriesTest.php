<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Tests\Feature\Factories;

use Markommerce\Catalog\Contracts\CategoryRepositoryInterface;
use Markommerce\Catalog\Contracts\ProductCategoryAssignmentRepositoryInterface;
use Markommerce\Catalog\Contracts\ProductRepositoryInterface;
use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Tests\Support\CategoryFactory;
use Markommerce\Catalog\Tests\Support\ProductFactory;
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

it('creates a product with default attributes via the real repository', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(fixtureFactoriesSimpleProfile());
    $testCase->setUpIntegration();

    try {
        $product = ProductFactory::new($testCase->store)->create();

        expect($product)->toBeInstanceOf(Product::class);
        expect($product->id)->not->toBeNull();
        expect($product->sku)->not->toBeEmpty();
        expect($product->name)->not->toBeEmpty();

        /** @var ProductRepositoryInterface $repo */
        $repo = $testCase->store->get(ProductRepositoryInterface::class);
        $found = $repo->findBySku($product->sku);

        expect($found)->not->toBeNull();
        expect($found?->id)->toBe($product->id);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('overrides product sku name and price fluently', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(fixtureFactoriesSimpleProfile());
    $testCase->setUpIntegration();

    try {
        $product = ProductFactory::new($testCase->store)
            ->withSku('MY-CUSTOM-SKU')
            ->withName('My Custom Product')
            ->withPrice('19.99')
            ->create();

        expect($product->sku)->toBe('MY-CUSTOM-SKU');
        expect($product->name)->toBe('My Custom Product');

        /** @var ProductRepositoryInterface $repo */
        $repo = $testCase->store->get(ProductRepositoryInterface::class);
        $found = $repo->findBySku('MY-CUSTOM-SKU');

        expect($found)->not->toBeNull();
        expect($found?->name)->toBe('My Custom Product');
        expect($found?->priceAmount)->toBe('19.9900');
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('assigns a created product to a category', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(fixtureFactoriesSimpleProfile());
    $testCase->setUpIntegration();

    try {
        $category = CategoryFactory::new($testCase->store)->create();
        $product = ProductFactory::new($testCase->store)
            ->inCategory($category)
            ->create();

        /** @var ProductCategoryAssignmentRepositoryInterface $assignmentRepo */
        $assignmentRepo = $testCase->store->get(ProductCategoryAssignmentRepositoryInterface::class);
        $assignment = $assignmentRepo->findByProductAndCategory((int) $product->id, (int) $category->id);

        expect($assignment)->not->toBeNull();
        expect($assignment?->productId)->toBe($product->id);
        expect($assignment?->categoryId)->toBe($category->id);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('creates a category via the real repository', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(fixtureFactoriesSimpleProfile());
    $testCase->setUpIntegration();

    try {
        $category = CategoryFactory::new($testCase->store)->withName('Electronics')->create();

        expect($category)->toBeInstanceOf(Category::class);
        expect($category->id)->not->toBeNull();
        expect($category->name)->toBe('Electronics');

        /** @var CategoryRepositoryInterface $repo */
        $repo = $testCase->store->get(CategoryRepositoryInterface::class);
        $found = $repo->find((int) $category->id);

        expect($found)->not->toBeNull();
        expect($found?->name)->toBe('Electronics');
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('writes an indexed price row when the profile supports it', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(fixtureFactoriesPriceIndexProfile());
    $testCase->setUpIntegration();

    try {
        $product = ProductFactory::new($testCase->store)
            ->withIndexedPrice('29.99')
            ->create();

        expect($product->id)->not->toBeNull();

        /** @var \Markommerce\CatalogPriceIndex\Contracts\ProductPriceIndexRepositoryInterface $indexRepo */
        $indexRepo = $testCase->store->get(\Markommerce\CatalogPriceIndex\Contracts\ProductPriceIndexRepositoryInterface::class);
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
});

it('generates unique default skus across multiple products', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(fixtureFactoriesSimpleProfile());
    $testCase->setUpIntegration();

    try {
        $product1 = ProductFactory::new($testCase->store)->create();
        $product2 = ProductFactory::new($testCase->store)->create();
        $product3 = ProductFactory::new($testCase->store)->create();

        $skus = [$product1->sku, $product2->sku, $product3->sku];

        expect(array_unique($skus))->toHaveCount(3);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('adds markommerce/testing as a catalog require-dev without a composer dependency cycle', function (): void {
    $catalogComposerPath = dirname(__DIR__, 3) . '/composer.json';
    $catalogComposerJson = (string) file_get_contents($catalogComposerPath);
    $catalogComposer = json_decode($catalogComposerJson, true);

    expect($catalogComposer['require-dev'])->toHaveKey('markommerce/testing');

    $testingComposerPath = dirname(__DIR__, 5) . '/vendor/markommerce/testing/composer.json';
    $testingComposerJson = (string) file_get_contents($testingComposerPath);
    $testingComposer = json_decode($testingComposerJson, true);

    $runtimeRequires = array_keys($testingComposer['require'] ?? []);

    expect($runtimeRequires)->not->toContain('markommerce/catalog');
});
