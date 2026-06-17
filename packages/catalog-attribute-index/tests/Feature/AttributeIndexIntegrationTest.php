<?php

declare(strict_types=1);

namespace Markommerce\CatalogAttributeIndex\Tests\Feature;

use Marko\Database\Connection\ConnectionInterface;
use Markommerce\Attribute\Contracts\AttributeDefinitionRepositoryInterface;
use Markommerce\Attribute\Entity\AttributeDefinition;
use Markommerce\Attribute\Entity\AttributeOption;
use Markommerce\Attribute\Registry\AttributeEntityClassMap;
use Markommerce\Attribute\Registry\AttributeTypeRegistry;
use Markommerce\Attribute\Reserved\ReservedCodeProvider;
use Markommerce\Attribute\Services\AttributeDefinitionService;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Repositories\ProductRepository;
use Markommerce\CatalogAttribute\ProductAttributeAccessor;
use Markommerce\CatalogAttributeIndex\AttributeIndexer;
use Markommerce\CatalogAttributeIndex\IndexedAttributeReader;
use Markommerce\CatalogAttributeScope\ScopedProductAttributeAccessor;
use Markommerce\Scope\Signature\ScopeSignature;
use Markommerce\Testing\Database\TestConnection;
use Markommerce\Testing\IntegrationTestCase;
use Markommerce\Testing\Profile\StoreProfile;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function attrIndexIntVendorDir(): string
{
    return dirname(__DIR__, 4) . '/vendor';
}

function makeAttrIndexIntProfile(): StoreProfile
{
    return StoreProfile::of(
        attrIndexIntVendorDir(),
        'markommerce/catalog-attribute-index',
        'markommerce/locale',
        'marko/database-pgsql',
        'markommerce/attribute-pgsql',
    )->withLocales('default', 'en', 'de');
}

