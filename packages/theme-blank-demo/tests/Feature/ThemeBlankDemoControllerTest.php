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
use Marko\Layout\ComponentCollector;
use Marko\Layout\ComponentDataResolver;
use Marko\Layout\DiscoveringComponentCollector;
use Marko\Layout\HandleResolver;
use Marko\Layout\LayoutProcessor;
use Marko\Layout\LayoutResolver;
use Marko\Layout\Middleware\LayoutMiddleware;
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
use Markommerce\ThemeBlankDemo\Config\ThemeBlankDemoConfig;
use Markommerce\ThemeBlankDemo\Controller\ThemeBlankDemoController;
use Markommerce\ThemeBlankDemo\Middleware\EnsureThemeBlankDemoEnabledMiddleware;

function themeBlankDemoTestCleanup(string $dir): void
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
            themeBlankDemoTestCleanup($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

function themeBlankDemoTestEnsureManifest(string $basePath): bool
{
    $manifestDir = $basePath . '/public/build/.vite';

    if (file_exists($manifestDir . '/manifest.json')) {
        return false;
    }

    @mkdir($manifestDir, 0755, true);
    file_put_contents($manifestDir . '/manifest.json', json_encode([
        'packages/theme-blank-demo/resources/js/main.ts' => [
            'file' => 'assets/main-abc123.js',
            'css' => ['assets/main-abc123.css'],
            'isEntry' => true,
        ],
    ]));

    return true;
}

function themeBlankDemoTestBuildRouter(
    ConfigRepositoryInterface $config,
    string $frontendPath,
    string $themeBlankDemoPath,
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
            name: 'markommerce/theme-blank-demo',
            version: '1.0.0',
            path: $themeBlankDemoPath,
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

    $themeBlankDemoConfig = new ThemeBlankDemoConfig($config);
    $ensureMiddleware = new EnsureThemeBlankDemoEnabledMiddleware($themeBlankDemoConfig);

    $routes = new RouteCollection();
    $discovery = new RouteDiscovery();
    $controllerRoutes = $discovery->discoverFromClass(ThemeBlankDemoController::class);
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
    $container->instance(ThemeBlankDemoConfig::class, $themeBlankDemoConfig);
    $container->instance(EnsureThemeBlankDemoEnabledMiddleware::class, $ensureMiddleware);
    $container->instance(ThemeBlankDemoController::class, new ThemeBlankDemoController());

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

it(
    'returns 200 and renders representative tags when theme_blank_demo.enabled is true',
    function (): void {
        $cacheDir = sys_get_temp_dir() . '/latte-theme-blank-demo-smoke-' . bin2hex(random_bytes(8));
        mkdir($cacheDir, 0755, true);

        $frontendPath = dirname(__DIR__, 2) . '/../frontend';
        $themeBlankDemoPath = dirname(__DIR__, 2);
        $basePath = $cacheDir . '/base';

        themeBlankDemoTestEnsureManifest($basePath);

        $config = new ConfigRepository([
            'theme_blank_demo' => ['enabled' => true],
            'vite' => [
                'entry' => 'packages/theme-blank-demo/resources/js/main.ts',
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

        $router = themeBlankDemoTestBuildRouter($config, $frontendPath, $themeBlankDemoPath, $basePath, $cacheDir);
        $request = new Request(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/markommerce/_demo/theme-blank']);
        $response = $router->handle($request);

        expect($response->statusCode())->toBe(200);
        $body = $response->body();
        expect($body)->toMatch('/<(script|link)/');
        expect($body)->toContain('<mk-stack');

        themeBlankDemoTestCleanup($cacheDir);
    },
);

it(
    'returns 404 when theme_blank_demo.enabled is false because EnsureThemeBlankDemoEnabledMiddleware short-circuits before LayoutMiddleware runs',
    function (): void {
        $cacheDir = sys_get_temp_dir() . '/latte-theme-blank-demo-404-' . bin2hex(random_bytes(8));
        mkdir($cacheDir, 0755, true);

        $frontendPath = dirname(__DIR__, 2) . '/../frontend';
        $themeBlankDemoPath = dirname(__DIR__, 2);
        $basePath = $cacheDir . '/base';

        $config = new ConfigRepository([
            'theme_blank_demo' => ['enabled' => false],
            'vite' => [
                'entry' => 'packages/theme-blank-demo/resources/js/main.ts',
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

        $router = themeBlankDemoTestBuildRouter(
            $config,
            $frontendPath,
            $themeBlankDemoPath,
            $basePath,
            $cacheDir,
            withLayoutMiddleware: false,
        );
        $request = new Request(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/markommerce/_demo/theme-blank']);
        $response = $router->handle($request);

        expect($response->statusCode())->toBe(404);

        themeBlankDemoTestCleanup($cacheDir);
    },
);
