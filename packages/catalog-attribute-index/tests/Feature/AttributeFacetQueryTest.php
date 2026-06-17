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
use Markommerce\Catalog\Filtering\FilterSelection;
use Markommerce\Catalog\Repositories\ProductRepository;
use Markommerce\CatalogAttribute\ProductAttributeAccessor;
use Markommerce\CatalogAttributeIndex\AttributeIndexer;
use Markommerce\CatalogAttributeIndex\Facet\AttributeFacetQuery;
use Markommerce\CatalogAttributeScope\ScopedProductAttributeAccessor;
use Markommerce\Scope\Signature\ScopeSignature;
use Markommerce\Testing\Database\TestConnection;
use Markommerce\Testing\IntegrationTestCase;
use Markommerce\Testing\Profile\StoreProfile;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function attrFacetVendorDir(): string
{
    return dirname(__DIR__, 4) . '/vendor';
}

function makeAttrFacetProfile(): StoreProfile
{
    return StoreProfile::of(
        attrFacetVendorDir(),
        'markommerce/catalog-attribute-index',
        'markommerce/locale',
        'marko/database-pgsql',
        'markommerce/attribute-pgsql',
    )->withLocales('default', 'en', 'de');
}

function makeAttrFacetDefinitionService(IntegrationTestCase $testCase): AttributeDefinitionService
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

/**
 * Create a category in the DB and return its auto-generated id.
 */
