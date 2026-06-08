<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepository;
use Marko\Database\Entity\EntityCollection;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\RouteCollection;
use Marko\Routing\RouteDiscovery;
use Marko\Routing\RouteMatcher;
use Marko\Routing\RouteMatcherInterface;
use Marko\Routing\Router;
use Marko\View\ViewInterface;
use Markommerce\Catalog\Contracts\CategoryRepositoryInterface;
use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Pagination\PaginationOptionsResolver;
use Markommerce\Catalog\Pagination\ResolvedPaginationOptions;
use Markommerce\Catalog\Sorting\CategorySortOrderRegistry;
use Markommerce\Catalog\Sorting\ColumnSortOrder;
use Markommerce\Criteria\Sort\SortDirection;
use Markommerce\Catalog\Pricing\Contracts\PriceResolverInterface;
use Markommerce\Catalog\Pricing\Exceptions\PriceUnavailableException;
use Markommerce\Catalog\Pricing\PriceContext;
use Markommerce\Catalog\Services\CategoryAssignmentService;
use Markommerce\Catalog\Tests\Support\FakeCategoryRepository;
use Markommerce\Catalog\Tests\Support\FakeProductCategoryAssignmentRepository;
use Markommerce\Catalog\Tests\Support\FakeProductRepository;
use Markommerce\CatalogPriceIndex\Contracts\ProductPriceIndexRepositoryInterface;
use Markommerce\CatalogPriceIndex\Entity\ProductPriceIndexEntry;
use Markommerce\CatalogStorefront\Component\ProductCard;
use Markommerce\CatalogStorefront\Component\ProductGridComponent;
use Markommerce\CatalogStorefront\Component\StockBadge;
use Markommerce\CatalogStorefront\Context\CategoryDataProvider;
use Markommerce\CatalogStorefront\Controller\CategoryController;
use Markommerce\Config\Contracts\ConfigResolverInterface;
use Markommerce\Criteria\Page\Page;
use Markommerce\Criteria\Position\PositionCodec;
use Markommerce\Criteria\Strategy\KeysetPaginationStrategy;
use Markommerce\Criteria\Strategy\OffsetPage;
use Markommerce\Currency\CurrencyResolver;
use Markommerce\Layout\Cache\ArtifactReaderInterface;
use Markommerce\Layout\Cache\PreparedTree;
use Markommerce\Layout\Cache\PreparedTreeBuilder;
use Markommerce\Layout\Compiler\Compiler;
use Markommerce\Layout\Compiler\ResolutionPhase;
use Markommerce\Layout\Compiler\ValidationPhase;
use Markommerce\Layout\Discovery\LayoutDiscovery;
use Markommerce\Layout\Middleware\MarkommerceLayoutMiddleware;
use Markommerce\Layout\Runtime\Renderer;
use Markommerce\Money\Currency;
use Markommerce\Money\Money;
use Markommerce\MoneyIntl\MoneyFormatter;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Registry\ScopeRegistryInterface;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function catalogSeoMakeEmptyPriceIndexRepository(): ProductPriceIndexRepositoryInterface
{
    return new class () implements ProductPriceIndexRepositoryInterface
    {
        public function upsertMany(array $entries): void {}

        public function findByProductId(int $productId): ?ProductPriceIndexEntry
        {
            return null;
        }

        public function findByProductIds(array $productIds): array
        {
            return [];
        }

        public function truncate(): void {}
    };
}

function catalogSeoMakeCurrencyResolver(): CurrencyResolver
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
 * @param array<string, mixed> $overrides
 */
function catalogSeoMakeConfigResolver(array $overrides = []): ConfigResolverInterface
{
    $defaults = [
        'defaultPageSize'  => 24,
        'allowedPageSizes' => [12, 24, 48, 96],
        'maxPageSize'      => 96,
        'strategy'         => 'offset',
        'presentation'     => 'numbered',
        'countMode'        => 'exact',
        'maxPageDepth'     => 10,
        'defaultSort'      => 'position',
        'enabledSorts'     => [],
        'viewAllThreshold' => 0,
        'countCacheTtl'    => 0,
    ];

    $values = array_merge($defaults, $overrides);

    return new class ($values) implements ConfigResolverInterface
    {
        /** @param array<string, mixed> $values */
        public function __construct(private readonly array $values) {}

        public function resolved(string $configClass, string $field): mixed
        {
            return $this->values[$field] ?? null;
        }
    };
}

function catalogSeoMakeSortRegistry(): CategorySortOrderRegistry
{
    $registry = new CategorySortOrderRegistry();
    $registry->register(new ColumnSortOrder(
        key: 'position',
        label: 'Position',
        column: 'catalog_product_category.position',
        direction: SortDirection::Ascending,
        supportsKeyset: false,
    ), 0);

    return $registry;
}

