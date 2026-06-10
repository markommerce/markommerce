<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container as CoreContainer;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepository;
use Marko\Core\Module\ModuleRepositoryInterface;
use Marko\Core\Path\ProjectPaths;
use Marko\Routing\Http\Request;
use Marko\Routing\RouteCollection;
use Marko\Routing\RouteDiscovery;
use Marko\Routing\RouteMatcher;
use Marko\Routing\RouteMatcherInterface;
use Marko\Routing\Router;
use Marko\View\Latte\LatteView;
use Marko\View\Latte\LatteViewConfig;
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
use Markommerce\FrontendDemo\Middleware\EnsureFrontendDemoEnabledMiddleware;
use Markommerce\Layout\Cache\ArtifactReaderInterface;
use Markommerce\Layout\Cache\PreparedTree;
use Markommerce\Layout\Cache\PreparedTreeBuilder;
use Markommerce\Layout\Compiler\Compiler;
use Markommerce\Layout\Compiler\ResolutionPhase;
use Markommerce\Layout\Compiler\ValidationPhase;
use Markommerce\Layout\Discovery\LayoutDiscovery;
use Markommerce\Layout\Middleware\MarkommerceLayoutMiddleware;
use Markommerce\Layout\Runtime\Renderer;

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

/**
 * Build a compiled layout artifact (prepared trees) for the frontend-demo layout.
 *
 * @return array<string, PreparedTree>
 */
function demoTestBuildArtifact(string $frontendDemoPath): array
{
    $moduleRepository = new ModuleRepository([
        new ModuleManifest(
            name: 'markommerce/frontend-demo',
            version: '1.0.0',
            path: $frontendDemoPath,
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

function demoTestBuildRouter(
    ConfigRepositoryInterface $config,
    string $frontendPath,
    string $frontendDemoPath,
    string $basePath,
    string $cacheDir,
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
    $latteViewConfig = new LatteViewConfig($config);
    $templateResolver = new ModuleTemplateResolver($moduleRepository, $viewConfig);
    $paths = new ProjectPaths($basePath);
    $vite = new Vite($config, $paths);
    $viteExtension = new ViteExtension($vite, $config);
    $engineFactory = new MarkommerceLatteEngineFactory($viewConfig, $latteViewConfig, $viteExtension);
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

    $container = new CoreContainer();
    $container->instance(ConfigRepositoryInterface::class, $config);
    $container->instance(ModuleRepositoryInterface::class, $moduleRepository);
    $container->instance(ViewInterface::class, $view);
    $container->instance(RouteMatcherInterface::class, $matcher);
    $container->instance(TemplateResolverInterface::class, $templateResolver);
    $container->instance(FrontendDemoConfig::class, $frontendDemoConfig);
    $container->instance(EnsureFrontendDemoEnabledMiddleware::class, $ensureMiddleware);
    $container->instance(DemoController::class, new DemoController());
    $container->instance(DemoCounterComponent::class, new DemoCounterComponent());

    $trees = demoTestBuildArtifact($frontendDemoPath);

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

it('it returns 200 OK when the demo route is requested', function (): void {
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
    expect($response->body())->toContain('<markommerce-counter');

    demoTestCleanup($cacheDir);
    if ($manifestCreated) {
        @unlink($basePath . '/public/build/.vite/manifest.json');
    }
});

it('it returns 404 when frontend_demo.enabled is false', function (): void {
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

    $router = demoTestBuildRouter($config, $frontendPath, $frontendDemoPath, $basePath, $cacheDir);
    $request = new Request(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/markommerce/_demo']);
    $response = $router->handle($request);

    expect($response->statusCode())->toBe(404);

    demoTestCleanup($cacheDir);
});