function createFacetCategory(
    ConnectionInterface $connection,
    string $name,
): int {
    $connection->execute('INSERT INTO catalog_categories (name) VALUES (?)', [$name]);

    return $connection->lastInsertId();
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('returns facet value counts for a facetable attribute in a category', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(makeAttrFacetProfile());
    $testCase->setUpIntegration();

    try {
        $service = makeAttrFacetDefinitionService($testCase);

        $colorDef = new AttributeDefinition();
        $colorDef->code = 'color';
        $colorDef->entityType = 'product';
        $colorDef->type = 'select';
        $colorDef->label = 'Color';
        $colorDef->scopable = false;
        $colorDef->facetable = true;
        $colorDef->config = [];

        $service->create($colorDef, [
            (function (): AttributeOption { $o = new AttributeOption(); $o->value = 'red'; $o->label = 'Red';

return $o;
 })(),
            (function (): AttributeOption { $o = new AttributeOption(); $o->value = 'blue'; $o->label = 'Blue';

return $o;
 })(),
        ]);

        /** @var ProductAttributeAccessor $globalAccessor */
        $globalAccessor = $testCase->get(ProductAttributeAccessor::class);

        /** @var ProductRepository $productRepository */
        $productRepository = $testCase->get(ProductRepository::class);

        /** @var ConnectionInterface $connection */
        $connection = $testCase->get(ConnectionInterface::class);

        $p1 = new Product();
        $p1->sku = 'FACET-C-001';
        $p1->name = 'Facet Color 1';
        $globalAccessor->set($p1, 'color', 'red');
        $productRepository->save($p1);

        $p2 = new Product();
        $p2->sku = 'FACET-C-002';
        $p2->name = 'Facet Color 2';
        $globalAccessor->set($p2, 'color', 'blue');
        $productRepository->save($p2);

        $p3 = new Product();
        $p3->sku = 'FACET-C-003';
        $p3->name = 'Facet Color 3';
        $globalAccessor->set($p3, 'color', 'red');
        $productRepository->save($p3);

        $categoryId = createFacetCategory($connection, 'Test Facet Color Category');
        $connection->execute(
            'INSERT INTO catalog_product_category (product_id, category_id) VALUES (?, ?), (?, ?), (?, ?)',
            [(int) $p1->id, $categoryId, (int) $p2->id, $categoryId, (int) $p3->id, $categoryId],
        );

        /** @var AttributeIndexer $indexer */
        $indexer = $testCase->get(AttributeIndexer::class);
        $indexer->rebuildAll();

        /** @var AttributeFacetQuery $facetQuery */
        $facetQuery = $testCase->get(AttributeFacetQuery::class);

        $facets = $facetQuery->facets($categoryId, new FilterSelection());

        expect($facets)->not->toBeEmpty();

        $colorFacet = array_find($facets, fn ($f) => $f->code === 'color');
        expect($colorFacet)->not->toBeNull();

        $valuesByCode = [];

        foreach ($colorFacet->values as $fv) {
            $valuesByCode[$fv->value] = $fv->count;
        }

        expect($valuesByCode)->toHaveKey('red')
            ->and($valuesByCode['red'])->toBe(2)
            ->and($valuesByCode)->toHaveKey('blue')
            ->and($valuesByCode['blue'])->toBe(1);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('counts distinct products per value at the resolved scope signature', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(makeAttrFacetProfile());
    $testCase->setUpIntegration();

    try {
        $service = makeAttrFacetDefinitionService($testCase);

        $colorDef = new AttributeDefinition();
        $colorDef->code = 'color';
        $colorDef->entityType = 'product';
        $colorDef->type = 'select';
        $colorDef->label = 'Color';
        $colorDef->scopable = true;
        $colorDef->facetable = true;
        $colorDef->config = ['axes' => ['locale']];

        $service->create($colorDef, [
            (function (): AttributeOption { $o = new AttributeOption(); $o->value = 'red'; $o->label = 'Red';

return $o;
 })(),
            (function (): AttributeOption { $o = new AttributeOption(); $o->value = 'rot'; $o->label = 'Rot';

return $o;
 })(),
        ]);

        /** @var ProductAttributeAccessor $globalAccessor */
        $globalAccessor = $testCase->get(ProductAttributeAccessor::class);

        /** @var ScopedProductAttributeAccessor $scopedAccessor */
        $scopedAccessor = $testCase->get(ScopedProductAttributeAccessor::class);

        /** @var ProductRepository $productRepository */
        $productRepository = $testCase->get(ProductRepository::class);

        /** @var ConnectionInterface $connection */
        $connection = $testCase->get(ConnectionInterface::class);

        $deSig = ScopeSignature::fromArray(['locale' => 'de']);

        // p1: red globally, rot in de
        $p1 = new Product();
        $p1->sku = 'FACET-S-001';
        $p1->name = 'Scoped Facet 1';
        $globalAccessor->set($p1, 'color', 'red');
        $scopedAccessor->setScoped($p1, 'color', 'rot', $deSig);
        $productRepository->save($p1);

        // p2: red globally, no de override → resolves to base 'red' under de
        $p2 = new Product();
        $p2->sku = 'FACET-S-002';
        $p2->name = 'Scoped Facet 2';
        $globalAccessor->set($p2, 'color', 'red');
        $productRepository->save($p2);

        $categoryId = createFacetCategory($connection, 'Test Scoped Facet Category');
        $connection->execute(
            'INSERT INTO catalog_product_category (product_id, category_id) VALUES (?, ?), (?, ?)',
            [(int) $p1->id, $categoryId, (int) $p2->id, $categoryId],
        );

        /** @var AttributeIndexer $indexer */
        $indexer = $testCase->get(AttributeIndexer::class);
        $indexer->rebuildAll();

        /** @var AttributeFacetQuery $facetQuery */
        $facetQuery = $testCase->get(AttributeFacetQuery::class);

        // Run with locale:de scope active
        $facets = [];
        $testCase->store->inScope(null, 'de', function () use ($facetQuery, $categoryId, &$facets): void {
            $facets = $facetQuery->facets($categoryId, new FilterSelection());
        });

        $colorFacet = array_find($facets, fn ($f) => $f->code === 'color');
        expect($colorFacet)->not->toBeNull();

        $valuesByCode = [];

        foreach ($colorFacet->values as $fv) {
            $valuesByCode[$fv->value] = $fv->count;
        }

        // Under locale:de: p1 = 'rot', p2 = 'red' (falls back to base since en = base)
        expect($valuesByCode)->toHaveKey('rot')
            ->and($valuesByCode['rot'])->toBe(1)
            ->and($valuesByCode)->toHaveKey('red')
            ->and($valuesByCode['red'])->toBe(1);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('computes a facet disjunctively ignoring that facet\'s own selected values', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(makeAttrFacetProfile());
    $testCase->setUpIntegration();

    try {
        $service = makeAttrFacetDefinitionService($testCase);

        $colorDef = new AttributeDefinition();
        $colorDef->code = 'color';
        $colorDef->entityType = 'product';
        $colorDef->type = 'select';
        $colorDef->label = 'Color';
        $colorDef->scopable = false;
        $colorDef->facetable = true;
        $colorDef->config = [];

        $service->create($colorDef, [
            (function (): AttributeOption { $o = new AttributeOption(); $o->value = 'red'; $o->label = 'Red';

return $o;
 })(),
            (function (): AttributeOption { $o = new AttributeOption(); $o->value = 'blue'; $o->label = 'Blue';

return $o;
 })(),
            (function (): AttributeOption { $o = new AttributeOption(); $o->value = 'green'; $o->label = 'Green';

return $o;
 })(),
        ]);

        /** @var ProductAttributeAccessor $globalAccessor */
        $globalAccessor = $testCase->get(ProductAttributeAccessor::class);

        /** @var ProductRepository $productRepository */
        $productRepository = $testCase->get(ProductRepository::class);

        /** @var ConnectionInterface $connection */
        $connection = $testCase->get(ConnectionInterface::class);

        $p1 = new Product();
        $p1->sku = 'FACET-D-001';
        $p1->name = 'Disjunctive 1';
        $globalAccessor->set($p1, 'color', 'red');
        $productRepository->save($p1);

        $p2 = new Product();
        $p2->sku = 'FACET-D-002';
        $p2->name = 'Disjunctive 2';
        $globalAccessor->set($p2, 'color', 'blue');
        $productRepository->save($p2);

        $p3 = new Product();
        $p3->sku = 'FACET-D-003';
        $p3->name = 'Disjunctive 3';
        $globalAccessor->set($p3, 'color', 'green');
        $productRepository->save($p3);

        $categoryId = createFacetCategory($connection, 'Test Disjunctive Category');
        $connection->execute(
            'INSERT INTO catalog_product_category (product_id, category_id) VALUES (?, ?), (?, ?), (?, ?)',
            [(int) $p1->id, $categoryId, (int) $p2->id, $categoryId, (int) $p3->id, $categoryId],
        );

        /** @var AttributeIndexer $indexer */
        $indexer = $testCase->get(AttributeIndexer::class);
        $indexer->rebuildAll();

        /** @var AttributeFacetQuery $facetQuery */
        $facetQuery = $testCase->get(AttributeFacetQuery::class);

        // Filter is set to 'red' for color — disjunctive means color facet ignores its own filter
        // so ALL 3 values (red, blue, green) remain countable
        $selection = new FilterSelection(['color' => ['red']]);
        $facets = $facetQuery->facets($categoryId, $selection);

        $colorFacet = array_find($facets, fn ($f) => $f->code === 'color');
        expect($colorFacet)->not->toBeNull();

        // All 3 values should appear because disjunctive ignores own filter
        expect($colorFacet->values)->toHaveCount(3);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('applies other attributes\' selected filters when counting a facet', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(makeAttrFacetProfile());
    $testCase->setUpIntegration();

    try {
        $service = makeAttrFacetDefinitionService($testCase);

        // Two facetable attributes: color and size
        $colorDef = new AttributeDefinition();
        $colorDef->code = 'color';
        $colorDef->entityType = 'product';
        $colorDef->type = 'select';
        $colorDef->label = 'Color';
        $colorDef->scopable = false;
        $colorDef->facetable = true;
        $colorDef->config = [];

        $sizeDef = new AttributeDefinition();
        $sizeDef->code = 'size';
        $sizeDef->entityType = 'product';
        $sizeDef->type = 'select';
        $sizeDef->label = 'Size';
        $sizeDef->scopable = false;
        $sizeDef->facetable = true;
        $sizeDef->config = [];

        $service->create($colorDef, [
            (function (): AttributeOption { $o = new AttributeOption(); $o->value = 'red'; $o->label = 'Red';

return $o;
 })(),
            (function (): AttributeOption { $o = new AttributeOption(); $o->value = 'blue'; $o->label = 'Blue';

return $o;
 })(),
        ]);
        $service->create($sizeDef, [
            (function (): AttributeOption { $o = new AttributeOption(); $o->value = 'S'; $o->label = 'Small';

return $o;
 })(),
            (function (): AttributeOption { $o = new AttributeOption(); $o->value = 'L'; $o->label = 'Large';

return $o;
 })(),
        ]);

        /** @var ProductAttributeAccessor $globalAccessor */
        $globalAccessor = $testCase->get(ProductAttributeAccessor::class);

        /** @var ProductRepository $productRepository */
        $productRepository = $testCase->get(ProductRepository::class);

        /** @var ConnectionInterface $connection */
        $connection = $testCase->get(ConnectionInterface::class);

        // p1: red, S
        $p1 = new Product();
        $p1->sku = 'FACET-O-001';
        $p1->name = 'Other Filter 1';
        $globalAccessor->set($p1, 'color', 'red');
        $globalAccessor->set($p1, 'size', 'S');
        $productRepository->save($p1);

        // p2: red, L
        $p2 = new Product();
        $p2->sku = 'FACET-O-002';
        $p2->name = 'Other Filter 2';
        $globalAccessor->set($p2, 'color', 'red');
        $globalAccessor->set($p2, 'size', 'L');
        $productRepository->save($p2);

        // p3: blue, S
        $p3 = new Product();
        $p3->sku = 'FACET-O-003';
        $p3->name = 'Other Filter 3';
        $globalAccessor->set($p3, 'color', 'blue');
        $globalAccessor->set($p3, 'size', 'S');
        $productRepository->save($p3);

        $categoryId = createFacetCategory($connection, 'Test Other Filters Category');
        $connection->execute(
            'INSERT INTO catalog_product_category (product_id, category_id) VALUES (?, ?), (?, ?), (?, ?)',
            [(int) $p1->id, $categoryId, (int) $p2->id, $categoryId, (int) $p3->id, $categoryId],
        );

        /** @var AttributeIndexer $indexer */
        $indexer = $testCase->get(AttributeIndexer::class);
        $indexer->rebuildAll();

        /** @var AttributeFacetQuery $facetQuery */
        $facetQuery = $testCase->get(AttributeFacetQuery::class);

        // When size=S is selected, the color facet counts should only count products that also have size=S
        // p1 (red, S) and p3 (blue, S) qualify → red=1, blue=1
        // p2 (red, L) does NOT qualify because size filter = S is applied to color's count
        $selection = new FilterSelection(['size' => ['S']]);
        $facets = $facetQuery->facets($categoryId, $selection);

        $colorFacet = array_find($facets, fn ($f) => $f->code === 'color');
        expect($colorFacet)->not->toBeNull();

        $valuesByCode = [];

        foreach ($colorFacet->values as $fv) {
            $valuesByCode[$fv->value] = $fv->count;
        }

        expect($valuesByCode)->toHaveKey('red')
            ->and($valuesByCode['red'])->toBe(1)
            ->and($valuesByCode)->toHaveKey('blue')
            ->and($valuesByCode['blue'])->toBe(1);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('marks selected values in the returned facet', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(makeAttrFacetProfile());
    $testCase->setUpIntegration();

    try {
        $service = makeAttrFacetDefinitionService($testCase);

        $colorDef = new AttributeDefinition();
        $colorDef->code = 'color';
        $colorDef->entityType = 'product';
        $colorDef->type = 'select';
        $colorDef->label = 'Color';
        $colorDef->scopable = false;
        $colorDef->facetable = true;
        $colorDef->config = [];

        $service->create($colorDef, [
            (function (): AttributeOption { $o = new AttributeOption(); $o->value = 'red'; $o->label = 'Red';

return $o;
 })(),
            (function (): AttributeOption { $o = new AttributeOption(); $o->value = 'blue'; $o->label = 'Blue';

return $o;
 })(),
        ]);

        /** @var ProductAttributeAccessor $globalAccessor */
        $globalAccessor = $testCase->get(ProductAttributeAccessor::class);

        /** @var ProductRepository $productRepository */
        $productRepository = $testCase->get(ProductRepository::class);

        /** @var ConnectionInterface $connection */
        $connection = $testCase->get(ConnectionInterface::class);

        $p1 = new Product();
        $p1->sku = 'FACET-M-001';
        $p1->name = 'Mark Selected 1';
        $globalAccessor->set($p1, 'color', 'red');
        $productRepository->save($p1);

        $p2 = new Product();
        $p2->sku = 'FACET-M-002';
        $p2->name = 'Mark Selected 2';
        $globalAccessor->set($p2, 'color', 'blue');
        $productRepository->save($p2);

        $categoryId = createFacetCategory($connection, 'Test Mark Selected Category');
        $connection->execute(
            'INSERT INTO catalog_product_category (product_id, category_id) VALUES (?, ?), (?, ?)',
            [(int) $p1->id, $categoryId, (int) $p2->id, $categoryId],
        );

        /** @var AttributeIndexer $indexer */
        $indexer = $testCase->get(AttributeIndexer::class);
        $indexer->rebuildAll();

        /** @var AttributeFacetQuery $facetQuery */
        $facetQuery = $testCase->get(AttributeFacetQuery::class);

        $selection = new FilterSelection(['color' => ['red']]);
        $facets = $facetQuery->facets($categoryId, $selection);

        $colorFacet = array_find($facets, fn ($f) => $f->code === 'color');
        expect($colorFacet)->not->toBeNull();

        $selectedValues = array_values(array_filter($colorFacet->values, fn ($fv) => $fv->selected));
        $notSelectedValues = array_values(array_filter($colorFacet->values, fn ($fv) => !$fv->selected));

        expect($selectedValues)->toHaveCount(1);
        expect($selectedValues[0]->value)->toBe('red');

        expect($notSelectedValues)->toHaveCount(1);
        expect($notSelectedValues[0]->value)->toBe('blue');
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');