function catalogSeoMakePaginationOptionsResolver(array $overrides = []): PaginationOptionsResolver
{
    return new PaginationOptionsResolver(catalogSeoMakeConfigResolver($overrides), catalogSeoMakeSortRegistry());
}

function catalogSeoMakeScopeContext(): ScopeContext
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

function catalogSeoMakeMoneyFormatter(): MoneyFormatter
{
    return new MoneyFormatter(catalogSeoMakeScopeContext());
}

function catalogSeoMakeNoPricePriceResolver(): PriceResolverInterface
{
    return new class () implements PriceResolverInterface
    {
        public function resolve(PriceContext $context): Money
        {
            throw PriceUnavailableException::forContext($context);
        }
    };
}

/**
 * Build a CategoryAssignmentService backed by the in-memory fakes.
 *
 * @param int $totalItems total number of products in the category (drives view-all logic)
 */
function catalogSeoMakeAssignmentService(
    FakeProductRepository $productRepository,
    FakeCategoryRepository $categoryRepository,
    FakeProductCategoryAssignmentRepository $assignmentRepository,
    int $totalItems = 0,
): CategoryAssignmentService {
    $positionCodec = new PositionCodec();

    return new class (
        $productRepository,
        $categoryRepository,
        $assignmentRepository,
        $positionCodec,
        new KeysetPaginationStrategy($positionCodec),
        $totalItems,
    ) extends CategoryAssignmentService
    {
        public function __construct(
            FakeProductRepository $productRepository,
            FakeCategoryRepository $categoryRepository,
            FakeProductCategoryAssignmentRepository $assignmentRepository,
            PositionCodec $positionCodec,
            KeysetPaginationStrategy $keysetPaginationStrategy,
            private readonly int $overrideTotalItems,
        ) {
            parent::__construct(
                $productRepository,
                $categoryRepository,
                $assignmentRepository,
                $positionCodec,
                $keysetPaginationStrategy,
            );
        }

        public function paginatedProductsInCategory(int $categoryId, ResolvedPaginationOptions $options): Page
        {
            $products = $this->productsInCategory($categoryId);

            return new OffsetPage(
                items: new EntityCollection($products),
                size: $options->size,
                nextPosition: null,
                previousPosition: null,
                currentPage: $options->page,
                totalPages: 1,
                totalItems: $this->overrideTotalItems > 0 ? $this->overrideTotalItems : count($products),
                positionCodec: new PositionCodec(),
            );
        }
    };
}

/**
 * @return array<string, PreparedTree>
 */
function catalogSeoBuildArtifact(): array
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
    $compiler = new Compiler($layoutDiscovery, $resolutionPhase, $validationPhase, $treeBuilder);

    return $compiler->compile();
}

class CatalogSeoFakeView implements ViewInterface
{
    public function render(string $template, array $data = []): Response
    {
        return Response::html($this->renderToString($template, $data));
    }

    public function renderToString(string $template, array $data = []): string
    {
        $output = '<div data-template="' . htmlspecialchars($template) . '"';

        foreach ($data as $key => $value) {
            if (is_string($value) || is_int($value) || is_float($value) || is_bool($value)) {
                $output .= ' data-' . htmlspecialchars($key) . '="' . htmlspecialchars((string) $value) . '"';
            } elseif (is_object($value) && method_exists($value, '__toString')) {
                $output .= ' data-' . htmlspecialchars($key) . '="' . htmlspecialchars((string) $value) . '"';
            } elseif (is_object($value)) {
                if (property_exists($value, 'name') && is_string($value->name)) {
                    $output .= ' data-' . htmlspecialchars($key) . '-name="' . htmlspecialchars($value->name) . '"';
                }
            } elseif (is_array($value)) {
                foreach ($value as $v) {
                    if (is_string($v)) {
                        $output .= ' data-array-item="' . htmlspecialchars($v) . '"';
                    }
                }
            }
        }

        $slots = '';
        if (isset($data['_slots']) && is_array($data['_slots'])) {
            foreach (array_keys($data['_slots']) as $slotName) {
                $slots .= "{slot $slotName}{/slot}";
            }
        }

        $output .= ">$slots</div>";

        return $output;
    }
}

class CatalogSeoFakeContainer implements ContainerInterface
{
    /** @var array<string, object> */
    private array $bindings = [];

    public function bind(string $class, object $instance): void
    {
        $this->bindings[$class] = $instance;
    }

