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
use Markommerce\CatalogStorefront\Data\ProductGridData;
use Markommerce\Config\Contracts\ConfigResolverInterface;
use Markommerce\Criteria\Page\Page;
use Markommerce\Criteria\Position\PositionCodec;
use Markommerce\Criteria\Strategy\KeysetPaginationStrategy;
use Markommerce\Criteria\Strategy\OffsetPage;
use Markommerce\Currency\CurrencyResolver;
use Markommerce\Layout\Cache\ArtifactReaderInterface;
use Markommerce\Layout\Cache\PreparedPlace;
use Markommerce\Layout\Cache\PreparedRepeatSlot;
use Markommerce\Layout\Cache\PreparedTree;
use Markommerce\Layout\Cache\PreparedTreeBuilder;
use Markommerce\Layout\Compiler\Compiler;
use Markommerce\Layout\Compiler\ResolutionPhase;
use Markommerce\Layout\Compiler\ValidationPhase;
use Markommerce\Layout\Contracts\ContextProvider;
use Markommerce\Layout\Discovery\LayoutDiscovery;
use Markommerce\Layout\Layout;
use Markommerce\Layout\Middleware\MarkommerceLayoutMiddleware;
use Markommerce\Layout\Runtime\Renderer;
use Markommerce\Layout\Slot;
use Markommerce\Money\Currency;
use Markommerce\Money\Money;
use Markommerce\MoneyIntl\MoneyFormatter;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Registry\ScopeRegistryInterface;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function catalogLayoutMakeEmptyPriceIndexRepository(): ProductPriceIndexRepositoryInterface
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

function catalogLayoutMakeCurrencyResolver(): CurrencyResolver
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

function catalogLayoutMakeScopeContext(): ScopeContext
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

function catalogLayoutMakeMoneyFormatter(): MoneyFormatter
{
    return new MoneyFormatter(catalogLayoutMakeScopeContext());
}

function catalogLayoutMakeNoPricePriceResolver(): PriceResolverInterface
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
 * @param array<string, mixed> $overrides
 */
function catalogLayoutMakeConfigResolver(array $overrides = []): ConfigResolverInterface
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

function catalogLayoutMakeSortRegistry(): CategorySortOrderRegistry
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

function catalogLayoutMakePaginationOptionsResolver(): PaginationOptionsResolver
{
    return new PaginationOptionsResolver(catalogLayoutMakeConfigResolver(), catalogLayoutMakeSortRegistry());
}

/**
 * Build a CategoryAssignmentService that delegates paginatedProductsInCategory
 * to the in-memory fake repositories (wraps productsInCategory result in an OffsetPage).
 */
function catalogLayoutMakeAssignmentService(
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

function catalogLayoutLoadLayoutFile(): Layout
{
    $path = dirname(__DIR__, 2) . '/layout/category_show.php';

    return require $path;
}

function catalogLayoutBuildCompiler(): Compiler
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

/**
 * @param array<string, PreparedTree> $trees
 */
function catalogLayoutBuildMiddleware(
    array $trees,
    ContainerInterface $container,
    ViewInterface $view,
): MarkommerceLayoutMiddleware {
    $artifactReader = new class ($trees) implements ArtifactReaderInterface
    {
        /** @param array<string, PreparedTree> $trees */
        public function __construct(private array $trees) {}

        public function read(): array
        {
            return $this->trees;
        }
    };

    $routeMatcher = $container->get(RouteMatcherInterface::class);
    $renderer = new Renderer($view, $container);

    return new MarkommerceLayoutMiddleware($routeMatcher, $artifactReader, $renderer, $container);
}

/**
 * Simple fake view that renders templates with minimal HTML.
 */
class CatalogLayoutFakeView implements ViewInterface
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
        $slots = '';
        if (isset($data['_slots']) && is_array($data['_slots'])) {
            foreach (array_keys($data['_slots']) as $slotName) {
                $slots .= "{slot $slotName}{/slot}";
            }
        }

        return "<div data-template=\"$template\">$slots</div>";
    }
}

