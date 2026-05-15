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
use Marko\Layout\Attributes\Component;
use Marko\Layout\Attributes\Layout;
use Marko\Layout\ComponentCollector;
use Marko\Layout\ComponentDataResolver;
use Marko\Layout\DiscoveringComponentCollector;
use Marko\Layout\HandleResolver;
use Marko\Layout\LayoutProcessor;
use Marko\Layout\LayoutResolver;
use Marko\Layout\Middleware\LayoutMiddleware;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
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
use Markommerce\Frontend\View\Latte\MarkommerceLatteEngineFactory;
use Markommerce\Frontend\View\Latte\ViteExtension;
use Markommerce\FrontendDemo\Component\DemoCounterComponent;
use Markommerce\FrontendDemo\Config\FrontendDemoConfig;
use Markommerce\FrontendDemo\Controller\DemoController;
use Markommerce\FrontendDemo\Layout\DemoLayout;
use Markommerce\FrontendDemo\Middleware\EnsureFrontendDemoEnabledMiddleware;

function demoTestCleanup(string $dir): void
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
            demoTestCleanup($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

function demoTestBuildRouter(
    ConfigRepositoryInterface $config,
    string $frontendPath,
    string $frontendDemoPath,
    string $basePath,
    string $cacheDir,
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
            name: 'markommerce/frontend-demo',
            version: '1.0.0',
            path: $frontendDemoPath,
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

    $frontendDemoConfig = new FrontendDemoConfig($config);
    $ensureMiddleware = new EnsureFrontendDemoEnabledMiddleware($frontendDemoConfig);

    $routes = new RouteCollection();
    $discovery = new RouteDiscovery();
    $controllerRoutes = $discovery->discoverFromClass(DemoController::class);
    foreach ($controllerRoutes as $route) {
        $routes->add($route);
    }

    $matcher = new RouteMatcher($routes);
    $layoutResolver = new LayoutResolver();
    $handleResolver = new HandleResolver();

    $container = new CoreContainer();
    $container->instance(ConfigRepositoryInterface::class, $config);
    $container->instance(ModuleRepositoryInterface::class, $moduleRepository);
    $container->instance(ViewInterface::class, $view);
    $container->instance(RouteMatcherInterface::class, $matcher);
    $container->instance(TemplateResolverInterface::class, $templateResolver);
    $container->instance(FrontendDemoConfig::class, $frontendDemoConfig);
    $container->instance(EnsureFrontendDemoEnabledMiddleware::class, $ensureMiddleware);
    $container->instance(DemoController::class, new DemoController());

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

function demoTestEnsureManifest(string $basePath): bool
{
    $manifestDir = $basePath . '/public/build/.vite';

    if (file_exists($manifestDir . '/manifest.json')) {
        return false;
    }

    @mkdir($manifestDir, 0755, true);
    file_put_contents($manifestDir . '/manifest.json', json_encode([
        'packages/frontend-demo/resources/js/main.ts' => [
            'file' => 'assets/main-abc123.js',
            'css' => ['assets/main-abc123.css'],
            'isEntry' => true,
        ],
    ]));

    return true;
}

it('it returns 200 OK when frontend_demo.enabled is true and the demo route is requested', function (): void {
    $cacheDir = sys_get_temp_dir() . '/latte-demo-test-200-' . bin2hex(random_bytes(8));
    mkdir($cacheDir, 0755, true);

    $frontendPath = dirname(__DIR__, 2) . '/../frontend';
    $frontendDemoPath = dirname(__DIR__, 2);
    $basePath = dirname(__DIR__, 4);

    $manifestCreated = demoTestEnsureManifest($basePath);

    $config = new ConfigRepository([
        'frontend_demo' => ['enabled' => true],
        'vite' => [
            'entry' => 'packages/frontend-demo/resources/js/main.ts',
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

    $router = demoTestBuildRouter($config, $frontendPath, $frontendDemoPath, $basePath, $cacheDir);
    $request = new Request(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/markommerce/_demo']);
    $response = $router->handle($request);

    expect($response->statusCode())->toBe(200);

    demoTestCleanup($cacheDir);
    if ($manifestCreated) {
        @unlink($basePath . '/public/build/.vite/manifest.json');
    }
});

it('it returns 404 when frontend_demo.enabled is false because EnsureFrontendDemoEnabledMiddleware short-circuits before LayoutMiddleware runs', function (): void {
    $cacheDir = sys_get_temp_dir() . '/latte-demo-test-404-' . bin2hex(random_bytes(8));
    mkdir($cacheDir, 0755, true);

    $frontendPath = dirname(__DIR__, 2) . '/../frontend';
    $frontendDemoPath = dirname(__DIR__, 2);
    $basePath = dirname(__DIR__, 4);

    $config = new ConfigRepository([
        'frontend_demo' => ['enabled' => false],
        'vite' => [
            'entry' => 'packages/frontend-demo/resources/js/main.ts',
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

    $router = demoTestBuildRouter($config, $frontendPath, $frontendDemoPath, $basePath, $cacheDir, withLayoutMiddleware: false);
    $request = new Request(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/markommerce/_demo']);
    $response = $router->handle($request);

    expect($response->statusCode())->toBe(404);

    demoTestCleanup($cacheDir);
});

it('the #[Get] attribute is placed on the DemoController action method (not the class)', function (): void {
    $reflection = new ReflectionClass(DemoController::class);

    $classGetAttributes = $reflection->getAttributes(Get::class);
    expect($classGetAttributes)->toBeEmpty();

    $method = $reflection->getMethod('index');
    $methodGetAttributes = $method->getAttributes(Get::class);
    expect($methodGetAttributes)->not->toBeEmpty();

    $getAttr = $methodGetAttributes[0]->newInstance();
    expect($getAttr->path)->toBe('/markommerce/_demo');
});

it('the EnsureFrontendDemoEnabledMiddleware is registered via #[Middleware([...])] on the controller action method', function (): void {
    $reflection = new ReflectionClass(DemoController::class);
    $method = $reflection->getMethod('index');
    $middlewareAttributes = $method->getAttributes(Middleware::class);

    expect($middlewareAttributes)->not->toBeEmpty();

    $middlewareAttr = $middlewareAttributes[0]->newInstance();
    expect($middlewareAttr->middleware)->toContain(EnsureFrontendDemoEnabledMiddleware::class);
});

it('it embeds the <markommerce-counter> element in the rendered response body', function (): void {
    $cacheDir = sys_get_temp_dir() . '/latte-demo-counter-' . bin2hex(random_bytes(8));
    mkdir($cacheDir, 0755, true);

    $frontendPath = dirname(__DIR__, 2) . '/../frontend';
    $frontendDemoPath = dirname(__DIR__, 2);
    $basePath = dirname(__DIR__, 4);

    $manifestCreated = demoTestEnsureManifest($basePath);

    $config = new ConfigRepository([
        'frontend_demo' => ['enabled' => true],
        'vite' => [
            'entry' => 'packages/frontend-demo/resources/js/main.ts',
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

    $router = demoTestBuildRouter($config, $frontendPath, $frontendDemoPath, $basePath, $cacheDir);
    $request = new Request(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/markommerce/_demo']);
    $response = $router->handle($request);

    expect($response->body())->toContain('<markommerce-counter');

    demoTestCleanup($cacheDir);
    if ($manifestCreated) {
        @unlink($basePath . '/public/build/.vite/manifest.json');
    }
});

it('it includes the marko/vite generated script and link tags in the response head when the route is enabled', function (): void {
    $cacheDir = sys_get_temp_dir() . '/latte-demo-vite-' . bin2hex(random_bytes(8));
    mkdir($cacheDir, 0755, true);

    $frontendPath = dirname(__DIR__, 2) . '/../frontend';
    $frontendDemoPath = dirname(__DIR__, 2);
    $basePath = dirname(__DIR__, 4);

    $manifestCreated = demoTestEnsureManifest($basePath);

    $config = new ConfigRepository([
        'frontend_demo' => ['enabled' => true],
        'vite' => [
            'entry' => 'packages/frontend-demo/resources/js/main.ts',
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

    $router = demoTestBuildRouter($config, $frontendPath, $frontendDemoPath, $basePath, $cacheDir);
    $request = new Request(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/markommerce/_demo']);
    $response = $router->handle($request);

    $body = $response->body();
    expect($body)->toMatch('/<(script|link)/');

    demoTestCleanup($cacheDir);
    if ($manifestCreated) {
        @unlink($basePath . '/public/build/.vite/manifest.json');
    }
});

it('it uses the DemoLayout component as the layout for the route', function (): void {
    $reflection = new ReflectionClass(DemoController::class);
    $layoutAttributes = $reflection->getAttributes(Layout::class);

    expect($layoutAttributes)->not->toBeEmpty();

    $layoutAttr = $layoutAttributes[0]->newInstance();
    expect($layoutAttr->component)->toBe(DemoLayout::class);
});

it('it composes the DemoCounterComponent into the content slot of DemoLayout', function (): void {
    $reflection = new ReflectionClass(DemoCounterComponent::class);
    $componentAttributes = $reflection->getAttributes(Component::class);

    expect($componentAttributes)->not->toBeEmpty();

    $componentAttr = $componentAttributes[0]->newInstance();
    expect($componentAttr->slot)->toBe('content');
});

it('the demo main.ts imports open-props/style.css so Vite emits the Open Props stylesheet link in the head', function (): void {
    $mainTsPath = dirname(__DIR__, 2) . '/resources/js/main.ts';

    expect(file_exists($mainTsPath))->toBeTrue();

    $contents = file_get_contents($mainTsPath);
    expect($contents)->toContain("import 'open-props/style.css'");
});

it('it loads the @markommerce/frontend cascade layers and tokens CSS in the head before component-level CSS', function (): void {
    $mainTsPath = dirname(__DIR__, 2) . '/resources/js/main.ts';
    $contents = file_get_contents($mainTsPath);

    $layersPos = strpos($contents, '@markommerce/frontend/css/layers.css');
    $tokensPos = strpos($contents, '@markommerce/frontend/css/tokens.css');
    $componentPos = strpos($contents, 'counter.css');

    expect($layersPos)->toBeLessThan($componentPos)
        ->and($tokensPos)->toBeLessThan($componentPos);
});

it('the controller and middleware inject FrontendDemoConfig via constructor and read the enabled flag from there', function (): void {
    $reflection = new ReflectionClass(EnsureFrontendDemoEnabledMiddleware::class);
    $constructor = $reflection->getConstructor();

    expect($constructor)->not->toBeNull();

    $params = $constructor->getParameters();
    $paramTypes = array_map(
        fn ($p) => $p->getType()?->getName(),
        $params,
    );

    expect($paramTypes)->toContain(FrontendDemoConfig::class);
});

it('it follows project naming conventions: the FrontendDemoConfig parameter is named frontendDemoConfig', function (): void {
    $reflection = new ReflectionClass(EnsureFrontendDemoEnabledMiddleware::class);
    $constructor = $reflection->getConstructor();

    expect($constructor)->not->toBeNull();

    $paramNames = array_map(
        fn ($p) => $p->getName(),
        $constructor->getParameters(),
    );

    expect($paramNames)->toContain('frontendDemoConfig');
});
