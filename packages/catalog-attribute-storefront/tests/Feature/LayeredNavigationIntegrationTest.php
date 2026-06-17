<?php

declare(strict_types=1);

namespace Markommerce\CatalogAttributeStorefront\Tests\Feature;

use Markommerce\Attribute\Contracts\AttributeDefinitionRepositoryInterface;
use Markommerce\Attribute\Entity\AttributeDefinition;
use Markommerce\Attribute\Entity\AttributeOption;
use Markommerce\Attribute\Registry\AttributeEntityClassMap;
use Markommerce\Attribute\Registry\AttributeTypeRegistry;
use Markommerce\Attribute\Reserved\ReservedCodeProvider;
use Markommerce\Attribute\Services\AttributeDefinitionService;
use Markommerce\Catalog\Contracts\CategoryRepositoryInterface;
use Markommerce\Catalog\Contracts\ProductCategoryAssignmentRepositoryInterface;
use Markommerce\Catalog\Contracts\ProductRepositoryInterface;
use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Entity\ProductCategoryAssignment;
use Markommerce\Catalog\Filtering\FilterSelection;
use Markommerce\Catalog\Pagination\CountMode;
use Markommerce\Catalog\Pagination\PaginationPresentation;
use Markommerce\Catalog\Pagination\PaginationStrategyKind;
use Markommerce\Catalog\Pagination\ResolvedPaginationOptions;
use Markommerce\Catalog\Services\CategoryAssignmentService;
use Markommerce\Catalog\Sorting\CategorySortOrderRegistry;
use Markommerce\CatalogAttribute\ProductAttributeAccessor;
use Markommerce\CatalogAttributeIndex\AttributeIndexer;
use Markommerce\CatalogAttributeIndex\Facet\AttributeFacetQuery;
use Markommerce\CatalogAttributeScope\ScopedProductAttributeAccessor;
use Markommerce\Scope\Signature\ScopeSignature;
use Markommerce\Testing\Database\TestConnection;
use Markommerce\Testing\IntegrationTestCase;
use Markommerce\Testing\Profile\StoreProfile;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function layeredNavVendorDir(): string
{
    return dirname(__DIR__, 4) . '/vendor';
}

function makeLayeredNavProfile(): StoreProfile
{
    return StoreProfile::of(
        layeredNavVendorDir(),
        'markommerce/catalog-attribute-storefront',
        'markommerce/catalog-attribute-index',
        'markommerce/locale',
        'marko/database-pgsql',
        'markommerce/attribute-pgsql',
    )->withLocales('default', 'en', 'de');
}

function makeLayeredNavDefinitionService(IntegrationTestCase $testCase): AttributeDefinitionService
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

function makeLayeredNavOffsetOptions(IntegrationTestCase $testCase): ResolvedPaginationOptions
{
    /** @var CategorySortOrderRegistry $registry */
    $registry = $testCase->get(CategorySortOrderRegistry::class);
    $positionOrder = $registry->get('position');
    assert($positionOrder !== null, 'position sort order must be registered');

    return new ResolvedPaginationOptions(
        sortOrder: $positionOrder,
        size: 100,
        page: 1,
        presentation: PaginationPresentation::Numbered,
        strategyKind: PaginationStrategyKind::Offset,
        countMode: CountMode::Exact,
    );
}

/**
 * Seed a color + size attribute, a category, and 4 products:
 *  p1: red, S  (de override for color: rot)
 *  p2: blue, L  (de override for color: blau)
 *  p3: red, L  (de override for color: rot)
 *  p4: blue, S  (no de override for color: stays blue)
 *
 * Products are created in a single save pass with all attribute values already
 * attached, so companion dirty-tracking registers correctly on insert.
 *
 * Returns [categoryId, p1, p2, p3, p4]
 *
 * @return array{0: int, 1: Product, 2: Product, 3: Product, 4: Product}
 */