    public function get(string $id): mixed
    {
        if (isset($this->bindings[$id])) {
            return $this->bindings[$id];
        }
        if (class_exists($id)) {
            return new $id();
        }
        throw new RuntimeException("No binding for $id");
    }

    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) || class_exists($id);
    }

    public function singleton(string $id): void {}

    public function instance(string $id, object $instance): void
    {
        $this->bindings[$id] = $instance;
    }

    public function call(Closure $callable): mixed
    {
        return $callable();
    }
}

/**
 * @param array<string, mixed> $configOverrides
 */
function catalogSeoBuildRouter(
    FakeCategoryRepository $categoryRepository,
    FakeProductRepository $productRepository,
    FakeProductCategoryAssignmentRepository $assignmentRepository,
    array $configOverrides = [],
    int $totalItems = 0,
): Router {
    $routes = new RouteCollection();
    $discovery = new RouteDiscovery();
    foreach ($discovery->discoverFromClass(CategoryController::class) as $route) {
        $routes->add($route);
    }
    $matcher = new RouteMatcher($routes);

    $assignmentService = catalogSeoMakeAssignmentService(
        $productRepository,
        $categoryRepository,
        $assignmentRepository,
        $totalItems,
    );

    $paginationOptionsResolver = catalogSeoMakePaginationOptionsResolver($configOverrides);

    $container = new CatalogSeoFakeContainer();
    $container->instance(RouteMatcherInterface::class, $matcher);
    $container->instance(CategoryRepositoryInterface::class, $categoryRepository);
    $configResolver = catalogSeoMakeConfigResolver($configOverrides);
    $container->instance(
        CategoryController::class,
        new CategoryController($categoryRepository, $paginationOptionsResolver, $configResolver, $assignmentService),
    );
    $container->instance(CategoryAssignmentService::class, $assignmentService);

    $priceResolver = catalogSeoMakeNoPricePriceResolver();
    $moneyFormatter = catalogSeoMakeMoneyFormatter();

    $productGridComponent = new ProductGridComponent(
        $assignmentService,
        $paginationOptionsResolver,
        $priceResolver,
        $moneyFormatter,
        catalogSeoMakeEmptyPriceIndexRepository(),
        catalogSeoMakeCurrencyResolver(),
    );
    $container->instance(ProductGridComponent::class, $productGridComponent);
    $container->instance(ProductCard::class, new ProductCard($priceResolver, $moneyFormatter));
    $container->instance(StockBadge::class, new StockBadge());

    $categoryDataProvider = new CategoryDataProvider($categoryRepository);
    $container->instance(CategoryDataProvider::class, $categoryDataProvider);

    $trees = catalogSeoBuildArtifact();
    $view = new CatalogSeoFakeView();

    $artifactReader = new class ($trees) implements ArtifactReaderInterface
    {
        /** @param array<string, PreparedTree> $trees */
        public function __construct(private array $trees) {}

        public function read(): array
        {
            return $this->trees;
        }
    };

    $renderer = new Renderer($view, $container);
    $layoutMiddleware = new MarkommerceLayoutMiddleware($matcher, $artifactReader, $renderer, $container);
    $container->instance(MarkommerceLayoutMiddleware::class, $layoutMiddleware);

    return new Router($matcher, $container, [MarkommerceLayoutMiddleware::class]);
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('normalizes size and sort params in the canonical url', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Electronics';
    $categoryRepository->save($category);

    // defaultPageSize=24, defaultSort=position — requesting defaults should omit them from canonical
    $router = catalogSeoBuildRouter($categoryRepository, $productRepository, $assignmentRepository);

    // Request with explicit defaults: page=1 (default), size=24 (default), sort=position (default)
    $request = new Request(
        server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI'    => '/catalog/category/' . $category->id . '?page=1&size=24&sort=position',
            'HTTP_HOST'      => 'example.com',
        ],
        query: ['page' => '1', 'size' => '24', 'sort' => 'position'],
    );
    $response = $router->handle($request);

    expect($response->statusCode())->toBe(200);
    $canonical = $response->headers()['Link'] ?? null;
    expect($canonical)->not->toBeNull();
    // page=1 is default — canonical should NOT include page param
    expect($canonical)->not->toContain('page=1');
    // size=24 is the default page size — should NOT be included in canonical
    expect($canonical)->not->toContain('size=24');
    // sort=position is the default — should NOT be included in canonical
    expect($canonical)->not->toContain('sort=position');
    // Canonical URL should be just the bare category path
    expect($canonical)->toContain('/catalog/category/' . $category->id . '>');
});

