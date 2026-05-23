<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepository;
use Marko\Core\Module\ModuleRepositoryInterface;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;
use Marko\View\ViewInterface;
use Markommerce\Catalog\Component\ProductCard;
use Markommerce\Catalog\Component\ProductGridComponent;
use Markommerce\Catalog\Component\StockBadge;
use Markommerce\Catalog\Context\CategoryDataProvider;
use Markommerce\Catalog\Context\CategoryToken;
use Markommerce\Catalog\Contracts\CategoryRepositoryInterface;
use Markommerce\Catalog\Controller\CategoryController;
use Markommerce\Catalog\Data\ProductCardData;
use Markommerce\Catalog\Data\ProductGridData;
use Markommerce\Catalog\Data\StockBadgeData;
use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Iteration\ProductIteration;
use Markommerce\Catalog\Services\CategoryAssignmentService;
use Markommerce\Catalog\Tests\Support\FakeCategoryRepository;
use Markommerce\Catalog\Tests\Support\FakeProductCategoryAssignmentRepository;
use Markommerce\Catalog\Tests\Support\FakeProductRepository;
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
use Markommerce\Layout\ExtensionBag;
use Markommerce\Layout\Layout;
use Markommerce\Layout\Middleware\MarkommerceLayoutMiddleware;
use Markommerce\Layout\Place;
use Markommerce\Layout\Provide;
use Markommerce\Layout\Runtime\Renderer;
use Markommerce\Layout\Runtime\RendererInterface;
use Markommerce\Layout\Slot;
use Markommerce\Layout\Source\Source;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Metadata\ScopeMetadataFactory;
use Markommerce\Scope\Registry\PhpScopeRegistry;
use Markommerce\Scope\Resolution\ScopeWalker;
use Markommerce\Scope\Resolver\ScopeResolver;
use Markommerce\Scope\Signature\ScopeSignatureValidator;
use Markommerce\Scope\Signature\SignatureCandidateEnumerator;
use Markommerce\Scope\Storage\DefaultScopeGuard;
use Markommerce\ThemeBlank\Layout\OneColumnLayout;
use Marko\Config\ConfigRepository;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function catalogLayoutLoadLayoutFile(): Layout
{
    $path = dirname(__DIR__, 2) . '/layout/category_show.php';
    return require $path;
}

function catalogLayoutBuildScopeResolver(): ScopeResolver
{
    DefaultScopeGuard::reset();

    $rawConfig = require dirname(__DIR__, 3) . '/scope/config/scope.php';
    $config = new ConfigRepository(['scope' => $rawConfig]);
    $registry = new PhpScopeRegistry($config);

    $context = new ScopeContext($registry);
    $metadataFactory = new ScopeMetadataFactory($registry);
    $enumerator = new SignatureCandidateEnumerator($registry);
    $walker = new ScopeWalker($enumerator);
    $validator = new ScopeSignatureValidator($registry);

    return new ScopeResolver($metadataFactory, $walker, $context, $validator);
}