/**
 * Simple fake container that resolves named classes or pre-registered instances.
 */
class CatalogLayoutFakeContainer implements ContainerInterface
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

// ─── Tests ────────────────────────────────────────────────────────────────────

it('defines a category_show layout for the CategoryController show action', function (): void {
    $layout = catalogLayoutLoadLayoutFile();

    expect($layout)->toBeInstanceOf(Layout::class);
    expect($layout->handle)->toBe([CategoryController::class, 'show']);
});

it('compiles the category_show layout without error', function (): void {
    $compiler = catalogLayoutBuildCompiler();
    $trees = $compiler->compile();

    $handleKey = CategoryController::class . '::show';
    expect($trees)->toHaveKey($handleKey);
    expect($trees[$handleKey])->toBeInstanceOf(PreparedTree::class);
});

it('exposes the product grid component without a hardcoded handle or slot', function (): void {
    $reflection = new ReflectionClass(ProductGridComponent::class);

    // Ensure the class does NOT have marko/layout Component attribute with handle/slot
    $markoComponentClass = 'Marko\Layout\Attributes\Component';
    if (class_exists($markoComponentClass)) {
        $attributes = $reflection->getAttributes($markoComponentClass);
        // Either no such attribute OR the attribute has no handle/slot binding
        if (!empty($attributes)) {
            $attr = $attributes[0]->newInstance();
            expect($attr->handle ?? null)->toBeNull();
            expect($attr->slot ?? null)->toBeNull();
        }
    }

    // The component should NOT reference CategoryController in its attributes
    $source = file_get_contents(
        dirname(__DIR__, 2) . '/src/Component/ProductGridComponent.php',
    );
    expect($source)->not->toContain("handle: [CategoryController::class, 'show']");
    expect($source)->not->toContain("slot: 'content'");
});

it('returns a typed ProductGridData DTO from the grid component data method', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Test';
    $categoryRepository->save($category);

    $assignmentService = catalogLayoutMakeAssignmentService(
        $productRepository,
        $categoryRepository,
        $assignmentRepository,
    );

    $component = new ProductGridComponent(
        $assignmentService,
        catalogLayoutMakePaginationOptionsResolver(),
        catalogLayoutMakeNoPricePriceResolver(),
        catalogLayoutMakeMoneyFormatter(),
        catalogLayoutMakeEmptyPriceIndexRepository(),
        catalogLayoutMakeCurrencyResolver(),
    );
    $data = $component->data($category, 1, 0, '');

    expect($data)->toBeInstanceOf(ProductGridData::class);
    expect($data->products)->toBeArray();
});

it('loads the category via a context provider instead of inside the component', function (): void {
    // CategoryDataProvider must implement ContextProvider
    $reflection = new ReflectionClass(CategoryDataProvider::class);
    expect($reflection->implementsInterface(ContextProvider::class))->toBeTrue();

    // ProductGridComponent's data() method must accept a Category object (not just a raw int ID)
    $gridReflection = new ReflectionClass(ProductGridComponent::class);
    $dataMethod = $gridReflection->getMethod('data');
    $params = $dataMethod->getParameters();

    $paramNames = array_map(fn ($p) => $p->getName(), $params);
    $paramTypes = array_map(fn ($p) => $p->getType()?->getName(), $params);
    // Must accept Category object
    expect($paramTypes)->toContain(Category::class);
    // The first param is 'category' (not 'id')
    expect($paramNames[0])->toBe('category');
    // Should accept pagination params from the layout query string
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
        file_get_contents(dirname(__DIR__, 2) . '/composer.json'),
        true,
    );

    expect($manifest['require'])->not->toHaveKey('marko/layout');
    expect($manifest['require'])->toHaveKey('markommerce/layout');
});

