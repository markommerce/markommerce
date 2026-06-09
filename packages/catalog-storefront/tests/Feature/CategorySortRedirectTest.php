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
use Markommerce\Catalog\Exceptions\InvalidPaginationConfigException;
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

function sortRedirectMakeEmptyPriceIndexRepository(): ProductPriceIndexRepositoryInterface
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

function sortRedirectMakeCurrencyResolver(): CurrencyResolver
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
function sortRedirectMakeConfigResolver(array $overrides = []): ConfigResolverInterface
{
    $defaults = [
        'defaultPageSize'  => 24,
        'allowedPageSizes' => [12, 24, 48, 96],
        'maxPageSize'      => 96,
        'strategy'         => 'offset',
        'presentation'     => 'numbered',
        'countMode'        => 'exact',
        'maxPageDepth'     => 100,
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

/**
 * Registry with 'position' only — 'unknown_sort' will not be found.
 *
 * @param array<string, mixed> $configOverrides
 */
function sortRedirectMakePaginationOptionsResolver(array $configOverrides = []): PaginationOptionsResolver
{
    $registry = new CategorySortOrderRegistry();
    $registry->register(new ColumnSortOrder(
        key: 'position',
        label: 'Position',
        column: 'catalog_product_category.position',
        direction: SortDirection::Ascending,
        supportsKeyset: false,
    ), 0);

    return new PaginationOptionsResolver(
        sortRedirectMakeConfigResolver($configOverrides),
        $registry,
    );
}

/**
 * Registry with a keyset-incompatible sort (position) under keyset strategy — for the loud test.
 */
function sortRedirectMakeKeysetIncompatResolver(): PaginationOptionsResolver
{
    $registry = new CategorySortOrderRegistry();
    $registry->register(new ColumnSortOrder(
        key: 'position',
        label: 'Position',
        column: 'catalog_product_category.position',
        direction: SortDirection::Ascending,
        supportsKeyset: false,
    ), 0);

    return new PaginationOptionsResolver(
        sortRedirectMakeConfigResolver([
            'strategy'     => 'keyset',
            'presentation' => 'load_more',
            'defaultSort'  => 'position',
        ]),
        $registry,
    );
}

function sortRedirectMakeScopeContext(): ScopeContext
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

function sortRedirectMakeMoneyFormatter(): MoneyFormatter
{
    return new MoneyFormatter(sortRedirectMakeScopeContext());
}

function sortRedirectMakeNoPricePriceResolver(): PriceResolverInterface
{
    return new class () implements PriceResolverInterface
    {
        public function resolve(PriceContext $context): Money
        {
            throw PriceUnavailableException::forContext($context);
        }
    };
}

function sortRedirectMakeAssignmentService(
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
                totalItems: count($products),
                positionCodec: new PositionCodec(),
            );
        }
    };
}

/**
 * @return array<string, PreparedTree>
 */
function sortRedirectBuildArtifact(): array
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

class SortRedirectFakeView implements ViewInterface
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
            }
        }

        $output .= '></div>';

        return $output;
    }
}

class SortRedirectFakeContainer implements ContainerInterface
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
function sortRedirectBuildRouter(
    FakeCategoryRepository $categoryRepository,
    FakeProductRepository $productRepository,
    FakeProductCategoryAssignmentRepository $assignmentRepository,
    array $configOverrides = [],
    ?PaginationOptionsResolver $paginationOptionsResolver = null,
): Router {
    $routes = new RouteCollection();
    $discovery = new RouteDiscovery();
    foreach ($discovery->discoverFromClass(CategoryController::class) as $route) {
        $routes->add($route);
    }
    $matcher = new RouteMatcher($routes);

    $assignmentService = sortRedirectMakeAssignmentService(
        $productRepository,
        $categoryRepository,
        $assignmentRepository,
    );

    $resolver = $paginationOptionsResolver ?? sortRedirectMakePaginationOptionsResolver($configOverrides);

    $container = new SortRedirectFakeContainer();
    $container->instance(RouteMatcherInterface::class, $matcher);
    $container->instance(CategoryRepositoryInterface::class, $categoryRepository);
    $container->instance(
        CategoryController::class,
        new CategoryController(
            $categoryRepository,
            $resolver,
            sortRedirectMakeConfigResolver($configOverrides),
            $assignmentService,
        ),
    );
    $container->instance(CategoryAssignmentService::class, $assignmentService);

    $priceResolver = sortRedirectMakeNoPricePriceResolver();
    $moneyFormatter = sortRedirectMakeMoneyFormatter();

    $productGridComponent = new ProductGridComponent(
        $assignmentService,
        $resolver,
        $priceResolver,
        $moneyFormatter,
        sortRedirectMakeEmptyPriceIndexRepository(),
        sortRedirectMakeCurrencyResolver(),
    );
    $container->instance(ProductGridComponent::class, $productGridComponent);
    $container->instance(ProductCard::class, new ProductCard($priceResolver, $moneyFormatter));
    $container->instance(StockBadge::class, new StockBadge());

    $categoryDataProvider = new CategoryDataProvider($categoryRepository);
    $container->instance(CategoryDataProvider::class, $categoryDataProvider);

    $trees = sortRedirectBuildArtifact();
    $view = new SortRedirectFakeView();

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

it('it redirects to the category url without the sort param when an unknown sort is requested', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Test Category';
    $categoryRepository->save($category);

    $router = sortRedirectBuildRouter($categoryRepository, $productRepository, $assignmentRepository);

    $request = new Request(
        server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI'    => '/catalog/category/' . $category->id . '?sort=name',
            'HTTP_HOST'      => 'example.com',
        ],
        query: ['sort' => 'name'],
    );
    $response = $router->handle($request);

    expect($response->statusCode())->toBe(302);
    $location = $response->headers()['Location'] ?? null;
    expect($location)->not->toBeNull();
    expect($location)->toContain('/catalog/category/' . $category->id);
    expect($location)->not->toContain('sort=');
});

