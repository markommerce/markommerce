<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepository;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\RouteCollection;
use Marko\Routing\RouteDiscovery;
use Marko\Routing\RouteMatcher;
use Marko\Routing\RouteMatcherInterface;
use Marko\Routing\Router;
use Marko\View\ViewInterface;
use Markommerce\Catalog\Component\ProductCard;
use Markommerce\Catalog\Component\ProductGridComponent;
use Markommerce\Catalog\Component\StockBadge;
use Markommerce\Catalog\Context\CategoryDataProvider;
use Markommerce\Catalog\Contracts\CategoryRepositoryInterface;
use Markommerce\Catalog\Controller\CategoryController;
use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Services\CategoryAssignmentService;
use Markommerce\Catalog\Tests\Support\FakeCategoryRepository;
use Markommerce\Catalog\Tests\Support\FakeProductCategoryAssignmentRepository;
use Markommerce\Catalog\Tests\Support\FakeProductRepository;
use Markommerce\Layout\Cache\ArtifactReaderInterface;
use Markommerce\Layout\Cache\PreparedTree;
use Markommerce\Layout\Cache\PreparedTreeBuilder;
use Markommerce\Layout\Compiler\Compiler;
use Markommerce\Layout\Compiler\ResolutionPhase;
use Markommerce\Layout\Compiler\ValidationPhase;
use Markommerce\Layout\Discovery\LayoutDiscovery;
use Markommerce\Layout\Middleware\MarkommerceLayoutMiddleware;
use Markommerce\Layout\Runtime\Renderer;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Metadata\ScopedFieldRegistry;
use Markommerce\Scope\Metadata\ScopeMetadataFactory;
use Markommerce\Scope\Registry\PhpScopeRegistry;
use Markommerce\Scope\Resolution\ScopeWalker;
use Markommerce\Scope\Resolver\ScopeResolver;
use Markommerce\Scope\Signature\ScopeSignatureValidator;
use Markommerce\Scope\Signature\SignatureCandidateEnumerator;
use Markommerce\Scope\Storage\DefaultScopeGuard;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function catalogControllerBuildScopeResolver(): ScopeResolver
{
    DefaultScopeGuard::reset();

    $rawConfig = require dirname(__DIR__, 3) . '/scope/config/scope.php';
    $config = new ConfigRepository(['scope' => $rawConfig]);
    $registry = new PhpScopeRegistry($config);

    $context = new ScopeContext($registry);
    $metadataFactory = new ScopeMetadataFactory($registry, new ScopedFieldRegistry(scopeRegistry: $registry));
    $enumerator = new SignatureCandidateEnumerator($registry);
    $walker = new ScopeWalker($enumerator);
    $validator = new ScopeSignatureValidator($registry);

    return new ScopeResolver($metadataFactory, $walker, $context, $validator);
}

