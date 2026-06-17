<?php

declare(strict_types=1);

use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepository;
use Marko\Database\Entity\EntityCollection;
use Marko\Routing\Http\Request;
use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Filtering\FilterSelection;
use Markommerce\Catalog\Pagination\PaginationOptionsResolver;
use Markommerce\Catalog\Pagination\ResolvedPaginationOptions;
use Markommerce\Catalog\Pricing\Contracts\PriceResolverInterface;
use Markommerce\Catalog\Pricing\Exceptions\PriceUnavailableException;
use Markommerce\Catalog\Pricing\PriceContext;
use Markommerce\Catalog\Services\CategoryAssignmentService;
use Markommerce\Catalog\Sorting\CategorySortOrderRegistry;
use Markommerce\Catalog\Sorting\ColumnSortOrder;
use Markommerce\Catalog\Tests\Support\CategoryFactory;
use Markommerce\Catalog\Tests\Support\FakeCategoryRepository;
use Markommerce\Catalog\Tests\Support\FakeProductCategoryAssignmentRepository;
use Markommerce\Catalog\Tests\Support\FakeProductRepository;
use Markommerce\CatalogPriceIndex\Contracts\ProductPriceIndexRepositoryInterface;
use Markommerce\CatalogPriceIndex\Entity\ProductPriceIndexEntry;
use Markommerce\CatalogStorefront\Component\ProductCard;
use Markommerce\CatalogStorefront\Component\ProductGridComponent;
use Markommerce\CatalogStorefront\Context\CategoryDataProvider;
use Markommerce\CatalogStorefront\Controller\CategoryController;
use Markommerce\CatalogStorefront\Data\ProductGridData;
use Markommerce\Config\Contracts\ConfigResolverInterface;
use Markommerce\Criteria\Page\Page;
use Markommerce\Criteria\Position\PositionCodec;
use Markommerce\Criteria\Sort\SortDirection;
use Markommerce\Criteria\Strategy\KeysetPaginationStrategy;
use Markommerce\Criteria\Strategy\OffsetPage;
use Markommerce\Currency\CurrencyResolver;
use Markommerce\Layout\Cache\PreparedTree;
use Markommerce\Layout\Cache\PreparedTreeBuilder;
use Markommerce\Layout\Compiler\Compiler;
use Markommerce\Layout\Compiler\ResolutionPhase;
use Markommerce\Layout\Compiler\ValidationPhase;
use Markommerce\Layout\Contracts\ContextProvider;
use Markommerce\Layout\Discovery\LayoutDiscovery;
use Markommerce\Layout\Layout;
use Markommerce\Money\Currency;
use Markommerce\Money\Money;
use Markommerce\MoneyIntl\MoneyFormatter;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Registry\ScopeRegistryInterface;
use Markommerce\Testing\IntegrationTestCase;
use Markommerce\Testing\Profile\StoreProfile;

// ─── Harness helpers ──────────────────────────────────────────────────────────

function categoryLayoutVendorDir(): string
{
    // __DIR__ = packages/catalog-storefront/tests/Feature
    // dirname 4 levels up = markommerce root
    return dirname(__DIR__, 4) . '/vendor';
}

