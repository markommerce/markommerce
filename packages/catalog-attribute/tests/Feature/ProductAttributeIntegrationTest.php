<?php

declare(strict_types=1);

namespace Markommerce\CatalogAttribute\Tests\Feature;

use Marko\Database\Connection\ConnectionInterface;
use Markommerce\Attribute\Contracts\AttributeDefinitionRepositoryInterface;
use Markommerce\Attribute\Entity\AttributeDefinition;
use Markommerce\Attribute\Exceptions\ReservedAttributeCodeException;
use Markommerce\Attribute\Registry\AttributeEntityClassMap;
use Markommerce\Attribute\Registry\AttributeTypeRegistry;
use Markommerce\Attribute\Reserved\ReservedCodeProvider;
use Markommerce\Attribute\Services\AttributeDefinitionService;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Repositories\ProductRepository;
use Markommerce\CatalogAttribute\ProductAttributeAccessor;
use Markommerce\Testing\Database\TestConnection;
use Markommerce\Testing\IntegrationTestCase;
use Markommerce\Testing\Profile\StoreProfile;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function catalogAttributeVendorDir(): string
{
    return dirname(__DIR__, 4) . '/vendor';
}

function makeCatalogAttributeProfile(): StoreProfile
{
    return StoreProfile::of(
        catalogAttributeVendorDir(),
        'markommerce/catalog-attribute',
        'marko/database-pgsql',
    );
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('it merges the attribute_values column into the catalog_products table', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(makeCatalogAttributeProfile());
    $testCase->setUpIntegration();

    try {
        /** @var ConnectionInterface $connection */
        $connection = $testCase->get(ConnectionInterface::class);

        $rows = $connection->query(
            "SELECT column_name FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'catalog_products' AND column_name = 'attribute_values'",
        );

        $columnNames = array_column($rows, 'column_name');

        expect($columnNames)->toContain('attribute_values');
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('it round-trips a Json-backed custom value on a product through save and refetch', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(makeCatalogAttributeProfile());
    $testCase->setUpIntegration();

    try {
        /** @var AttributeDefinitionRepositoryInterface $definitionRepository */
        $definitionRepository = $testCase->get(AttributeDefinitionRepositoryInterface::class);

        /** @var AttributeTypeRegistry $typeRegistry */
        $typeRegistry = $testCase->get(AttributeTypeRegistry::class);

        /** @var ReservedCodeProvider $reservedCodeProvider */
        $reservedCodeProvider = $testCase->get(ReservedCodeProvider::class);

        /** @var AttributeEntityClassMap $entityClassMap */
        $entityClassMap = $testCase->get(AttributeEntityClassMap::class);

        $service = new AttributeDefinitionService(
            attributeDefinitionRepository: $definitionRepository,
            attributeTypeRegistry: $typeRegistry,
            reservedCodeProvider: $reservedCodeProvider,
            entityClassMap: $entityClassMap,
        );

        $colorDef = new AttributeDefinition();
        $colorDef->code = 'color';
        $colorDef->entityType = 'product';
        $colorDef->type = 'text';
        $colorDef->label = 'Color';

        $service->create($colorDef);

        /** @var ProductAttributeAccessor $accessor */
        $accessor = $testCase->get(ProductAttributeAccessor::class);

        /** @var ProductRepository $productRepository */
        $productRepository = $testCase->get(ProductRepository::class);

        $product = new Product();
        $product->sku = 'TEST-COLOR-001';
        $product->name = 'Colored T-Shirt';

        $accessor->set($product, 'color', 'red');
        $productRepository->save($product);

        /** @var Product $found */
        $found = $productRepository->find($product->id);

        expect($found)->not->toBeNull();
        expect($accessor->get($found, 'color'))->toBe('red');
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('it round-trips a Column-backed value written via the accessor onto the native column', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(makeCatalogAttributeProfile());
    $testCase->setUpIntegration();

    try {
        /** @var ProductAttributeAccessor $accessor */
        $accessor = $testCase->get(ProductAttributeAccessor::class);

        /** @var ProductRepository $productRepository */
        $productRepository = $testCase->get(ProductRepository::class);

        $product = new Product();
        $product->sku = 'TEST-NAME-001';
        $product->name = 'Original Name';

        $accessor->set($product, 'name', 'Updated Name');
        $productRepository->save($product);

        /** @var Product $found */
        $found = $productRepository->find($product->id);

        expect($found)->not->toBeNull();
        expect($accessor->get($found, 'name'))->toBe('Updated Name');
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('it preserves decimal precision for a Column-backed priceAmount value', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(makeCatalogAttributeProfile());
    $testCase->setUpIntegration();

    try {
        /** @var ProductAttributeAccessor $accessor */
        $accessor = $testCase->get(ProductAttributeAccessor::class);

        /** @var ProductRepository $productRepository */
        $productRepository = $testCase->get(ProductRepository::class);

        $product = new Product();
        $product->sku = 'TEST-PRICE-001';
        $product->name = 'Priced Product';

        $accessor->set($product, 'priceAmount', '19.99');
        $productRepository->save($product);

        /** @var Product $found */
        $found = $productRepository->find($product->id);

        expect($found)->not->toBeNull();

        $price = $accessor->get($found, 'priceAmount');

        expect((float) $price)->toBe(19.99);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('it rejects creating a custom product attribute whose code collides with a native column', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(makeCatalogAttributeProfile());
    $testCase->setUpIntegration();

    try {
        /** @var AttributeDefinitionRepositoryInterface $definitionRepository */
        $definitionRepository = $testCase->get(AttributeDefinitionRepositoryInterface::class);

        /** @var AttributeTypeRegistry $typeRegistry */
        $typeRegistry = $testCase->get(AttributeTypeRegistry::class);

        /** @var ReservedCodeProvider $reservedCodeProvider */
        $reservedCodeProvider = $testCase->get(ReservedCodeProvider::class);

        /** @var AttributeEntityClassMap $entityClassMap */
        $entityClassMap = $testCase->get(AttributeEntityClassMap::class);

        $service = new AttributeDefinitionService(
            attributeDefinitionRepository: $definitionRepository,
            attributeTypeRegistry: $typeRegistry,
            reservedCodeProvider: $reservedCodeProvider,
            entityClassMap: $entityClassMap,
        );

        $skuDef = new AttributeDefinition();
        $skuDef->code = 'sku';
        $skuDef->entityType = 'product';
        $skuDef->type = 'text';
        $skuDef->label = 'SKU';

        expect(fn () => $service->create($skuDef))->toThrow(ReservedAttributeCodeException::class);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');