it('renders all products on one page when under the view-all threshold', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Small Category';
    $categoryRepository->save($category);

    // Add 3 products
    for ($i = 1; $i <= 3; $i++) {
        $product = new Product();
        $product->sku = 'SKU-' . $i;
        $product->name = 'Product ' . $i;
        $productRepository->save($product);
        $assignmentService = catalogSeoMakeAssignmentService(
            $productRepository,
            $categoryRepository,
            new FakeProductCategoryAssignmentRepository(),
        );
        $assignmentService->assign($product->id, $category->id);
    }

    // viewAllThreshold=5 means categories with <= 5 products support view=all
    $router = catalogSeoBuildRouter(
        $categoryRepository,
        $productRepository,
        $assignmentRepository,
        configOverrides: ['viewAllThreshold' => 5, 'defaultPageSize' => 2],
        totalItems: 3,
    );

    $request = new Request(
        server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI'    => '/catalog/category/' . $category->id . '?view=all',
            'HTTP_HOST'      => 'example.com',
        ],
        query: ['view' => 'all'],
    );
    $response = $router->handle($request);

    // View-all should return a 200 with all products rendered on one page
    expect($response->statusCode())->toBe(200);
    // The canonical should point to the view=all URL
    $canonical = $response->headers()['Link'] ?? null;
    expect($canonical)->not->toBeNull();
    expect($canonical)->toContain('view=all');
});

it('canonicalizes paginated pages to the view-all url when view-all is active', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Small Category';
    $categoryRepository->save($category);

    // viewAllThreshold=5, category has 3 products — qualifies for view=all
    // A paginated request (page=2) should get a canonical pointing to view=all
    $router = catalogSeoBuildRouter(
        $categoryRepository,
        $productRepository,
        $assignmentRepository,
        configOverrides: ['viewAllThreshold' => 5, 'defaultPageSize' => 2],
        totalItems: 3,
    );

    $request = new Request(
        server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI'    => '/catalog/category/' . $category->id . '?page=2',
            'HTTP_HOST'      => 'example.com',
        ],
        query: ['page' => '2'],
    );
    $response = $router->handle($request);

    expect($response->statusCode())->toBe(200);
    $canonical = $response->headers()['Link'] ?? null;
    expect($canonical)->not->toBeNull();
    // Paginated page should canonicalize to view=all when threshold allows
    expect($canonical)->toContain('view=all');
    expect($canonical)->not->toContain('page=2');
});

it('ignores the view-all param when the threshold is disabled', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Category';
    $categoryRepository->save($category);

    // viewAllThreshold=0 means disabled
    $router = catalogSeoBuildRouter(
        $categoryRepository,
        $productRepository,
        $assignmentRepository,
        configOverrides: ['viewAllThreshold' => 0],
    );

    $request = new Request(
        server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI'    => '/catalog/category/' . $category->id . '?view=all',
            'HTTP_HOST'      => 'example.com',
        ],
        query: ['view' => 'all'],
    );
    $response = $router->handle($request);

    expect($response->statusCode())->toBe(200);
    $canonical = $response->headers()['Link'] ?? null;
    expect($canonical)->not->toBeNull();
    // view=all should be ignored — canonical should NOT contain view=all
    expect($canonical)->not->toContain('view=all');
    // Canonical should be the bare category URL (no params since page=1 is default)
    expect($canonical)->toContain('/catalog/category/' . $category->id);
});

it('returns 410 gone when the requested page exceeds the max depth', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Deep Paginated Category';
    $categoryRepository->save($category);

    // maxPageDepth=5 — requesting page 6 should return 410
    $router = catalogSeoBuildRouter(
        $categoryRepository,
        $productRepository,
        $assignmentRepository,
        configOverrides: ['maxPageDepth' => 5],
    );

    $request = new Request(
        server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI'    => '/catalog/category/' . $category->id . '?page=6',
            'HTTP_HOST'      => 'example.com',
        ],
        query: ['page' => '6'],
    );
    $response = $router->handle($request);

    expect($response->statusCode())->toBe(410);
});

it('sets a self-referencing canonical pointing at the current page', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Electronics';
    $categoryRepository->save($category);

    $router = catalogSeoBuildRouter($categoryRepository, $productRepository, $assignmentRepository);

    $request = new Request(
        server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI'    => '/catalog/category/' . $category->id . '?page=2',
            'HTTP_HOST'      => 'example.com',
        ],
        query: ['page' => '2'],
    );
    $response = $router->handle($request);

    expect($response->statusCode())->toBe(200);
    $canonical = $response->headers()['Link'] ?? null;
    expect($canonical)->not->toBeNull();
    expect($canonical)->toContain('rel="canonical"');
    expect($canonical)->toContain('page=2');
    expect($canonical)->toContain('/catalog/category/' . $category->id);
});