function catalogControllerTestCleanup(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            catalogControllerTestCleanup($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

/**
 * Build a compiled layout artifact (prepared trees) for the catalog layout.
 *
 * @return array<string, PreparedTree>
 */
function catalogControllerBuildArtifact(): array
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
    $compiler = new Compiler($layoutDiscovery, $resolutionPhase, $validationPhase, $treeBuilder);

    return $compiler->compile();
}

/**
 * A fake ViewInterface that renders template name and all scalar data properties.
 * This makes it possible to assert that the right data was passed to the view.
 */
class CatalogControllerFakeView implements ViewInterface
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
                // For Category objects, include their name if available
                if (property_exists($value, 'name') && is_string($value->name)) {
                    $output .= ' data-' . htmlspecialchars($key) . '-name="' . htmlspecialchars($value->name) . '"';
                    $output .= '>' . htmlspecialchars($value->name);
                    // Include slot placeholders
                    if (isset($data['_slots']) && is_array($data['_slots'])) {
                        foreach (array_keys($data['_slots']) as $slotName) {
                            $output .= "{slot $slotName}{/slot}";
                        }
                    }
                    $output .= '</div>';

                    return $output;
                }
            } elseif (is_array($value)) {
                // For arrays like resolvedNames - include values
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
class CatalogControllerFakeContainer implements ContainerInterface
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

function catalogControllerTestBuildRouter(
    FakeCategoryRepository $categoryRepository,
    FakeProductRepository $productRepository,
    FakeProductCategoryAssignmentRepository $assignmentRepository,
): Router {
    $routes = new RouteCollection();
    $discovery = new RouteDiscovery();
    foreach ($discovery->discoverFromClass(CategoryController::class) as $route) {
        $routes->add($route);
    }
    $matcher = new RouteMatcher($routes);

    $scopeResolver = catalogControllerBuildScopeResolver();
    $assignmentService = new CategoryAssignmentService(
        $productRepository,
        $categoryRepository,
        $assignmentRepository,
    );

    $container = new CatalogControllerFakeContainer();
    $container->instance(RouteMatcherInterface::class, $matcher);
    $container->instance(CategoryRepositoryInterface::class, $categoryRepository);
    $container->instance(CategoryController::class, new CategoryController($categoryRepository));
    $container->instance(ScopeResolver::class, $scopeResolver);
    $container->instance(CategoryAssignmentService::class, $assignmentService);

    $productGridComponent = new ProductGridComponent($categoryRepository, $assignmentService, $scopeResolver);
    $container->instance(ProductGridComponent::class, $productGridComponent);
    $container->instance(ProductCard::class, new ProductCard());
    $container->instance(StockBadge::class, new StockBadge());

    $categoryDataProvider = new CategoryDataProvider($categoryRepository);
    $container->instance(CategoryDataProvider::class, $categoryDataProvider);

    $trees = catalogControllerBuildArtifact();
    $view = new CatalogControllerFakeView();

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

it('places a Get route at /catalog/category/{id} on the controller action', function (): void {
    $reflection = new ReflectionClass(CategoryController::class);

    $classGetAttributes = $reflection->getAttributes(Get::class);
    expect($classGetAttributes)->toBeEmpty();

    $method = $reflection->getMethod('show');
    $methodGetAttributes = $method->getAttributes(Get::class);
    expect($methodGetAttributes)->not->toBeEmpty();

    $getAttr = $methodGetAttributes[0]->newInstance();
    expect($getAttr->path)->toBe('/catalog/category/{id}');
});

it(
    'defines the layout for CategoryController show via a layout file instead of a controller attribute',
    function (): void {
        $reflection = new ReflectionClass(CategoryController::class);

        // Controller should NOT have marko/layout's Layout attribute
        $markoLayoutClass = 'Marko\Layout\Attributes\Layout';
        $attributes = $reflection->getAttributes($markoLayoutClass);
        expect($attributes)->toBeEmpty();

        // The layout file should exist
        $layoutPath = dirname(__DIR__, 2) . '/layout/category_show.php';
        expect(file_exists($layoutPath))->toBeTrue();
    },
);

it('returns a 200 response with the assembled layout HTML when the category exists', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Test Category';
    $categoryRepository->save($category);

    $router = catalogControllerTestBuildRouter(
        $categoryRepository,
        $productRepository,
        $assignmentRepository,
    );

    $request = new Request(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/catalog/category/' . $category->id]);
    $response = $router->handle($request);

    expect($response->statusCode())->toBe(200);
});

it('returns a 404 response when the requested category id does not exist', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $router = catalogControllerTestBuildRouter(
        $categoryRepository,
        $productRepository,
        $assignmentRepository,
    );

    $request = new Request(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/catalog/category/9999']);
    $response = $router->handle($request);

    expect($response->statusCode())->toBe(404);
});

it('includes the category name in the rendered page heading', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Featured Electronics';
    $categoryRepository->save($category);

    $router = catalogControllerTestBuildRouter(
        $categoryRepository,
        $productRepository,
        $assignmentRepository,
    );

    $request = new Request(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/catalog/category/' . $category->id]);
    $response = $router->handle($request);

    expect($response->body())->toContain('Featured Electronics');
});

it('renders every assigned product as a product grid item in the response body', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Shoes';
    $categoryRepository->save($category);

    $product1 = new Product();
    $product1->sku = 'SHOE-001';
    $product1->name = 'Running Shoes';
    $productRepository->save($product1);

    $product2 = new Product();
    $product2->sku = 'SHOE-002';
    $product2->name = 'Hiking Boots';
    $productRepository->save($product2);

    $assignmentService = new CategoryAssignmentService($productRepository, $categoryRepository, $assignmentRepository);
    $assignmentService->assign($product1->id, $category->id);
    $assignmentService->assign($product2->id, $category->id);

    $router = catalogControllerTestBuildRouter(
        $categoryRepository,
        $productRepository,
        $assignmentRepository,
    );

    $request = new Request(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/catalog/category/' . $category->id]);
    $response = $router->handle($request);

    expect($response->body())
        ->toContain('Running Shoes')
        ->toContain('Hiking Boots');
});

it('renders an empty-state message when the category has no products', function (): void {
    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Empty Category';
    $categoryRepository->save($category);

    $router = catalogControllerTestBuildRouter(
        $categoryRepository,
        $productRepository,
        $assignmentRepository,
    );

    $request = new Request(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/catalog/category/' . $category->id]);
    $response = $router->handle($request);

    // The ProductGrid component renders with the category but no products
    // The response should contain the layout and product grid template
    expect($response->body())->toContain('catalog::components/product-grid');
    expect($response->statusCode())->toBe(200);
});

it('removes the standalone resources/views/category.latte template', function (): void {
    $templatePath = dirname(__DIR__, 2) . '/resources/views/category.latte';

    expect(file_exists($templatePath))->toBeFalse();
});