it('renders the category page with a grid of product cards', function (): void {
    $compiler = catalogLayoutBuildCompiler();
    $trees = $compiler->compile();
    $handleKey = CategoryController::class . '::show';

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

    $assignmentService = catalogLayoutMakeAssignmentService(
        $productRepository,
        $categoryRepository,
        $assignmentRepository,
    );
    $assignmentService->assign($product->id, $category->id);

    $container = new CatalogLayoutFakeContainer();
    $view = new CatalogLayoutFakeView();

    $routes = new RouteCollection();
    $discovery = new RouteDiscovery();
    foreach ($discovery->discoverFromClass(CategoryController::class) as $route) {
        $routes->add($route);
    }
    $matcher = new RouteMatcher($routes);

    $container->instance(RouteMatcherInterface::class, $matcher);
    $container->instance(CategoryRepositoryInterface::class, $categoryRepository);
    $container->instance(CategoryController::class, new CategoryController($categoryRepository));
    $container->instance(CategoryAssignmentService::class, $assignmentService);

    $priceResolver = catalogLayoutMakeNoPricePriceResolver();
    $moneyFormatter = catalogLayoutMakeMoneyFormatter();

    $productGridComponent = new ProductGridComponent(
        $assignmentService,
        catalogLayoutMakePaginationOptionsResolver(),
        $priceResolver,
        $moneyFormatter,
        catalogLayoutMakeEmptyPriceIndexRepository(),
        catalogLayoutMakeCurrencyResolver(),
    );
    $container->instance(ProductGridComponent::class, $productGridComponent);
    $container->instance(ProductCard::class, new ProductCard($priceResolver, $moneyFormatter));

    // Register CategoryDataProvider that uses the fake repository
    $categoryDataProvider = new CategoryDataProvider($categoryRepository);
    $container->instance(CategoryDataProvider::class, $categoryDataProvider);

    $middleware = catalogLayoutBuildMiddleware($trees, $container, $view);

    $controllerCallable = function (Request $request) use ($container): Response {
        $controller = $container->get(CategoryController::class);

        return $controller->show((int) explode('/', $request->path())[3], $request);
    };

    $request = new Request([
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/catalog/category/' . $category->id,
    ]);

    $response = $middleware->handle($request, $controllerCallable);

    expect($response->statusCode())->toBe(200);
    expect($response->body())->toContain('catalog-storefront::components/product-grid');
});

it('renders a stock badge sub-slot inside each product card', function (): void {
    $compiler = catalogLayoutBuildCompiler();
    $handleKey = CategoryController::class . '::show';
    $trees = $compiler->compile();
    $tree = $trees[$handleKey];

    // Find the product grid placement in the content slot
    $contentSlot = $tree->slots['content'] ?? [];
    expect($contentSlot)->not->toBeEmpty();

    // Find the products slot (should be a repeat slot)
    $productGridPlace = $contentSlot[0];
    expect($productGridPlace)->toBeInstanceOf(PreparedPlace::class);

    $productsSlot = $productGridPlace->slots['products'] ?? null;
    expect($productsSlot)->toBeInstanceOf(PreparedRepeatSlot::class);

    // The repeat slot children should contain the ProductCard component
    $productCardPlace = $productsSlot->children[0] ?? null;
    expect($productCardPlace)->toBeInstanceOf(PreparedPlace::class);
    expect($productCardPlace->component)->toBe(ProductCard::class);

    // The ProductCard's badges slot should exist
    $badgesSlot = $productCardPlace->slots['badges'] ?? null;
    expect($badgesSlot)->not->toBeNull();
    expect($badgesSlot)->not->toBeEmpty();

    // Should contain a StockBadge
    $stockBadgePlace = $badgesSlot[0] ?? null;
    expect($stockBadgePlace)->toBeInstanceOf(PreparedPlace::class);
    expect($stockBadgePlace->component)->toBe(StockBadge::class);
});
