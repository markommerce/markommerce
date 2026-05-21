<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container as CoreContainer;
use Marko\Core\Discovery\ClassFileParser;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepository;
use Marko\Core\Module\ModuleRepositoryInterface;
use Marko\Core\Path\ProjectPaths;
use Marko\Layout\Attributes\Layout;
use Marko\Layout\ComponentCollector;
use Marko\Layout\ComponentDataResolver;
use Marko\Layout\DiscoveringComponentCollector;
use Marko\Layout\HandleResolver;
use Marko\Layout\LayoutProcessor;
use Marko\Layout\LayoutResolver;
use Marko\Layout\Middleware\LayoutMiddleware;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Request;
use Marko\Routing\RouteCollection;
use Marko\Routing\RouteDiscovery;
use Marko\Routing\RouteMatcher;
use Marko\Routing\RouteMatcherInterface;
use Marko\Routing\Router;
use Marko\View\Latte\LatteView;
use Marko\View\ModuleTemplateResolver;
use Marko\View\TemplateResolverInterface;
use Marko\View\ViewConfig;
use Marko\View\ViewInterface;
use Marko\Vite\Vite;
use Markommerce\Catalog\Contracts\CategoryRepositoryInterface;
use Markommerce\Catalog\Controller\CategoryController;
use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Services\CategoryAssignmentService;
use Markommerce\Catalog\Tests\Support\FakeCategoryRepository;
use Markommerce\Catalog\Tests\Support\FakeProductCategoryAssignmentRepository;
use Markommerce\Catalog\Tests\Support\FakeProductRepository;
use Markommerce\Frontend\View\Latte\MarkommerceLatteEngineFactory;
use Markommerce\Frontend\View\Latte\ViteExtension;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Metadata\ScopeMetadataFactory;
use Markommerce\Scope\Registry\PhpScopeRegistry;
use Markommerce\Scope\Resolution\ScopeWalker;
use Markommerce\Scope\Resolver\ScopeResolver;
use Markommerce\Scope\Signature\ScopeSignatureValidator;
use Markommerce\Scope\Signature\SignatureCandidateEnumerator;
use Markommerce\Scope\Storage\DefaultScopeGuard;
use Markommerce\ThemeBlank\Layout\OneColumnLayout;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function catalogControllerBuildScopeResolver(): ScopeResolver
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

function catalogControllerTestEnsureManifest(string $basePath): bool
{
    $manifestDir = $basePath . '/public/build/.vite';

    if (file_exists($manifestDir . '/manifest.json')) {
        return false;
    }

    @mkdir($manifestDir, 0755, true);
    file_put_contents($manifestDir . '/manifest.json', json_encode([
        'packages/theme-blank/resources/js/index.ts' => [
            'file' => 'assets/index-abc123.js',
            'css' => ['assets/index-abc123.css'],
            'isEntry' => true,
        ],
    ]));

    return true;
}