function seedLayeredNavFixture(IntegrationTestCase $testCase): array
{
    $service = makeLayeredNavDefinitionService($testCase);

    // color attribute — scopable on locale
    $colorDef = new AttributeDefinition();
    $colorDef->code = 'color';
    $colorDef->entityType = 'product';
    $colorDef->type = 'select';
    $colorDef->label = 'Color';
    $colorDef->scopable = true;
    $colorDef->facetable = true;
    $colorDef->config = ['axes' => ['locale']];

    $service->create($colorDef, [
        makeOption('red', 'Red'),
        makeOption('blue', 'Blue'),
        makeOption('rot', 'Rot'),
        makeOption('blau', 'Blau'),
    ]);

    // size attribute — not scopable
    $sizeDef = new AttributeDefinition();
    $sizeDef->code = 'size';
    $sizeDef->entityType = 'product';
    $sizeDef->type = 'select';
    $sizeDef->label = 'Size';
    $sizeDef->scopable = false;
    $sizeDef->facetable = true;
    $sizeDef->config = [];

    $service->create($sizeDef, [
        makeOption('S', 'Small'),
        makeOption('L', 'Large'),
    ]);

    /** @var ProductAttributeAccessor $globalAccessor */
    $globalAccessor = $testCase->get(ProductAttributeAccessor::class);

    /** @var ScopedProductAttributeAccessor $scopedAccessor */
    $scopedAccessor = $testCase->get(ScopedProductAttributeAccessor::class);

    /** @var ProductRepositoryInterface $productRepository */
    $productRepository = $testCase->get(ProductRepositoryInterface::class);

    /** @var CategoryRepositoryInterface $categoryRepository */
    $categoryRepository = $testCase->get(CategoryRepositoryInterface::class);

    /** @var ProductCategoryAssignmentRepositoryInterface $assignmentRepository */
    $assignmentRepository = $testCase->get(ProductCategoryAssignmentRepositoryInterface::class);

    $deSig = ScopeSignature::fromArray(['locale' => 'de']);

    $category = new Category();
    $category->name = 'Layered Nav Test';
    $categoryRepository->save($category);

    // p1: color=red (de: rot), size=S — set ALL attributes before the single save
    $p1 = new Product();
    $p1->sku = 'LN-001';
    $p1->name = 'Layered Nav 001';
    $globalAccessor->set($p1, 'color', 'red');
    $scopedAccessor->setScoped($p1, 'color', 'rot', $deSig);
    $globalAccessor->set($p1, 'size', 'S');
    $productRepository->save($p1);

    // p2: color=blue (de: blau), size=L
    $p2 = new Product();
    $p2->sku = 'LN-002';
    $p2->name = 'Layered Nav 002';
    $globalAccessor->set($p2, 'color', 'blue');
    $scopedAccessor->setScoped($p2, 'color', 'blau', $deSig);
    $globalAccessor->set($p2, 'size', 'L');
    $productRepository->save($p2);

    // p3: color=red (de: rot), size=L
    $p3 = new Product();
    $p3->sku = 'LN-003';
    $p3->name = 'Layered Nav 003';
    $globalAccessor->set($p3, 'color', 'red');
    $scopedAccessor->setScoped($p3, 'color', 'rot', $deSig);
    $globalAccessor->set($p3, 'size', 'L');
    $productRepository->save($p3);

    // p4: color=blue (no de override), size=S
    $p4 = new Product();
    $p4->sku = 'LN-004';
    $p4->name = 'Layered Nav 004';
    $globalAccessor->set($p4, 'color', 'blue');
    $globalAccessor->set($p4, 'size', 'S');
    $productRepository->save($p4);

    // Assign all products to the category
    foreach ([$p1, $p2, $p3, $p4] as $product) {
        $assignment = new ProductCategoryAssignment();
        $assignment->productId = $product->id;
        $assignment->categoryId = $category->id;
        $assignmentRepository->save($assignment);
    }

    // Rebuild the attribute index (full materialization)
    /** @var AttributeIndexer $indexer */
    $indexer = $testCase->get(AttributeIndexer::class);
    $indexer->rebuildAll();

    return [(int) $category->id, $p1, $p2, $p3, $p4];
}