function makeAttrIndexIntDefinitionService(IntegrationTestCase $testCase): AttributeDefinitionService
{
    /** @var AttributeDefinitionRepositoryInterface $definitionRepository */
    $definitionRepository = $testCase->get(AttributeDefinitionRepositoryInterface::class);

    /** @var AttributeTypeRegistry $typeRegistry */
    $typeRegistry = $testCase->get(AttributeTypeRegistry::class);

    /** @var ReservedCodeProvider $reservedCodeProvider */
    $reservedCodeProvider = $testCase->get(ReservedCodeProvider::class);

    /** @var AttributeEntityClassMap $entityClassMap */
    $entityClassMap = $testCase->get(AttributeEntityClassMap::class);

    return new AttributeDefinitionService(
        attributeDefinitionRepository: $definitionRepository,
        attributeTypeRegistry: $typeRegistry,
        reservedCodeProvider: $reservedCodeProvider,
        entityClassMap: $entityClassMap,
    );
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('materializes a base row and a per-signature resolved row on rebuild', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(makeAttrIndexIntProfile());
    $testCase->setUpIntegration();

    try {
        $service = makeAttrIndexIntDefinitionService($testCase);

        // Create a scopable, facetable 'select' attribute 'color' with locale axis
        $colorDef = new AttributeDefinition();
        $colorDef->code = 'color';
        $colorDef->entityType = 'product';
        $colorDef->type = 'select';
        $colorDef->label = 'Color';
        $colorDef->scopable = true;
        $colorDef->facetable = true;
        $colorDef->config = ['axes' => ['locale']];

        $redOption = new AttributeOption();
        $redOption->value = 'red';
        $redOption->label = 'Red';

        $rotOption = new AttributeOption();
        $rotOption->value = 'rot';
        $rotOption->label = 'Rot';

        $service->create($colorDef, [$redOption, $rotOption]);

        /** @var ProductAttributeAccessor $globalAccessor */
        $globalAccessor = $testCase->get(ProductAttributeAccessor::class);

        /** @var ScopedProductAttributeAccessor $scopedAccessor */
        $scopedAccessor = $testCase->get(ScopedProductAttributeAccessor::class);

        /** @var ProductRepository $productRepository */
        $productRepository = $testCase->get(ProductRepository::class);

        $product = new Product();
        $product->sku = 'TEST-INDEX-001';
        $product->name = 'Test Index Product';

        // Set global value 'red'
        $globalAccessor->set($product, 'color', 'red');

        // Set scoped value 'rot' for locale:de
        $signature = ScopeSignature::fromArray(['locale' => 'de']);
        $scopedAccessor->setScoped($product, 'color', 'rot', $signature);

        $productRepository->save($product);

        /** @var Product $found */
        $found = $productRepository->find($product->id);
        expect($found)->not->toBeNull();

        // Run the indexer
        /** @var AttributeIndexer $indexer */
        $indexer = $testCase->get(AttributeIndexer::class);
        $indexer->rebuildAll();

        // Query index rows for this product
        /** @var ConnectionInterface $connection */
        $connection = $testCase->get(ConnectionInterface::class);

        $rows = $connection->query(
            'SELECT scope_signature, value_text FROM catalog_product_attribute_index'
            . " WHERE product_id = ? AND attribute_code = 'color'"
            . ' ORDER BY scope_signature',
            [(int) $product->id],
        );

        // Profile serves locale:en and locale:de (default excluded).
        // Full materialization: base '' + locale:en (equal to base) + locale:de (overridden) = 3 rows.
        expect($rows)->toHaveCount(3);

        $bySignature = [];
        foreach ($rows as $row) {
            $bySignature[$row['scope_signature']] = $row['value_text'];
        }

        expect($bySignature)->toHaveKey('')
            ->and($bySignature[''])->toBe('red');

        expect($bySignature)->toHaveKey('locale:en')
            ->and($bySignature['locale:en'])->toBe('red');

        expect($bySignature)->toHaveKey('locale:de')
            ->and($bySignature['locale:de'])->toBe('rot');
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('returns the product from a scoped value filter query against the index', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(makeAttrIndexIntProfile());
    $testCase->setUpIntegration();

    try {
        $service = makeAttrIndexIntDefinitionService($testCase);

        $colorDef = new AttributeDefinition();
        $colorDef->code = 'color';
        $colorDef->entityType = 'product';
        $colorDef->type = 'select';
        $colorDef->label = 'Color';
        $colorDef->scopable = true;
        $colorDef->facetable = true;
        $colorDef->config = ['axes' => ['locale']];

        $redOption = new AttributeOption();
        $redOption->value = 'red';
        $redOption->label = 'Red';

        $rotOption = new AttributeOption();
        $rotOption->value = 'rot';
        $rotOption->label = 'Rot';

        $service->create($colorDef, [$redOption, $rotOption]);

        /** @var ProductAttributeAccessor $globalAccessor */
        $globalAccessor = $testCase->get(ProductAttributeAccessor::class);

        /** @var ScopedProductAttributeAccessor $scopedAccessor */
        $scopedAccessor = $testCase->get(ScopedProductAttributeAccessor::class);

        /** @var ProductRepository $productRepository */
        $productRepository = $testCase->get(ProductRepository::class);

        $product = new Product();
        $product->sku = 'TEST-FILTER-001';
        $product->name = 'Test Filter Product';

        $globalAccessor->set($product, 'color', 'red');
        $signature = ScopeSignature::fromArray(['locale' => 'de']);
        $scopedAccessor->setScoped($product, 'color', 'rot', $signature);

        $productRepository->save($product);

        /** @var AttributeIndexer $indexer */
        $indexer = $testCase->get(AttributeIndexer::class);
        $indexer->rebuildAll();

        // Layered-nav filter query: find products where scope_signature='locale:de' AND attribute_code='color' AND value_text='rot'
        /** @var ConnectionInterface $connection */
        $connection = $testCase->get(ConnectionInterface::class);

        $rows = $connection->query(
            'SELECT product_id FROM catalog_product_attribute_index'
            . " WHERE scope_signature = 'locale:de' AND attribute_code = 'color' AND value_text = 'rot'",
        );

        expect($rows)->toHaveCount(1);
        expect((int) $rows[0]['product_id'])->toBe((int) $product->id);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('produces facet value counts via a group-by query against the index', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(makeAttrIndexIntProfile());
    $testCase->setUpIntegration();

    try {
        $service = makeAttrIndexIntDefinitionService($testCase);

        $colorDef = new AttributeDefinition();
        $colorDef->code = 'color';
        $colorDef->entityType = 'product';
        $colorDef->type = 'select';
        $colorDef->label = 'Color';
        $colorDef->scopable = true;
        $colorDef->facetable = true;
        $colorDef->config = ['axes' => ['locale']];

        $redOption = new AttributeOption();
        $redOption->value = 'red';
        $redOption->label = 'Red';

        $rotOption = new AttributeOption();
        $rotOption->value = 'rot';
        $rotOption->label = 'Rot';

        $blauOption = new AttributeOption();
        $blauOption->value = 'blau';
        $blauOption->label = 'Blau';

        $service->create($colorDef, [$redOption, $rotOption, $blauOption]);

        /** @var ProductAttributeAccessor $globalAccessor */
        $globalAccessor = $testCase->get(ProductAttributeAccessor::class);

        /** @var ScopedProductAttributeAccessor $scopedAccessor */
        $scopedAccessor = $testCase->get(ScopedProductAttributeAccessor::class);

        /** @var ProductRepository $productRepository */
        $productRepository = $testCase->get(ProductRepository::class);

        $signature = ScopeSignature::fromArray(['locale' => 'de']);

        // Product 1: red globally, rot for de
        $product1 = new Product();
        $product1->sku = 'TEST-FACET-001';
        $product1->name = 'Facet Product 1';
        $globalAccessor->set($product1, 'color', 'red');
        $scopedAccessor->setScoped($product1, 'color', 'rot', $signature);
        $productRepository->save($product1);

        // Product 2: red globally, rot for de
        $product2 = new Product();
        $product2->sku = 'TEST-FACET-002';
        $product2->name = 'Facet Product 2';
        $globalAccessor->set($product2, 'color', 'red');
        $scopedAccessor->setScoped($product2, 'color', 'rot', $signature);
        $productRepository->save($product2);

        // Product 3: red globally, blau for de
        $product3 = new Product();
        $product3->sku = 'TEST-FACET-003';
        $product3->name = 'Facet Product 3';
        $globalAccessor->set($product3, 'color', 'red');
        $scopedAccessor->setScoped($product3, 'color', 'blau', $signature);
        $productRepository->save($product3);

        /** @var AttributeIndexer $indexer */
        $indexer = $testCase->get(AttributeIndexer::class);
        $indexer->rebuildAll();

        // Facet count query: GROUP BY value_text for locale:de scope
        /** @var ConnectionInterface $connection */
        $connection = $testCase->get(ConnectionInterface::class);

        $rows = $connection->query(
            'SELECT value_text, COUNT(*) AS cnt FROM catalog_product_attribute_index'
            . " WHERE scope_signature = 'locale:de' AND attribute_code = 'color'"
            . ' GROUP BY value_text ORDER BY value_text',
        );

        expect($rows)->toHaveCount(2);

        $facets = [];
        foreach ($rows as $row) {
            $facets[$row['value_text']] = (int) $row['cnt'];
        }

        expect($facets)->toHaveKey('rot')
            ->and($facets['rot'])->toBe(2);

        expect($facets)->toHaveKey('blau')
            ->and($facets['blau'])->toBe(1);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('returns the live value via the fallback reader when the index has no row for the product', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(makeAttrIndexIntProfile());
    $testCase->setUpIntegration();

    try {
        $service = makeAttrIndexIntDefinitionService($testCase);

        $colorDef = new AttributeDefinition();
        $colorDef->code = 'color';
        $colorDef->entityType = 'product';
        $colorDef->type = 'select';
        $colorDef->label = 'Color';
        $colorDef->scopable = true;
        $colorDef->facetable = true;
        $colorDef->config = ['axes' => ['locale']];

        $redOption = new AttributeOption();
        $redOption->value = 'red';
        $redOption->label = 'Red';

        $service->create($colorDef, [$redOption]);

        /** @var ProductAttributeAccessor $globalAccessor */
        $globalAccessor = $testCase->get(ProductAttributeAccessor::class);

        /** @var ProductRepository $productRepository */
        $productRepository = $testCase->get(ProductRepository::class);

        $product = new Product();
        $product->sku = 'TEST-FALLBACK-IDX-001';
        $product->name = 'Test Fallback Index Product';

        $globalAccessor->set($product, 'color', 'red');
        $productRepository->save($product);

        /** @var Product $found */
        $found = $productRepository->find($product->id);
        expect($found)->not->toBeNull();

        // Do NOT rebuild the index — no index rows should exist

        /** @var IndexedAttributeReader $reader */
        $reader = $testCase->get(IndexedAttributeReader::class);

        // Without index rows, reader should fall back to live value
        $value = $reader->resolve($found, 'color');

        expect($value)->toBe('red');
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('returns the indexed value via the reader when an index row exists', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(makeAttrIndexIntProfile());
    $testCase->setUpIntegration();

    try {
        $service = makeAttrIndexIntDefinitionService($testCase);

        $colorDef = new AttributeDefinition();
        $colorDef->code = 'color';
        $colorDef->entityType = 'product';
        $colorDef->type = 'select';
        $colorDef->label = 'Color';
        $colorDef->scopable = true;
        $colorDef->facetable = true;
        $colorDef->config = ['axes' => ['locale']];

        $redOption = new AttributeOption();
        $redOption->value = 'red';
        $redOption->label = 'Red';

        $rotOption = new AttributeOption();
        $rotOption->value = 'rot';
        $rotOption->label = 'Rot';

        $service->create($colorDef, [$redOption, $rotOption]);

        /** @var ProductAttributeAccessor $globalAccessor */
        $globalAccessor = $testCase->get(ProductAttributeAccessor::class);

        /** @var ScopedProductAttributeAccessor $scopedAccessor */
        $scopedAccessor = $testCase->get(ScopedProductAttributeAccessor::class);

        /** @var ProductRepository $productRepository */
        $productRepository = $testCase->get(ProductRepository::class);

        $product = new Product();
        $product->sku = 'TEST-INDEXED-001';
        $product->name = 'Test Indexed Product';

        $globalAccessor->set($product, 'color', 'red');
        $signature = ScopeSignature::fromArray(['locale' => 'de']);
        $scopedAccessor->setScoped($product, 'color', 'rot', $signature);

        $productRepository->save($product);

        /** @var Product $found */
        $found = $productRepository->find($product->id);
        expect($found)->not->toBeNull();

        /** @var AttributeIndexer $indexer */
        $indexer = $testCase->get(AttributeIndexer::class);
        $indexer->rebuildAll();

        /** @var IndexedAttributeReader $reader */
        $reader = $testCase->get(IndexedAttributeReader::class);

        // Under locale:de scope — should return indexed 'rot'
        $resolvedDe = null;
        $testCase->store->inScope(null, 'de', function () use ($reader, $found, &$resolvedDe): void {
            $resolvedDe = $reader->resolve($found, 'color');
        });

        // Under locale:en scope — should return base 'red'
        $resolvedEn = null;
        $testCase->store->inScope(null, 'en', function () use ($reader, $found, &$resolvedEn): void {
            $resolvedEn = $reader->resolve($found, 'color');
        });

        expect($resolvedDe)->toBe('rot');
        expect($resolvedEn)->toBe('red');
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');
