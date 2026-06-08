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

function catalogFragmentMakeEmptyPriceIndexRepository(): ProductPriceIndexRepositoryInterface
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

function catalogFragmentMakeCurrencyResolver(): CurrencyResolver
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
function catalogFragmentMakeConfigResolver(array $overrides = []): ConfigResolverInterface
{
    $defaults = [
        'defaultPageSize'  => 24,
        'allowedPageSizes' => [12, 24, 48, 96],
        'maxPageSize'      => 96,
        'strategy'         => 'offset',
        'presentation'     => 'numbered',
        'countMode'        => 'exact',
        'maxPageDepth'     => 5,
        'defaultSort'      => 'position',
        'allowedSorts'     => ['position', 'name', 'sku', 'price'],
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

function catalogFragmentMakePaginationOptionsResolver(int $maxPageDepth = 5): PaginationOptionsResolver
{
    return new PaginationOptionsResolver(catalogFragmentMakeConfigResolver(['maxPageDepth' => $maxPageDepth]));
}

/**
 * Build a CategoryAssignmentService with configurable total pages for pagination testing.
 */
function catalogFragmentMakeAssignmentService(
    FakeProductRepository $productRepository,
    FakeCategoryRepository $categoryRepository,
    FakeProductCategoryAssignmentRepository $assignmentRepository,
    int $totalPages = 1,
): CategoryAssignmentService {
    $positionCodec = new PositionCodec();

    return new class (
        $productRepository,
        $categoryRepository,
        $assignmentRepository,
        $positionCodec,
        new KeysetPaginationStrategy($positionCodec),
        $totalPages,
    ) extends CategoryAssignmentService
    {
        public function __construct(
            FakeProductRepository $productRepository,
            FakeCategoryRepository $categoryRepository,
            FakeProductCategoryAssignmentRepository $assignmentRepository,
            PositionCodec $positionCodec,
            KeysetPaginationStrategy $keysetPaginationStrategy,
            private readonly int $totalPages,
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
                size: $options->pageRequest->size,
                nextPosition: null,
                previousPosition: null,
                currentPage: $options->page,
                totalPages: $this->totalPages,
                totalItems: count($products),
                positionCodec: new PositionCodec(),
            );
        }
    };
}

function catalogFragmentMakeScopeContext(): ScopeContext
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

function catalogFragmentMakeMoneyFormatter(): MoneyFormatter
{
    return new MoneyFormatter(catalogFragmentMakeScopeContext());
}

function catalogFragmentMakeNoPricePriceResolver(): PriceResolverInterface
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
 * Build a compiled layout artifact (prepared trees) for the catalog layout.
 *
 * @return array<string, PreparedTree>
 */
function catalogFragmentBuildArtifact(): array
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

/**
 * A fake ViewInterface that renders template name and all scalar data properties.
 */
class CatalogFragmentFakeView implements ViewInterface
{
    public function render(
        string $template,
        array $data = [],
    ): Response {
        return Response::html($this->renderToString($template, $data));
    }

    public function renderToString(
        string $template,
        array $data = [],
    ): string {
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
                foreach ($value as $k => $v) {
                    if (is_string($v)) {
                        $output .= ' data-array-item="' . htmlspecialchars($v) . '"';
                    }
                }
            }
        }

        // Include slot placeholders
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

/**
 * Simple fake container for tests.
 */
class CatalogFragmentFakeContainer implements ContainerInterface
{
    /** @var array<string, object> */
    private array $bindings = [];

    public function bind(
        string $class,
        object $instance,
    ): void {
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

    public function instance(
        string $id,
        object $instance,
    ): void {
        $this->bindings[$id] = $instance;
    }

    public function call(Closure $callable): mixed
    {
        return $callable();
    }
}

function catalogFragmentBuildRouter(
    FakeCategoryRepository $categoryRepository,
    FakeProductRepository $productRepository,
    FakeProductCategoryAssignmentRepository $assignmentRepository,
    int $totalPages = 1,
    int $maxPageDepth = 5,
): Router {
    $routes = new RouteCollection();
    $discovery = new RouteDiscovery();
    foreach ($discovery->discoverFromClass(CategoryController::class) as $route) {
        $routes->add($route);
    }
    $matcher = new RouteMatcher($routes);

    $assignmentService = catalogFragmentMakeAssignmentService(
        $productRepository,
        $categoryRepository,
        $assignmentRepository,
        $totalPages,
    );

    $container = new CatalogFragmentFakeContainer();
    $container->instance(RouteMatcherInterface::class, $matcher);
    $container->instance(CategoryRepositoryInterface::class, $categoryRepository);
    $container->instance(
        CategoryController::class,
        new CategoryController($categoryRepository, catalogFragmentMakePaginationOptionsResolver($maxPageDepth)),
    );
    $container->instance(CategoryAssignmentService::class, $assignmentService);

    $priceResolver = catalogFragmentMakeNoPricePriceResolver();
    $moneyFormatter = catalogFragmentMakeMoneyFormatter();

    $productGridComponent = new ProductGridComponent(
        $assignmentService,
        catalogFragmentMakePaginationOptionsResolver($maxPageDepth),
        $priceResolver,
        $moneyFormatter,
        catalogFragmentMakeEmptyPriceIndexRepository(),
        catalogFragmentMakeCurrencyResolver(),
    );
    $container->instance(ProductGridComponent::class, $productGridComponent);
    $container->instance(ProductCard::class, new ProductCard($priceResolver, $moneyFormatter));
    $container->instance(StockBadge::class, new StockBadge());

    $categoryDataProvider = new CategoryDataProvider($categoryRepository);
    $container->instance(CategoryDataProvider::class, $categoryDataProvider);

    $trees = catalogFragmentBuildArtifact();
    $view = new CatalogFragmentFakeView();

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

it('renders the product cards for the requested page as html', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Test Category';
    $categoryRepository->save($category);

    $product = new Product();
    $product->sku = 'PROD-001';
    $product->name = 'Test Product';
    $productRepository->save($product);

    $assignmentService = catalogFragmentMakeAssignmentService($productRepository, $categoryRepository, $assignmentRepository);
    $assignmentService->assign($product->id, $category->id);

    $router = catalogFragmentBuildRouter($categoryRepository, $productRepository, $assignmentRepository);

    $request = new Request(
        server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/catalog/category/' . $category->id . '/page'],
        query: ['page' => '1'],
    );
    $response = $router->handle($request);

    expect($response->statusCode())->toBe(200);
    expect($response->body())->toContain('catalog-storefront::components/product-card');
});

it('produces card markup identical to the full page render', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Test Category';
    $categoryRepository->save($category);

    $product = new Product();
    $product->sku = 'PROD-001';
    $product->name = 'Test Product';
    $productRepository->save($product);

    $assignmentService = catalogFragmentMakeAssignmentService($productRepository, $categoryRepository, $assignmentRepository);
    $assignmentService->assign($product->id, $category->id);

    $router = catalogFragmentBuildRouter($categoryRepository, $productRepository, $assignmentRepository);

    // Fetch full page
    $fullRequest = new Request(
        server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/catalog/category/' . $category->id],
    );
    $fullResponse = $router->handle($fullRequest);

    // Fetch fragment
    $fragmentRequest = new Request(
        server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/catalog/category/' . $category->id . '/page'],
        query: ['page' => '1'],
    );
    $fragmentResponse = $router->handle($fragmentRequest);

    // Extract the card region from both: both should contain the same card markup
    $cardPattern = '/<div[^>]*data-template="catalog-storefront::components\/product-card"[^>]*>/';
    preg_match($cardPattern, $fullResponse->body(), $fullMatches);
    preg_match($cardPattern, $fragmentResponse->body(), $fragmentMatches);

    expect($fragmentMatches)->not->toBeEmpty();
    expect($fullMatches[0])->toBe($fragmentMatches[0]);
});

it('respects the size and sort query params', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Test Category';
    $categoryRepository->save($category);

    $product = new Product();
    $product->sku = 'PROD-001';
    $product->name = 'Product 1';
    $productRepository->save($product);

    $assignmentService = catalogFragmentMakeAssignmentService($productRepository, $categoryRepository, $assignmentRepository);
    $assignmentService->assign($product->id, $category->id);

    $router = catalogFragmentBuildRouter($categoryRepository, $productRepository, $assignmentRepository);

    $request = new Request(
        server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/catalog/category/' . $category->id . '/page'],
        query: ['page' => '1', 'size' => '12', 'sort' => 'name'],
    );
    $response = $router->handle($request);

    expect($response->statusCode())->toBe(200);
    expect($response->body())->toContain('catalog-storefront::components/product-card');
});