function makeOption(string $value, string $label): AttributeOption
{
    $option = new AttributeOption();
    $option->value = $value;
    $option->label = $label;

    return $option;
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('narrows the category listing to products matching a selected attribute value', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(makeLayeredNavProfile());
    $testCase->setUpIntegration();

    try {
        [$categoryId, $p1, $p2, $p3, $p4] = seedLayeredNavFixture($testCase);

        /** @var CategoryAssignmentService $service */
        $service = $testCase->get(CategoryAssignmentService::class);

        $options = makeLayeredNavOffsetOptions($testCase);

        // Filter: color=red — should return p1 and p3 only
        $selection = new FilterSelection(['color' => ['red']]);
        $page = $service->paginatedProductsInCategory($categoryId, $options, $selection);
        $items = $page->items->toArray();

        expect($items)->toHaveCount(2);

        $returnedIds = array_map(fn (Product $p): mixed => $p->id, $items);
        expect($returnedIds)->toContain($p1->id);
        expect($returnedIds)->toContain($p3->id);
        expect($returnedIds)->not->toContain($p2->id);
        expect($returnedIds)->not->toContain($p4->id);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('ORs multiple values within an attribute and ANDs across attributes', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(makeLayeredNavProfile());
    $testCase->setUpIntegration();

    try {
        [$categoryId, $p1, $p2, $p3, $p4] = seedLayeredNavFixture($testCase);

        /** @var CategoryAssignmentService $service */
        $service = $testCase->get(CategoryAssignmentService::class);

        $options = makeLayeredNavOffsetOptions($testCase);

        // OR within attribute: color=red OR color=blue → all 4 products
        $orSelection = new FilterSelection(['color' => ['red', 'blue']]);
        $orPage = $service->paginatedProductsInCategory($categoryId, $options, $orSelection);
        expect($orPage->items->toArray())->toHaveCount(4);

        // AND across attributes: color=red AND size=L → only p3
        $andSelection = new FilterSelection(['color' => ['red'], 'size' => ['L']]);
        $andPage = $service->paginatedProductsInCategory($categoryId, $options, $andSelection);
        $andItems = $andPage->items->toArray();

        expect($andItems)->toHaveCount(1);
        expect($andItems[0]->id)->toBe($p3->id);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('computes a facet disjunctively while constraining other facets by the selection', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(makeLayeredNavProfile());
    $testCase->setUpIntegration();

    try {
        [$categoryId, $p1, $p2, $p3, $p4] = seedLayeredNavFixture($testCase);

        /** @var AttributeFacetQuery $facetQuery */
        $facetQuery = $testCase->get(AttributeFacetQuery::class);

        // With color=red selected:
        // - color facet is DISJUNCTIVE: ignores its own filter → shows all color values
        //   (red: 2 products p1+p3, blue: 2 products p2+p4)
        // - size facet is constrained by color=red filter → only p1(S) and p3(L) qualify
        //   (S: 1, L: 1)
        $selection = new FilterSelection(['color' => ['red']]);
        $facets = $facetQuery->facets($categoryId, $selection);

        $colorFacet = array_find($facets, fn ($f) => $f->code === 'color');
        $sizeFacet  = array_find($facets, fn ($f) => $f->code === 'size');

        expect($colorFacet)->not->toBeNull();
        expect($sizeFacet)->not->toBeNull();

        // Color facet: disjunctive — all values still shown
        $colorValues = [];
        foreach ($colorFacet->values as $fv) {
            $colorValues[$fv->value] = $fv->count;
        }
        expect($colorValues)->toHaveKey('red')->and($colorValues['red'])->toBe(2);
        expect($colorValues)->toHaveKey('blue')->and($colorValues['blue'])->toBe(2);

        // Size facet: constrained by color=red → only p1 and p3 qualify
        $sizeValues = [];
        foreach ($sizeFacet->values as $fv) {
            $sizeValues[$fv->value] = $fv->count;
        }
        expect($sizeValues)->toHaveKey('S')->and($sizeValues['S'])->toBe(1);
        expect($sizeValues)->toHaveKey('L')->and($sizeValues['L'])->toBe(1);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('resolves facet values and filtering for the active scope', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(makeLayeredNavProfile());
    $testCase->setUpIntegration();

    try {
        [$categoryId, $p1, $p2, $p3, $p4] = seedLayeredNavFixture($testCase);

        /** @var AttributeFacetQuery $facetQuery */
        $facetQuery = $testCase->get(AttributeFacetQuery::class);

        /** @var CategoryAssignmentService $service */
        $service = $testCase->get(CategoryAssignmentService::class);

        $options = makeLayeredNavOffsetOptions($testCase);

        // --- Under locale:de scope ---
        // color index rows at locale:de: p1=rot, p2=blau, p3=rot, p4=blue (p4 has no de override)
        // Under full materialization, p4 gets a locale:de row equal to its base value 'blue'.
        $deFacets = [];
        $testCase->store->inScope(null, 'de', function () use ($facetQuery, $categoryId, &$deFacets): void {
            $deFacets = $facetQuery->facets($categoryId, new FilterSelection());
        });

        $deColorFacet = array_find($deFacets, fn ($f) => $f->code === 'color');
        expect($deColorFacet)->not->toBeNull();

        $deColorValues = [];
        foreach ($deColorFacet->values as $fv) {
            $deColorValues[$fv->value] = $fv->count;
        }

        // Under de: rot=2 (p1, p3), blau=1 (p2), blue=1 (p4 falls back to base 'blue')
        expect($deColorValues)->toHaveKey('rot')->and($deColorValues['rot'])->toBe(2);
        expect($deColorValues)->toHaveKey('blau')->and($deColorValues['blau'])->toBe(1);
        expect($deColorValues)->toHaveKey('blue')->and($deColorValues['blue'])->toBe(1);

        // Filter color=rot under locale:de → should return p1 and p3
        $deFilteredPage = null;
        $testCase->store->inScope(null, 'de', function () use (
            $service, $categoryId, $options, &$deFilteredPage,
        ): void {
            $deFilteredPage = $service->paginatedProductsInCategory(
                $categoryId,
                $options,
                new FilterSelection(['color' => ['rot']]),
            );
        });

        assert($deFilteredPage !== null);
        $deItems = $deFilteredPage->items->toArray();
        expect($deItems)->toHaveCount(2);

        $deReturnedIds = array_map(fn (Product $p): mixed => $p->id, $deItems);
        expect($deReturnedIds)->toContain($p1->id);
        expect($deReturnedIds)->toContain($p3->id);

        // --- Under locale:en scope ---
        // color index rows at locale:en: all products resolve to base 'red' or 'blue'
        // (en has no overrides so falls back to base values)
        $enFacets = [];
        $testCase->store->inScope(null, 'en', function () use ($facetQuery, $categoryId, &$enFacets): void {
            $enFacets = $facetQuery->facets($categoryId, new FilterSelection());
        });

        $enColorFacet = array_find($enFacets, fn ($f) => $f->code === 'color');
        expect($enColorFacet)->not->toBeNull();

        $enColorValues = [];
        foreach ($enColorFacet->values as $fv) {
            $enColorValues[$fv->value] = $fv->count;
        }

        // Under en: red=2 (p1, p3), blue=2 (p2, p4)
        expect($enColorValues)->toHaveKey('red')->and($enColorValues['red'])->toBe(2);
        expect($enColorValues)->toHaveKey('blue')->and($enColorValues['blue'])->toBe(2);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('returns the full listing and facet counts when no filters are selected', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(makeLayeredNavProfile());
    $testCase->setUpIntegration();

    try {
        [$categoryId, $p1, $p2, $p3, $p4] = seedLayeredNavFixture($testCase);

        /** @var CategoryAssignmentService $service */
        $service = $testCase->get(CategoryAssignmentService::class);

        /** @var AttributeFacetQuery $facetQuery */
        $facetQuery = $testCase->get(AttributeFacetQuery::class);

        $options = makeLayeredNavOffsetOptions($testCase);
        $emptySelection = new FilterSelection();

        // Full listing: all 4 products returned
        $page = $service->paginatedProductsInCategory($categoryId, $options, $emptySelection);
        expect($page->items->toArray())->toHaveCount(4);

        // Facets: color has red=2, blue=2; size has S=2, L=2 — at base scope ('')
        $facets = $facetQuery->facets($categoryId, $emptySelection);

        $colorFacet = array_find($facets, fn ($f) => $f->code === 'color');
        $sizeFacet  = array_find($facets, fn ($f) => $f->code === 'size');

        expect($colorFacet)->not->toBeNull();
        expect($sizeFacet)->not->toBeNull();

        // At base scope: color=red: p1+p3=2, color=blue: p2+p4=2
        $colorValues = [];
        foreach ($colorFacet->values as $fv) {
            $colorValues[$fv->value] = $fv->count;
        }
        expect($colorValues)->toHaveKey('red')->and($colorValues['red'])->toBe(2);
        expect($colorValues)->toHaveKey('blue')->and($colorValues['blue'])->toBe(2);

        // size: S=2 (p1+p4), L=2 (p2+p3)
        $sizeValues = [];
        foreach ($sizeFacet->values as $fv) {
            $sizeValues[$fv->value] = $fv->count;
        }
        expect($sizeValues)->toHaveKey('S')->and($sizeValues['S'])->toBe(2);
        expect($sizeValues)->toHaveKey('L')->and($sizeValues['L'])->toBe(2);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');