function catalogControllerTestBuildRouter(
    ConfigRepositoryInterface $config,
    string $frontendPath,
    string $themeBlankPath,
    string $catalogPath,
    string $basePath,
    string $cacheDir,
    FakeCategoryRepository $categoryRepository,
    FakeProductRepository $productRepository,
    FakeProductCategoryAssignmentRepository $assignmentRepository,
    bool $withLayoutMiddleware = true,
): Router {
    $moduleRepository = new ModuleRepository([
        new ModuleManifest(
            name: 'markommerce/frontend',
            version: '1.0.0',
            path: $frontendPath,
            source: 'vendor',
        ),
        new ModuleManifest(
            name: 'markommerce/theme-blank',
            version: '1.0.0',
            path: $themeBlankPath,
            source: 'vendor',
        ),
        new ModuleManifest(
            name: 'markommerce/catalog',
            version: '1.0.0',
            path: $catalogPath,
            source: 'vendor',
        ),
    ]);

    $viewConfig = new ViewConfig($config);
    $templateResolver = new ModuleTemplateResolver($moduleRepository, $viewConfig);
    $paths = new ProjectPaths($basePath);
    $vite = new Vite($config, $paths);
    $viteExtension = new ViteExtension($vite, $config);
    $engineFactory = new MarkommerceLatteEngineFactory($viewConfig, $viteExtension);
    $engine = $engineFactory->create();
    $view = new LatteView($engine, $templateResolver);

    $routes = new RouteCollection();
    $discovery = new RouteDiscovery();
    $controllerRoutes = $discovery->discoverFromClass(CategoryController::class);
    foreach ($controllerRoutes as $route) {
        $routes->add($route);
    }

    $matcher = new RouteMatcher($routes);
    $layoutResolver = new LayoutResolver();
    $handleResolver = new HandleResolver();

    $scopeResolver = catalogControllerBuildScopeResolver();
    $assignmentService = new CategoryAssignmentService(
        $productRepository,
        $categoryRepository,
        $assignmentRepository,
    );

    $container = new CoreContainer();
    $container->instance(ConfigRepositoryInterface::class, $config);
    $container->instance(ModuleRepositoryInterface::class, $moduleRepository);
    $container->instance(ViewInterface::class, $view);
    $container->instance(RouteMatcherInterface::class, $matcher);
    $container->instance(TemplateResolverInterface::class, $templateResolver);
    $container->instance(CategoryRepositoryInterface::class, $categoryRepository);
    $container->instance(CategoryController::class, new CategoryController($categoryRepository));
    $container->instance(ScopeResolver::class, $scopeResolver);
    $container->instance(CategoryAssignmentService::class, $assignmentService);

    $classFileParser = new ClassFileParser();
    $innerCollector = new ComponentCollector($handleResolver, $routes);
    $componentCollector = new DiscoveringComponentCollector($moduleRepository, $classFileParser, $innerCollector);
    $componentDataResolver = new ComponentDataResolver();

    $layoutProcessor = new LayoutProcessor(
        $container,
        $layoutResolver,
        $handleResolver,
        $componentCollector,
        $componentDataResolver,
        $view,
    );

    $globalMiddleware = [];

    if ($withLayoutMiddleware) {
        $layoutMiddleware = new LayoutMiddleware($matcher, $layoutProcessor, $layoutResolver);
        $container->instance(LayoutMiddleware::class, $layoutMiddleware);
        $globalMiddleware = [LayoutMiddleware::class];
    }

    return new Router($matcher, $container, $globalMiddleware);
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

it('declares theme-blank\'s OneColumnLayout via the Layout attribute on the controller class', function (): void {
    $reflection = new ReflectionClass(CategoryController::class);
    $layoutAttributes = $reflection->getAttributes(Layout::class);

    expect($layoutAttributes)->not->toBeEmpty();

    $layoutAttr = $layoutAttributes[0]->newInstance();
    expect($layoutAttr->component)->toBe(OneColumnLayout::class);
});

it('returns a 200 response with the assembled layout HTML when the category exists', function (): void {
    $cacheDir = sys_get_temp_dir() . '/latte-catalog-controller-200-' . bin2hex(random_bytes(8));
    mkdir($cacheDir, 0755, true);

    $frontendPath = dirname(__DIR__, 2) . '/../frontend';
    $themeBlankPath = dirname(__DIR__, 2) . '/../theme-blank';
    $catalogPath = dirname(__DIR__, 2);
    $basePath = $cacheDir . '/base';

    catalogControllerTestEnsureManifest($basePath);

    $config = new ConfigRepository([
        'vite' => [
            'entry' => 'packages/theme-blank/resources/js/index.ts',
            'buildDirectory' => 'build',
            'manifestFilename' => '.vite/manifest.json',
            'devServerUrl' => 'http://localhost:5173',
            'useDevServer' => false,
            'devServerStylesheets' => [],
        ],
        'view' => [
            'cache_directory' => $cacheDir,
            'extension' => '.latte',
            'auto_refresh' => true,
            'strict_types' => false,
        ],
    ]);

    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Test Category';
    $categoryRepository->save($category);

    $router = catalogControllerTestBuildRouter(
        $config,
        $frontendPath,
        $themeBlankPath,
        $catalogPath,
        $basePath,
        $cacheDir,
        $categoryRepository,
        $productRepository,
        $assignmentRepository,
    );

    $request = new Request(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/catalog/category/' . $category->id]);
    $response = $router->handle($request);

    expect($response->statusCode())->toBe(200);

    catalogControllerTestCleanup($cacheDir);
});

it('returns a 404 response when the requested category id does not exist', function (): void {
    $cacheDir = sys_get_temp_dir() . '/latte-catalog-controller-404-' . bin2hex(random_bytes(8));
    mkdir($cacheDir, 0755, true);

    $frontendPath = dirname(__DIR__, 2) . '/../frontend';
    $themeBlankPath = dirname(__DIR__, 2) . '/../theme-blank';
    $catalogPath = dirname(__DIR__, 2);
    $basePath = $cacheDir . '/base';

    catalogControllerTestEnsureManifest($basePath);

    $config = new ConfigRepository([
        'vite' => [
            'entry' => 'packages/theme-blank/resources/js/index.ts',
            'buildDirectory' => 'build',
            'manifestFilename' => '.vite/manifest.json',
            'devServerUrl' => 'http://localhost:5173',
            'useDevServer' => false,
            'devServerStylesheets' => [],
        ],
        'view' => [
            'cache_directory' => $cacheDir,
            'extension' => '.latte',
            'auto_refresh' => true,
            'strict_types' => false,
        ],
    ]);

    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $router = catalogControllerTestBuildRouter(
        $config,
        $frontendPath,
        $themeBlankPath,
        $catalogPath,
        $basePath,
        $cacheDir,
        $categoryRepository,
        $productRepository,
        $assignmentRepository,
    );

    $request = new Request(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/catalog/category/9999']);
    $response = $router->handle($request);

    expect($response->statusCode())->toBe(404);

    catalogControllerTestCleanup($cacheDir);
});

it('includes the category name in the rendered page heading', function (): void {
    $cacheDir = sys_get_temp_dir() . '/latte-catalog-controller-heading-' . bin2hex(random_bytes(8));
    mkdir($cacheDir, 0755, true);

    $frontendPath = dirname(__DIR__, 2) . '/../frontend';
    $themeBlankPath = dirname(__DIR__, 2) . '/../theme-blank';
    $catalogPath = dirname(__DIR__, 2);
    $basePath = $cacheDir . '/base';

    catalogControllerTestEnsureManifest($basePath);

    $config = new ConfigRepository([
        'vite' => [
            'entry' => 'packages/theme-blank/resources/js/index.ts',
            'buildDirectory' => 'build',
            'manifestFilename' => '.vite/manifest.json',
            'devServerUrl' => 'http://localhost:5173',
            'useDevServer' => false,
            'devServerStylesheets' => [],
        ],
        'view' => [
            'cache_directory' => $cacheDir,
            'extension' => '.latte',
            'auto_refresh' => true,
            'strict_types' => false,
        ],
    ]);

    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Featured Electronics';
    $categoryRepository->save($category);

    $router = catalogControllerTestBuildRouter(
        $config,
        $frontendPath,
        $themeBlankPath,
        $catalogPath,
        $basePath,
        $cacheDir,
        $categoryRepository,
        $productRepository,
        $assignmentRepository,
    );

    $request = new Request(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/catalog/category/' . $category->id]);
    $response = $router->handle($request);

    expect($response->body())->toContain('Featured Electronics');

    catalogControllerTestCleanup($cacheDir);
});

it('renders every assigned product as a product grid item in the response body', function (): void {
    $cacheDir = sys_get_temp_dir() . '/latte-catalog-controller-products-' . bin2hex(random_bytes(8));
    mkdir($cacheDir, 0755, true);

    $frontendPath = dirname(__DIR__, 2) . '/../frontend';
    $themeBlankPath = dirname(__DIR__, 2) . '/../theme-blank';
    $catalogPath = dirname(__DIR__, 2);
    $basePath = $cacheDir . '/base';

    catalogControllerTestEnsureManifest($basePath);

    $config = new ConfigRepository([
        'vite' => [
            'entry' => 'packages/theme-blank/resources/js/index.ts',
            'buildDirectory' => 'build',
            'manifestFilename' => '.vite/manifest.json',
            'devServerUrl' => 'http://localhost:5173',
            'useDevServer' => false,
            'devServerStylesheets' => [],
        ],
        'view' => [
            'cache_directory' => $cacheDir,
            'extension' => '.latte',
            'auto_refresh' => true,
            'strict_types' => false,
        ],
    ]);

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
        $config,
        $frontendPath,
        $themeBlankPath,
        $catalogPath,
        $basePath,
        $cacheDir,
        $categoryRepository,
        $productRepository,
        $assignmentRepository,
    );

    $request = new Request(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/catalog/category/' . $category->id]);
    $response = $router->handle($request);

    expect($response->body())
        ->toContain('Running Shoes')
        ->toContain('Hiking Boots')
        ->toContain('<article');

    catalogControllerTestCleanup($cacheDir);
});

it('renders an empty-state message when the category has no products', function (): void {
    $cacheDir = sys_get_temp_dir() . '/latte-catalog-controller-empty-' . bin2hex(random_bytes(8));
    mkdir($cacheDir, 0755, true);

    $frontendPath = dirname(__DIR__, 2) . '/../frontend';
    $themeBlankPath = dirname(__DIR__, 2) . '/../theme-blank';
    $catalogPath = dirname(__DIR__, 2);
    $basePath = $cacheDir . '/base';

    catalogControllerTestEnsureManifest($basePath);

    $config = new ConfigRepository([
        'vite' => [
            'entry' => 'packages/theme-blank/resources/js/index.ts',
            'buildDirectory' => 'build',
            'manifestFilename' => '.vite/manifest.json',
            'devServerUrl' => 'http://localhost:5173',
            'useDevServer' => false,
            'devServerStylesheets' => [],
        ],
        'view' => [
            'cache_directory' => $cacheDir,
            'extension' => '.latte',
            'auto_refresh' => true,
            'strict_types' => false,
        ],
    ]);

    $categoryRepository = new FakeCategoryRepository();
    $productRepository = new FakeProductRepository();
    $assignmentRepository = new FakeProductCategoryAssignmentRepository();

    $category = new Category();
    $category->name = 'Empty Category';
    $categoryRepository->save($category);

    $router = catalogControllerTestBuildRouter(
        $config,
        $frontendPath,
        $themeBlankPath,
        $catalogPath,
        $basePath,
        $cacheDir,
        $categoryRepository,
        $productRepository,
        $assignmentRepository,
    );

    $request = new Request(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/catalog/category/' . $category->id]);
    $response = $router->handle($request);

    expect($response->body())->toContain('No products found');

    catalogControllerTestCleanup($cacheDir);
});

it('removes the standalone resources/views/category.latte template', function (): void {
    $templatePath = dirname(__DIR__, 2) . '/resources/views/category.latte';

    expect(file_exists($templatePath))->toBeFalse();
});