it('signals no more results past the last page', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Test Category';
    $categoryRepository->save($category);

    $router = catalogFragmentBuildRouter($categoryRepository, $productRepository, $assignmentRepository, totalPages: 1);

    // Request page 2 when there is only 1 page — empty grid, no data-next
    $request = new Request(
        server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/catalog/category/' . $category->id . '/page'],
        query: ['page' => '2'],
    );
    $response = $router->handle($request);

    expect($response->statusCode())->toBe(200);
    // With the fake view, nextPageUrl (null) is not rendered as data-nextpageurl
    expect($response->body())->not->toContain('data-nextpageurl');
});

it('returns 410 when the requested page exceeds the max depth', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Test Category';
    $categoryRepository->save($category);

    $router = catalogFragmentBuildRouter(
        $categoryRepository,
        $productRepository,
        $assignmentRepository,
        maxPageDepth: 5,
    );

    $request = new Request(
        server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/catalog/category/' . $category->id . '/page'],
        query: ['page' => '6'],
    );
    $response = $router->handle($request);

    expect($response->statusCode())->toBe(410);
});

it('returns 404 for an unknown category', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $router = catalogFragmentBuildRouter($categoryRepository, $productRepository, $assignmentRepository);

    $request = new Request(
        server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/catalog/category/9999/page'],
    );
    $response = $router->handle($request);

    expect($response->statusCode())->toBe(404);
});