function categoryLayoutEnsureConfigKey(): void
{
    if ((string) (getenv('MARKOMMERCE_CONFIG_SECRET_KEY') ?: '') === '') {
        $testKey = base64_encode(str_repeat("\x01", SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
        putenv('MARKOMMERCE_CONFIG_SECRET_KEY=' . $testKey);
    }
}

function categoryLayoutMakeTestCase(): IntegrationTestCase
{
    categoryLayoutEnsureConfigKey();

    return new IntegrationTestCase(
        StoreProfile::storefront(categoryLayoutVendorDir()),
    );
}

// Helpers that do not need the DB (compile-time layout inspection)

function categoryLayoutLoadLayoutFile(): Layout
{
    $path = dirname(__DIR__, 2) . '/layout/category_show.php';

    return require $path;
}

function categoryLayoutBuildCompiler(): Compiler
{
    $catalogPath = dirname(__DIR__, 2);

    $moduleRepository = new ModuleRepository([
        new ModuleManifest(
            name: 'markommerce/catalog-storefront',
            version: '1.0.0',
            path: $catalogPath,
            source: 'vendor',
        ),
    ]);

    $layoutDiscovery = new LayoutDiscovery($moduleRepository);
    $resolutionPhase = new ResolutionPhase();
    $validationPhase = new ValidationPhase();
    $treeBuilder = new PreparedTreeBuilder();

    return new Compiler($layoutDiscovery, $resolutionPhase, $validationPhase, $treeBuilder);
}

function categoryLayoutMakeScopeContext(): ScopeContext
{
    $registry = new class () implements ScopeRegistryInterface
    {
        public function hasAxis(string $name): bool
        {
            return false;
        }

        public function getAxis(string $name): ScopeAxis
        {
            throw UnknownAxisException::forAxis($name);
        }

        /** @return list<string> */
        public function listAxes(): array
        {
            return [];
        }

        public function getHierarchy(string $axisName): ScopeHierarchy
        {
            throw UnknownAxisException::forAxis($axisName);
        }
    };

    return new ScopeContext($registry);
}

function categoryLayoutMakeNoPricePriceResolver(): PriceResolverInterface
{
    return new class () implements PriceResolverInterface
    {
        public function resolve(PriceContext $context): Money
        {
            throw PriceUnavailableException::forContext($context);
        }
    };
}

function categoryLayoutMakeEmptyPriceIndexRepository(): ProductPriceIndexRepositoryInterface
{
    return new class () implements ProductPriceIndexRepositoryInterface
    {
        public function upsertMany(array $entries): void {}

        public function findByProductId(int $productId): ?ProductPriceIndexEntry
        {
            return null;
        }

        /** @return list<ProductPriceIndexEntry> */
        public function findByProductIds(array $productIds): array
        {
            return [];
        }

        public function truncate(): void {}
    };
}

function categoryLayoutMakeCurrencyResolver(): CurrencyResolver
{
    $currency = new Currency(code: 'USD', scale: 2, symbol: '$', name: 'US Dollar');

    return new class ($currency) extends CurrencyResolver
    {
        public function __construct(private readonly Currency $currency) {}

        public function base(): Currency
        {
            return $this->currency;
        }
    };
}

/**
 * @param array<string, mixed> $values
 */
function categoryLayoutMakeConfigResolver(array $values = []): ConfigResolverInterface
{
    $defaults = [
        'defaultPageSize' => 24, 'allowedPageSizes' => [12, 24], 'maxPageSize' => 96,
        'strategy' => 'offset', 'presentation' => 'numbered', 'countMode' => 'exact',
        'maxPageDepth' => 100, 'defaultSort' => 'position', 'enabledSorts' => [],
        'viewAllThreshold' => 0, 'countCacheTtl' => 0,
    ];

    $merged = array_merge($defaults, $values);

    return new class ($merged) implements ConfigResolverInterface
    {
        /** @param array<string, mixed> $values */
        public function __construct(private readonly array $values) {}

        public function resolved(
            string $configClass,
            string $field,
        ): mixed
        {
            return $this->values[$field] ?? null;
        }
    };
}

function categoryLayoutMakePaginationOptionsResolver(): PaginationOptionsResolver
{
    $sortRegistry = new CategorySortOrderRegistry();
    $sortRegistry->register(new ColumnSortOrder(
        key: 'position',
        label: 'Position',
        column: 'catalog_product_category.position',
        direction: SortDirection::Ascending,
        supportsKeyset: false,
    ), 0);

    return new PaginationOptionsResolver(categoryLayoutMakeConfigResolver(), $sortRegistry);
}

function categoryLayoutMakeAssignmentService(
    FakeProductRepository $productRepository,
    FakeCategoryRepository $categoryRepository,
    FakeProductCategoryAssignmentRepository $assignmentRepository,
): CategoryAssignmentService {
    $positionCodec = new PositionCodec();

    return new class (
        $productRepository,
        $categoryRepository,
        $assignmentRepository,
        $positionCodec,
        new KeysetPaginationStrategy($positionCodec),
    ) extends CategoryAssignmentService
    {
        public function paginatedProductsInCategory(
            int $categoryId,
            ResolvedPaginationOptions $options,
            FilterSelection $filters = new FilterSelection(),
        ): Page
        {
            $products = $this->productsInCategory($categoryId);

            return new OffsetPage(
                items: new EntityCollection($products),
                size: $options->size,
                nextPosition: null,
                previousPosition: null,
                currentPage: $options->page,
                totalPages: 1,
                totalItems: count($products),
                positionCodec: new PositionCodec(),
            );
        }
    };
}

// ─── Pure compile-time / reflection tests (no DB needed) ──────────────────────

it('defines a category_show layout for the CategoryController show action', function (): void {
    $layout = categoryLayoutLoadLayoutFile();

    expect($layout)->toBeInstanceOf(Layout::class);
    expect($layout->handle)->toBe([CategoryController::class, 'show']);
});

it('compiles the category_show layout without error', function (): void {
    $compiler = categoryLayoutBuildCompiler();
    $trees = $compiler->compile();

    $handleKey = CategoryController::class . '::show';
    expect($trees)->toHaveKey($handleKey);
    expect($trees[$handleKey])->toBeInstanceOf(PreparedTree::class);
});

it('exposes the product grid component without a hardcoded handle or slot', function (): void {
    $reflection = new ReflectionClass(ProductGridComponent::class);

    $markoComponentClass = 'Marko\Layout\Attributes\Component';
    if (class_exists($markoComponentClass)) {
        $attributes = $reflection->getAttributes($markoComponentClass);
        if (!empty($attributes)) {
            $attr = $attributes[0]->newInstance();
            // Verify handle/slot are not set on the component attribute.
            // Use a reflection check since these properties may not be declared on the
            // attribute instance (different marko versions may differ in their schema).
            $attrReflection = new ReflectionObject($attr);
            $handleValue = $attrReflection->hasProperty('handle')
                ? $attrReflection->getProperty('handle')->getValue($attr)
                : null;
            $slotValue = $attrReflection->hasProperty('slot')
                ? $attrReflection->getProperty('slot')->getValue($attr)
                : null;
            expect($handleValue)->toBeNull();
            expect($slotValue)->toBeNull();
        }
    }

    $source = file_get_contents(
        dirname(__DIR__, 2) . '/src/Component/ProductGridComponent.php',
    );
    expect($source)->not->toContain("handle: [CategoryController::class, 'show']");
    expect($source)->not->toContain("slot: 'content'");
});

it('returns a typed ProductGridData DTO from the grid component data method', function (): void {
    // This test exercises ProductGridComponent::data() in isolation using fake repos —
    // it verifies the DTO return type, not rendering. Fakes are still valid here since
    // we're testing the component's data method signature, not DB integration.
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Test';
    $categoryRepository->save($category);

    $assignmentService = categoryLayoutMakeAssignmentService(
        $productRepository,
        $categoryRepository,
        $assignmentRepository,
    );

    $component = new ProductGridComponent(
        $assignmentService,
        categoryLayoutMakePaginationOptionsResolver(),
        categoryLayoutMakeNoPricePriceResolver(),
        new MoneyFormatter(categoryLayoutMakeScopeContext()),
        categoryLayoutMakeEmptyPriceIndexRepository(),
        categoryLayoutMakeCurrencyResolver(),
    );
    $data = $component->data($category, 1, 0, '');

    expect($data)->toBeInstanceOf(ProductGridData::class);
    expect($data->products)->toBeArray();
});

it('loads the category via a context provider instead of inside the component', function (): void {
    $reflection = new ReflectionClass(CategoryDataProvider::class);
    expect($reflection->implementsInterface(ContextProvider::class))->toBeTrue();

    $gridReflection = new ReflectionClass(ProductGridComponent::class);
    $dataMethod = $gridReflection->getMethod('data');
    $params = $dataMethod->getParameters();

    $paramNames = array_map(fn ($p) => $p->getName(), $params);
    $paramTypes = array_map(function (ReflectionParameter $p): ?string {
        $type = $p->getType();

        return $type instanceof ReflectionNamedType ? $type->getName() : null;
    }, $params);
    expect($paramTypes)->toContain(Category::class);
    expect($paramNames[0])->toBe('category');
    expect($paramNames)->toContain('page');
    expect($paramNames)->toContain('size');
    expect($paramNames)->toContain('sort');
});

it('returns 404 from the controller when the category does not exist', function (): void {
    $categoryRepository = new FakeCategoryRepository();

    $controller = new CategoryController($categoryRepository);
    $request = new Request(
        server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/catalog/category/9999'],
    );
    $response = $controller->show(9999, $request);

    expect($response->statusCode())->toBe(404);
});

it('no longer carries the marko/layout Layout attribute on CategoryController', function (): void {
    $reflection = new ReflectionClass(CategoryController::class);

    $markoLayoutClass = 'Marko\Layout\Attributes\Layout';
    $attributes = $reflection->getAttributes($markoLayoutClass);

    expect($attributes)->toBeEmpty();
});

it('no longer depends on marko/layout in composer.json', function (): void {
    $manifest = json_decode(
        (string) file_get_contents(dirname(__DIR__, 2) . '/composer.json'),
        true,
    );

    expect($manifest['require'])->not->toHaveKey('marko/layout');
    expect($manifest['require'])->toHaveKey('markommerce/layout');
});

it(
    'migrates CategoryLayoutTest asserting the REAL rendered layout HTML (mk-* markup, not the fake template-name string)',
    function (): void {
        IntegrationTestCase::skipIfUnavailable();

        $testCase = categoryLayoutMakeTestCase();
        $testCase->setUpIntegration();

        try {
            $store = $testCase->store;

            $category = CategoryFactory::new($store)->withName('Real Layout Category')->create();

            $request = new Request([
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/catalog/category/' . $category->id,
                'HTTP_HOST' => 'localhost',
            ]);
            $response = $store->handle($request);

            expect($response->statusCode())->toBe(200);
            // Real Latte product-grid.latte emits <mk-stack> and category heading
            expect($response->body())->toContain('<mk-stack');
            expect($response->body())->toContain('Real Layout Category');
            // Empty category: no <mk-grid>, but the empty-state message renders
            expect($response->body())->toContain('No products found');
            // Absolutely no fake-view artifact strings in real output
            expect($response->body())->not->toContain('catalog-storefront::components/product-grid');
        } finally {
            $testCase->tearDownIntegration();
        }
    },
)->group('integration-destructive');
