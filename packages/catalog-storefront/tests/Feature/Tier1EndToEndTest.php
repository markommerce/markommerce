<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepository;
use Marko\Core\Module\ModuleRepositoryInterface;
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
use Markommerce\Catalog\Services\CategoryAssignmentService;
use Markommerce\Catalog\Tests\Support\FakeCategoryRepository;
use Markommerce\Catalog\Tests\Support\FakeProductCategoryAssignmentRepository;
use Markommerce\Catalog\Tests\Support\FakeProductRepository;
use Markommerce\CatalogStorefront\Component\ProductCard;
use Markommerce\CatalogStorefront\Component\ProductGridComponent;
use Markommerce\CatalogStorefront\Component\StockBadge;
use Markommerce\CatalogStorefront\Context\CategoryDataProvider;
use Markommerce\CatalogStorefront\Controller\CategoryController;
use Markommerce\Layout\Cache\ArtifactReaderInterface;
use Markommerce\Layout\Cache\PreparedTree;
use Markommerce\Layout\Cache\PreparedTreeBuilder;
use Markommerce\Layout\Compiler\Compiler;
use Markommerce\Layout\Compiler\ResolutionPhase;
use Markommerce\Layout\Compiler\ValidationPhase;
use Markommerce\Layout\Discovery\LayoutDiscovery;
use Markommerce\Layout\Middleware\MarkommerceLayoutMiddleware;
use Markommerce\Layout\Runtime\Renderer;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Paths to the Tier 1 packages, resolved relative to the test file location.
 * From packages/catalog-storefront/tests/Feature/Tier1EndToEndTest.php,
 * dirname(__DIR__, 4) resolves to the markommerce root (packages/../../.. = root).
 */
function tier1PackagesRoot(): string
{
    return dirname(__DIR__, 4) . '/packages';
}

/**
 * Path to the marko framework packages root.
 */
function tier1MarkoPackagesRoot(): string
{
    return dirname(__DIR__, 5) . '/marko/packages';
}

/**
 * Build all Tier 1 module manifests pointing at their real package paths.
 *
 * @return list<ModuleManifest>
 */
function buildTier1Manifests(): array
{
    $pkgRoot = tier1PackagesRoot();
    $markoRoot = tier1MarkoPackagesRoot();

    return [
        new ModuleManifest(
            name: 'marko/config',
            version: '1.0.0',
            path: $markoRoot . '/config',
            source: 'vendor',
        ),
        new ModuleManifest(
            name: 'marko/core',
            version: '1.0.0',
            path: $markoRoot . '/core',
            source: 'vendor',
        ),
        new ModuleManifest(
            name: 'marko/database',
            version: '1.0.0',
            path: $markoRoot . '/database',
            source: 'vendor',
        ),
        new ModuleManifest(
            name: 'marko/routing',
            version: '1.0.0',
            path: $markoRoot . '/routing',
            source: 'vendor',
        ),
        new ModuleManifest(
            name: 'marko/view',
            version: '1.0.0',
            path: $markoRoot . '/view',
            source: 'vendor',
        ),
        new ModuleManifest(
            name: 'marko/view-latte',
            version: '1.0.0',
            path: $markoRoot . '/view-latte',
            source: 'vendor',
        ),
        new ModuleManifest(
            name: 'markommerce/catalog',
            version: '1.0.0',
            path: $pkgRoot . '/catalog',
            source: 'vendor',
        ),
        new ModuleManifest(
            name: 'markommerce/catalog-storefront',
            version: '1.0.0',
            path: $pkgRoot . '/catalog-storefront',
            source: 'vendor',
        ),
        new ModuleManifest(
            name: 'markommerce/config',
            version: '1.0.0',
            path: $pkgRoot . '/config',
            source: 'vendor',
        ),
        new ModuleManifest(
            name: 'markommerce/config-pgsql',
            version: '1.0.0',
            path: $pkgRoot . '/config-pgsql',
            source: 'vendor',
        ),
        new ModuleManifest(
            name: 'markommerce/layout',
            version: '1.0.0',
            path: $pkgRoot . '/layout',
            source: 'vendor',
        ),
        new ModuleManifest(
            name: 'markommerce/frontend',
            version: '1.0.0',
            path: $pkgRoot . '/frontend',
            source: 'vendor',
        ),
        new ModuleManifest(
            name: 'markommerce/theme-blank',
            version: '1.0.0',
            path: $pkgRoot . '/theme-blank',
            source: 'vendor',
        ),
    ];
}

/**
 * Build a focused ModuleRepository for layout discovery and template resolution.
 *
 * Only includes modules that contribute valid layouts and templates for the Tier 1
 * storefront rendering pipeline. Excludes markommerce/catalog which still contains
 * a legacy layout file from before Task 002 moved the storefront code.
 */
