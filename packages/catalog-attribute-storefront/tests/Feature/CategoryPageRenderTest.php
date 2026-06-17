<?php

declare(strict_types=1);

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
use Markommerce\CatalogAttribute\ProductAttributeAccessor;
use Markommerce\CatalogAttributeIndex\AttributeIndexer;
use Marko\Routing\Http\Request;
use Markommerce\Testing\IntegrationTestCase;
use Markommerce\Testing\Profile\StoreProfile;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function categoryRenderVendorDir(): string
{
    // __DIR__ = packages/catalog-attribute-storefront/tests/Feature
    // 4 levels up = markommerce root
    return dirname(__DIR__, 4) . '/vendor';
}

function categoryRenderEnsureConfigKey(): void
{
    if ((string) (getenv('MARKOMMERCE_CONFIG_SECRET_KEY') ?: '') === '') {
        $testKey = base64_encode(str_repeat("\x01", SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
        putenv('MARKOMMERCE_CONFIG_SECRET_KEY=' . $testKey);
    }
}

function categoryRenderMakeProfile(): StoreProfile
{
    categoryRenderEnsureConfigKey();

    return StoreProfile::of(
        categoryRenderVendorDir(),
        'markommerce/catalog-attribute-storefront',
        'markommerce/theme-blank',
        'marko/database-pgsql',
        'markommerce/config-pgsql',
        'markommerce/locale',
        'markommerce/attribute-pgsql',
    )->withConfigOverrides([
        'vite' => [
            'useDevServer' => true,
            'devServerUrl' => 'http://localhost:5173',
            'entry' => 'packages/frontend/resources/js/main.ts',
        ],
    ]);
}

function categoryRenderMakeTestCase(): IntegrationTestCase
{
    return new IntegrationTestCase(categoryRenderMakeProfile());
}

function categoryRenderMakeDefinitionService(IntegrationTestCase $testCase): AttributeDefinitionService
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

function categoryRenderMakeOption(string $value, string $label): AttributeOption
{
    $option = new AttributeOption();
    $option->value = $value;
    $option->label = $label;

    return $option;
}

/**
 * Seed a color attribute, a category, and two products (one red, one blue).
 * Rebuilds the attribute index.
 * Returns [categoryId].
 *
 * @return int
 */
function categoryRenderSeedFacetableFixture(IntegrationTestCase $testCase): int
{
    $service = categoryRenderMakeDefinitionService($testCase);

    $colorDef = new AttributeDefinition();
    $colorDef->code = 'color';
    $colorDef->entityType = 'product';
    $colorDef->type = 'select';
    $colorDef->label = 'Color';
    $colorDef->scopable = false;
    $colorDef->facetable = true;
    $colorDef->config = [];

    $service->create($colorDef, [
        categoryRenderMakeOption('red', 'Red'),
        categoryRenderMakeOption('blue', 'Blue'),
    ]);

    /** @var ProductAttributeAccessor $globalAccessor */
    $globalAccessor = $testCase->get(ProductAttributeAccessor::class);

    /** @var ProductRepositoryInterface $productRepository */
    $productRepository = $testCase->get(ProductRepositoryInterface::class);

    /** @var CategoryRepositoryInterface $categoryRepository */
    $categoryRepository = $testCase->get(CategoryRepositoryInterface::class);

    /** @var ProductCategoryAssignmentRepositoryInterface $assignmentRepository */
    $assignmentRepository = $testCase->get(ProductCategoryAssignmentRepositoryInterface::class);

    $category = new Category();
    $category->name = 'Faceted Category';
    $categoryRepository->save($category);

    $p1 = new Product();
    $p1->sku = 'CR-001';
    $p1->name = 'Red Product';
    $globalAccessor->set($p1, 'color', 'red');
    $productRepository->save($p1);

    $p2 = new Product();
    $p2->sku = 'CR-002';
    $p2->name = 'Blue Product';
    $globalAccessor->set($p2, 'color', 'blue');
    $productRepository->save($p2);

    foreach ([$p1, $p2] as $product) {
        $assignment = new ProductCategoryAssignment();
        $assignment->productId = $product->id;
        $assignment->categoryId = $category->id;
        $assignmentRepository->save($assignment);
    }

    /** @var AttributeIndexer $indexer */
    $indexer = $testCase->get(AttributeIndexer::class);
    $indexer->rebuildAll();

    return (int) $category->id;
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('renders the facet sidebar in the sidebar-left column of the category page', function (): void {
    IntegrationTestCase::skipIfUnavailable();

    $testCase = categoryRenderMakeTestCase();
    $testCase->setUpIntegration();

    try {
        $categoryId = categoryRenderSeedFacetableFixture($testCase);

        $request = new Request([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/catalog/category/' . $categoryId,
            'HTTP_HOST' => 'localhost',
        ]);
        $response = $testCase->store->handle($request);

        expect($response->statusCode())->toBe(200);
        // 2columns-left.latte wraps sidebar-left slot in <aside>
        expect($response->body())->toContain('<aside>');
        // The facet sidebar component renders inside the aside/sidebar-left column
        expect($response->body())->toContain('catalog-facet-sidebar');
        // Facet groups appear for the color attribute
        expect($response->body())->toContain('catalog-facet-sidebar__group');
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('renders the product grid in the content column alongside the sidebar', function (): void {
    IntegrationTestCase::skipIfUnavailable();

    $testCase = categoryRenderMakeTestCase();
    $testCase->setUpIntegration();

    try {
        $categoryId = categoryRenderSeedFacetableFixture($testCase);

        $request = new Request([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/catalog/category/' . $categoryId,
            'HTTP_HOST' => 'localhost',
        ]);
        $response = $testCase->store->handle($request);

        expect($response->statusCode())->toBe(200);
        // product-grid.latte wraps everything in <mk-stack>
        expect($response->body())->toContain('<mk-stack');
        // 2columns-left.latte wraps the two-column layout in <mk-sidebar>
        expect($response->body())->toContain('<mk-sidebar>');
        // The grid appears alongside the sidebar in the content slot
        expect($response->body())->toContain('catalog-product-grid');
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('shows selected facet values and an active-filter chip for the current selection', function (): void {
    IntegrationTestCase::skipIfUnavailable();

    $testCase = categoryRenderMakeTestCase();
    $testCase->setUpIntegration();

    try {
        $categoryId = categoryRenderSeedFacetableFixture($testCase);

        // Request with color=red selected; pass filter as the query array (the layout
        // source resolver reads from request->query(), not from the REQUEST_URI string).
        $request = new Request(
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/catalog/category/' . $categoryId,
                'HTTP_HOST' => 'localhost',
            ],
            query: ['filter' => ['color' => ['red']]],
        );
        $response = $testCase->store->handle($request);

        expect($response->statusCode())->toBe(200);
        // The selected facet value gets the --selected modifier class
        expect($response->body())->toContain('catalog-facet-sidebar__value--selected');
        // The selected row gets aria-current="true"
        expect($response->body())->toContain('aria-current="true"');
        // The active-filter chip area renders
        expect($response->body())->toContain('catalog-facet-active__chip');
        // The "Clear all" link renders when there is an active selection
        expect($response->body())->toContain('catalog-facet-active__clear');
        expect($response->body())->toContain('Clear all');
        // The clear-all href points back to the category without filter params
        expect($response->body())->toContain('href="/catalog/category/' . $categoryId . '"');
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('renders the category page without a broken sidebar when no facets exist', function (): void {
    IntegrationTestCase::skipIfUnavailable();

    $testCase = categoryRenderMakeTestCase();
    $testCase->setUpIntegration();

    try {
        /** @var CategoryRepositoryInterface $categoryRepository */
        $categoryRepository = $testCase->get(CategoryRepositoryInterface::class);

        $category = new Category();
        $category->name = 'Empty Facets Category';
        $categoryRepository->save($category);

        $request = new Request([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/catalog/category/' . $category->id,
            'HTTP_HOST' => 'localhost',
        ]);
        $response = $testCase->store->handle($request);

        expect($response->statusCode())->toBe(200);
        // The page still renders with a 200 (no crash)
        expect($response->body())->toContain('<mk-stack');
        // The sidebar wrapper is present (from 2columns-left.latte)
        expect($response->body())->toContain('<aside>');
        // No facet group chrome rendered when no facetable values exist
        expect($response->body())->not->toContain('catalog-facet-sidebar__group');
        // No active-filter chips when there is no selection
        expect($response->body())->not->toContain('catalog-facet-active__chip');
        // No "Clear all" link when nothing is selected
        expect($response->body())->not->toContain('catalog-facet-active__clear');
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');