it('it issues a 302 status for an unknown requested sort', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Test Category';
    $categoryRepository->save($category);

    $router = sortRedirectBuildRouter($categoryRepository, $productRepository, $assignmentRepository);

    $request = new Request(
        server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI'    => '/catalog/category/' . $category->id . '?sort=sku',
            'HTTP_HOST'      => 'example.com',
        ],
        query: ['sort' => 'sku'],
    );
    $response = $router->handle($request);

    expect($response->statusCode())->toBe(302);
});

it('it preserves the page and size params while dropping the invalid sort on redirect', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Test Category';
    $categoryRepository->save($category);

    $router = sortRedirectBuildRouter($categoryRepository, $productRepository, $assignmentRepository);

    $request = new Request(
        server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI'    => '/catalog/category/' . $category->id . '?page=3&size=48&sort=price',
            'HTTP_HOST'      => 'example.com',
        ],
        query: ['page' => '3', 'size' => '48', 'sort' => 'price'],
    );
    $response = $router->handle($request);

    expect($response->statusCode())->toBe(302);
    $location = $response->headers()['Location'] ?? null;
    expect($location)->not->toBeNull();
    expect($location)->toContain('page=3');
    expect($location)->toContain('size=48');
    expect($location)->not->toContain('sort=');
});

it('it renders normally without redirecting for a valid registered sort', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Test Category';
    $categoryRepository->save($category);

    $router = sortRedirectBuildRouter($categoryRepository, $productRepository, $assignmentRepository);

    $request = new Request(
        server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI'    => '/catalog/category/' . $category->id . '?sort=position',
            'HTTP_HOST'      => 'example.com',
        ],
        query: ['sort' => 'position'],
    );
    $response = $router->handle($request);

    expect($response->statusCode())->toBe(200);
});

it('it does not redirect and stays loud when a keyset-incompatible sort is requested under the keyset strategy', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Test Category';
    $categoryRepository->save($category);

    $router = sortRedirectBuildRouter(
        $categoryRepository,
        $productRepository,
        $assignmentRepository,
        paginationOptionsResolver: sortRedirectMakeKeysetIncompatResolver(),
    );

    // position is registered but keyset-incompatible under keyset strategy — must throw, not redirect
    $request = new Request(
        server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI'    => '/catalog/category/' . $category->id,
            'HTTP_HOST'      => 'example.com',
        ],
    );

    expect(fn () => $router->handle($request))->toThrow(InvalidPaginationConfigException::class);
});

it('it redirects the page fragment endpoint to default on an unknown sort', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Test Category';
    $categoryRepository->save($category);

    $router = sortRedirectBuildRouter($categoryRepository, $productRepository, $assignmentRepository);

    $request = new Request(
        server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI'    => '/catalog/category/' . $category->id . '/page?sort=name',
            'HTTP_HOST'      => 'example.com',
        ],
        query: ['sort' => 'name'],
    );
    $response = $router->handle($request);

    expect($response->statusCode())->toBe(302);
    $location = $response->headers()['Location'] ?? null;
    expect($location)->not->toBeNull();
    expect($location)->toContain('/catalog/category/' . $category->id);
    expect($location)->not->toContain('sort=');
});