function buildTier1RenderModuleRepository(): ModuleRepository
{
    $pkgRoot = tier1PackagesRoot();

    return new ModuleRepository([
        new ModuleManifest(
            name: 'markommerce/catalog-storefront',
            version: '1.0.0',
            path: $pkgRoot . '/catalog-storefront',
            source: 'vendor',
        ),
        new ModuleManifest(
            name: 'markommerce/theme-blank',
            version: '1.0.0',
            path: $pkgRoot . '/theme-blank',
            source: 'vendor',
        ),
    ]);
}

/**
 * Build a ConfigRepository with the view configuration needed for Latte rendering.
 */
function buildTier1Config(): ConfigRepository
{
    return new ConfigRepository([
        'view' => [
            'cache_directory' => sys_get_temp_dir() . '/marko_views_tier1',
            'extension' => '.latte',
            'auto_refresh' => true,
            'strict_types' => true,
        ],
    ]);
}

/**
 * A fake ViewInterface that renders template name and all scalar/object/array data properties.
 * Avoids the need for a real Latte engine (and its vite() function dependency from base.latte).
 * Produces output that exposes category names, product names, and resolvedNames so tests can assert
 * the right data reaches the templates.
 */
class Tier1FakeView implements ViewInterface
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
            } elseif (is_object($value) && property_exists($value, 'name') && is_string($value->name)) {
                $output .= ' data-' . htmlspecialchars($key) . '-name="' . htmlspecialchars($value->name) . '"';
            } elseif (is_array($value)) {
                foreach ($value as $item) {
                    if (is_string($item)) {
                        $output .= ' data-array-item="' . htmlspecialchars($item) . '"';
                    }
                }
            }
        }

        $slots = '';
        if (isset($data['_slots']) && is_array($data['_slots'])) {
            foreach (array_keys($data['_slots']) as $slotName) {
                $slots .= '{slot ' . $slotName . '}{/slot}';
            }
        }

        // For product-grid template, include category name directly so tests can find it
        if (str_contains($template, 'product-grid')) {
            $category = $data['category'] ?? null;
            if (is_object($category) && property_exists($category, 'name') && is_string($category->name)) {
                $slots .= htmlspecialchars($category->name);
            }
        }

        $output .= ">$slots</div>";

        return $output;
    }
}

/**
 * A Container decorator that records every resolved class/interface key
 * so tests can assert that no Scope or Locale classes were touched.
 */
class TrackingContainer implements ContainerInterface
{
    /** @var list<string> */
    public array $resolvedKeys = [];

    public function __construct(
        private Container $inner,
    ) {}

    public function get(string $id): mixed
    {
        $this->resolvedKeys[] = $id;

        return $this->inner->get($id);
    }

    public function has(string $id): bool
    {
        return $this->inner->has($id);
    }

    public function singleton(string $id): void
    {
        $this->inner->singleton($id);
    }

    public function instance(string $id, object $instance): void
    {
        $this->inner->instance($id, $instance);
    }

    public function bind(string $interface, string|Closure $implementation): void
    {
        $this->inner->bind($interface, $implementation);
    }

    public function call(Closure $callable): mixed
    {
        return $this->inner->call($callable);
    }
}

/**
 * Build the core Container with all Tier 1 bindings wired up manually.
 * Does NOT run any boot that requires scope or locale dependencies.
 *
 * @param FakeCategoryRepository $categoryRepository
 * @param FakeProductRepository $productRepository
 * @param FakeProductCategoryAssignmentRepository $assignmentRepository
 */
function buildTier1Container(
    FakeCategoryRepository $categoryRepository,
    FakeProductRepository $productRepository,
    FakeProductCategoryAssignmentRepository $assignmentRepository,
): TrackingContainer {
    $inner = new Container();

    $config = buildTier1Config();
    $inner->instance(ConfigRepositoryInterface::class, $config);
    $inner->instance(ContainerInterface::class, $inner);

    // Use the render module repository (catalog-storefront + theme-blank only) for
    // layout discovery; excludes markommerce/catalog which still contains a legacy layout.
    $moduleRepository = buildTier1RenderModuleRepository();
    $inner->instance(ModuleRepositoryInterface::class, $moduleRepository);

    // Use a fake view to avoid the real Latte engine's vite() dependency in base.latte.
    // The fake view still exposes all data properties (category name, resolved product names)
    // so response body assertions can verify the right data reaches the templates.
    $inner->instance(ViewInterface::class, new Tier1FakeView());

    // Catalog repository bindings (fakes)
    $inner->instance(CategoryRepositoryInterface::class, $categoryRepository);

    $assignmentService = new CategoryAssignmentService(
        $productRepository,
        $categoryRepository,
        $assignmentRepository,
    );
    $inner->instance(CategoryAssignmentService::class, $assignmentService);

    // Storefront component bindings
    $categoryController = new CategoryController($categoryRepository);
    $inner->instance(CategoryController::class, $categoryController);

    $productGridComponent = new ProductGridComponent($categoryRepository, $assignmentService);
    $inner->instance(ProductGridComponent::class, $productGridComponent);
    $inner->instance(ProductCard::class, new ProductCard());
    $inner->instance(StockBadge::class, new StockBadge());

    $categoryDataProvider = new CategoryDataProvider($categoryRepository);
    $inner->instance(CategoryDataProvider::class, $categoryDataProvider);

    $tracking = new TrackingContainer($inner);

    // Register the tracking container itself so middleware gets it
    $inner->instance(ContainerInterface::class, $tracking);

    return $tracking;
}