function catalogLayoutBuildCompiler(): Compiler
{
    $catalogPath = dirname(__DIR__, 2);

    $moduleRepository = new ModuleRepository([
        new ModuleManifest(
            name: 'markommerce/catalog',
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
    $artifactReader = new class($trees) implements ArtifactReaderInterface {
        /** @param array<string, PreparedTree> $trees */
        public function __construct(private array $trees) {}

        public function read(): array
        {
            return $this->trees;
        }
    };

    $routeMatcher = $container->get(\Marko\Routing\RouteMatcherInterface::class);
    $renderer = new Renderer($view, $container);

    return new MarkommerceLayoutMiddleware($routeMatcher, $artifactReader, $renderer, $container);
}

/**
 * Simple fake view that renders templates with minimal HTML.
 */
class CatalogLayoutFakeView implements ViewInterface
{
    public function render(string $template, array $data = []): Response
    {
        return Response::html($this->renderToString($template, $data));
    }

    public function renderToString(string $template, array $data = []): string
    {
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
        throw new \RuntimeException("No binding for $id");
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

    public function call(\Closure $callable): mixed
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
        dirname(__DIR__, 2) . '/src/Component/ProductGridComponent.php'
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

    $assignmentService = new CategoryAssignmentService(
        $productRepository,
        $categoryRepository,
        $assignmentRepository,
    );

    DefaultScopeGuard::reset();
    $rawConfig = require dirname(__DIR__, 3) . '/scope/config/scope.php';
    $config = new ConfigRepository(['scope' => $rawConfig]);
    $registry = new PhpScopeRegistry($config);
    $context = new ScopeContext($registry);
    $metadataFactory = new ScopeMetadataFactory($registry);
    $enumerator = new SignatureCandidateEnumerator($registry);
    $walker = new ScopeWalker($enumerator);
    $validator = new ScopeSignatureValidator($registry);
    $scopeResolver = new ScopeResolver($metadataFactory, $walker, $context, $validator);

    $component = new ProductGridComponent($categoryRepository, $assignmentService, $scopeResolver);
    $data = $component->data($category);

    expect($data)->toBeInstanceOf(ProductGridData::class);
    expect($data->products)->toBeArray();
});

it('loads the category via a context provider instead of inside the component', function (): void {
    // CategoryDataProvider must implement ContextProvider
    $reflection = new ReflectionClass(CategoryDataProvider::class);
    expect($reflection->implementsInterface(ContextProvider::class))->toBeTrue();

    // ProductGridComponent's data() method must not accept an int $id (category fetching moved out)
    $gridReflection = new ReflectionClass(ProductGridComponent::class);
    $dataMethod = $gridReflection->getMethod('data');
    $params = $dataMethod->getParameters();

    $paramTypes = array_map(fn ($p) => $p->getType()?->getName(), $params);
    // Should NOT accept plain int — should accept Category object
    expect($paramTypes)->not->toContain('int');
    expect($paramTypes)->toContain(Category::class);
});

it('returns 404 from the controller when the category does not exist', function (): void {
    $categoryRepository = new FakeCategoryRepository();

    $controller = new CategoryController($categoryRepository);
    $response = $controller->show(9999);

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

    $assignmentService = new CategoryAssignmentService(
        $productRepository,
        $categoryRepository,
        $assignmentRepository,
    );
    $assignmentService->assign($product->id, $category->id);

    $scopeResolver = catalogLayoutBuildScopeResolver();

    $container = new CatalogLayoutFakeContainer();
    $view = new CatalogLayoutFakeView();

    $routes = new \Marko\Routing\RouteCollection();
    $discovery = new \Marko\Routing\RouteDiscovery();
    foreach ($discovery->discoverFromClass(CategoryController::class) as $route) {
        $routes->add($route);
    }
    $matcher = new \Marko\Routing\RouteMatcher($routes);

    $container->instance(\Marko\Routing\RouteMatcherInterface::class, $matcher);
    $container->instance(CategoryRepositoryInterface::class, $categoryRepository);
    $container->instance(CategoryController::class, new CategoryController($categoryRepository));
    $container->instance(CategoryAssignmentService::class, $assignmentService);
    $container->instance(ScopeResolver::class, $scopeResolver);

    $productGridComponent = new ProductGridComponent($categoryRepository, $assignmentService, $scopeResolver);
    $container->instance(ProductGridComponent::class, $productGridComponent);

    // Register CategoryDataProvider that uses the fake repository
    $categoryDataProvider = new CategoryDataProvider($categoryRepository);
    $container->instance(CategoryDataProvider::class, $categoryDataProvider);

    $middleware = catalogLayoutBuildMiddleware($trees, $container, $view);

    $controllerCallable = function (Request $request) use ($container): Response {
        $controller = $container->get(CategoryController::class);
        return $controller->show((int) explode('/', $request->path())[3]);
    };

    $request = new Request([
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/catalog/category/' . $category->id,
    ]);

    $response = $middleware->handle($request, $controllerCallable);

    expect($response->statusCode())->toBe(200);
    expect($response->body())->toContain('catalog::components/product-grid');
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