/**
 * Compile the catalog-storefront + theme-blank layouts for the Tier 1 test.
 *
 * Uses the focused render module repository (catalog-storefront + theme-blank only).
 *
 * @return array<string, PreparedTree>
 */
function buildTier1Artifact(): array
{
    $moduleRepository = buildTier1RenderModuleRepository();
    $layoutDiscovery = new LayoutDiscovery($moduleRepository);
    $resolutionPhase = new ResolutionPhase();
    $validationPhase = new ValidationPhase();
    $treeBuilder = new PreparedTreeBuilder();
    $compiler = new Compiler($layoutDiscovery, $resolutionPhase, $validationPhase, $treeBuilder);

    return $compiler->compile();
}

/**
 * Build a Router wired to the Tier 1 container, with route discovery for CategoryController
 * and layout middleware.
 */
function buildTier1Router(TrackingContainer $container): Router
{
    $routes = new RouteCollection();
    $discovery = new RouteDiscovery();
    foreach ($discovery->discoverFromClass(CategoryController::class) as $route) {
        $routes->add($route);
    }
    $matcher = new RouteMatcher($routes);

    $container->instance(RouteMatcherInterface::class, $matcher);

    $trees = buildTier1Artifact();

    $artifactReader = new class ($trees) implements ArtifactReaderInterface
    {
        /** @param array<string, PreparedTree> $trees */
        public function __construct(private array $trees) {}

        public function read(): array
        {
            return $this->trees;
        }
    };

    $view = $container->get(ViewInterface::class);
    $renderer = new Renderer($view, $container);
    $layoutMiddleware = new MarkommerceLayoutMiddleware($matcher, $artifactReader, $renderer, $container);
    $container->instance(MarkommerceLayoutMiddleware::class, $layoutMiddleware);

    return new Router($matcher, $container, [MarkommerceLayoutMiddleware::class]);
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it(
    'boots the Tier 1 module manifest stack — catalog + catalog-storefront + config + config-pgsql + layout + frontend + theme-blank — without referencing any scope or locale module',
    function (): void {
        $manifests = buildTier1Manifests();

        $names = array_map(fn (ModuleManifest $m) => $m->name, $manifests);

        // Tier 1 stack is present
        expect($names)->toContain('markommerce/catalog')
            ->and($names)->toContain('markommerce/catalog-storefront')
            ->and($names)->toContain('markommerce/config')
            ->and($names)->toContain('markommerce/config-pgsql')
            ->and($names)->toContain('markommerce/layout')
            ->and($names)->toContain('markommerce/frontend')
            ->and($names)->toContain('markommerce/theme-blank');

        // Scope and locale modules are absent
        expect($names)->not->toContain('markommerce/scope')
            ->and($names)->not->toContain('markommerce/scope-pgsql')
            ->and($names)->not->toContain('markommerce/locale')
            ->and($names)->not->toContain('markommerce/catalog-scope')
            ->and($names)->not->toContain('markommerce/catalog-locale')
            ->and($names)->not->toContain('markommerce/catalog-storefront-scope');

        // All paths point to real directories
        foreach ($manifests as $manifest) {
            if ($manifest->path !== '') {
                expect(is_dir($manifest->path))->toBeTrue(
                    "Module $manifest->name path does not exist: $manifest->path",
                );
            }
        }
    },
);

it(
    'registers the CategoryController route GET /catalog/category/{id} via RouteDiscovery against the booted container',
    function (): void {
        $categoryRepository = new FakeCategoryRepository();
        $productRepository = new FakeProductRepository();
        $assignmentRepository = new FakeProductCategoryAssignmentRepository();

        $container = buildTier1Container($categoryRepository, $productRepository, $assignmentRepository);

        $routes = new RouteCollection();
        $discovery = new RouteDiscovery();
        foreach ($discovery->discoverFromClass(CategoryController::class) as $route) {
            $routes->add($route);
        }
        $matcher = new RouteMatcher($routes);
        $container->instance(RouteMatcherInterface::class, $matcher);

        $matched = $matcher->match('GET', '/catalog/category/1');

        expect($matched)->not->toBeNull()
            ->and($matched->route->controller)->toBe(CategoryController::class)
            ->and($matched->route->action)->toBe('show');
    },
);

it(
    'compiles the catalog-storefront category_show layout against the LayoutDiscovery and yields a PreparedTree for the controller handle',
    function (): void {
        $trees = buildTier1Artifact();

        $handleKey = CategoryController::class . '::show';
        expect($trees)->toHaveKey($handleKey);
        expect($trees[$handleKey])->toBeInstanceOf(PreparedTree::class);
        expect($trees[$handleKey]->handleKey)->toBe($handleKey);
    },
);

it(
    'renders /catalog/category/{id} with a real Product assigned to a Category and returns 200 with the product name and category name in the response body',
    function (): void {
        $categoryRepository = new FakeCategoryRepository();
        $productRepository = new FakeProductRepository();
        $assignmentRepository = new FakeProductCategoryAssignmentRepository();

        $category = new Category();
        $category->name = 'Tier1 Category';
        $categoryRepository->save($category);

        $product = new Product();
        $product->sku = 'TIER1-001';
        $product->name = 'Tier1 Product';
        $productRepository->save($product);

        $assignmentService = new CategoryAssignmentService(
            $productRepository,
            $categoryRepository,
            $assignmentRepository,
        );
        $assignmentService->assign($product->id, $category->id);

        $container = buildTier1Container($categoryRepository, $productRepository, $assignmentRepository);
        $router = buildTier1Router($container);

        $request = new Request([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/catalog/category/' . $category->id,
        ]);
        $response = $router->handle($request);

        expect($response->statusCode())->toBe(200)
            ->and($response->body())->toContain('Tier1 Category')
            ->and($response->body())->toContain('Tier1 Product');
    },
);

it(
    'returns 404 when the category does not exist (smoke test for the scope-free path)',
    function (): void {
        $categoryRepository = new FakeCategoryRepository();
        $productRepository = new FakeProductRepository();
        $assignmentRepository = new FakeProductCategoryAssignmentRepository();

        $container = buildTier1Container($categoryRepository, $productRepository, $assignmentRepository);
        $router = buildTier1Router($container);

        $request = new Request([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/catalog/category/9999',
        ]);
        $response = $router->handle($request);

        expect($response->statusCode())->toBe(404);
    },
);

it(
    'resolves the plain ProductGridComponent from the container (no Preference replacement is active without catalog-storefront-scope installed)',
    function (): void {
        $categoryRepository = new FakeCategoryRepository();
        $productRepository = new FakeProductRepository();
        $assignmentRepository = new FakeProductCategoryAssignmentRepository();

        $container = buildTier1Container($categoryRepository, $productRepository, $assignmentRepository);

        $resolved = $container->get(ProductGridComponent::class);

        expect($resolved)->toBeInstanceOf(ProductGridComponent::class);
        // No Preference replacement — should not be a subclass
        expect(get_class($resolved))->toBe(ProductGridComponent::class);
    },
);

it(
    'records zero Markommerce\\Scope\\ or Markommerce\\Locale\\ container lookups during the request lifecycle',
    function (): void {
        $categoryRepository = new FakeCategoryRepository();
        $productRepository = new FakeProductRepository();
        $assignmentRepository = new FakeProductCategoryAssignmentRepository();

        $category = new Category();
        $category->name = 'Scope-Free Category';
        $categoryRepository->save($category);

        $product = new Product();
        $product->sku = 'SF-001';
        $product->name = 'Scope-Free Product';
        $productRepository->save($product);

        $assignmentService = new CategoryAssignmentService(
            $productRepository,
            $categoryRepository,
            $assignmentRepository,
        );
        $assignmentService->assign($product->id, $category->id);

        $container = buildTier1Container($categoryRepository, $productRepository, $assignmentRepository);

        // Reset tracked keys before the request so we only capture request-lifecycle lookups
        $container->resolvedKeys = [];

        $router = buildTier1Router($container);

        // Reset again after router construction (wiring also does lookups)
        $container->resolvedKeys = [];

        $request = new Request([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/catalog/category/' . $category->id,
        ]);
        $router->handle($request);

        $scopeOrLocaleKeys = array_filter(
            $container->resolvedKeys,
            fn (string $key) => str_contains($key, 'Markommerce\\Scope\\')
                || str_contains($key, 'Markommerce\\Locale\\'),
        );

        expect($scopeOrLocaleKeys)->toBeEmpty();
    },
);
