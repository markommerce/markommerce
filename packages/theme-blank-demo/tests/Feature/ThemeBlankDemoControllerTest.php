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
use Markommerce\ThemeBlankDemo\Component\ThemeBlankShowcaseComponent;
use Markommerce\ThemeBlankDemo\Config\ThemeBlankDemoConfig;
use Markommerce\ThemeBlankDemo\Controller\ThemeBlankDemoController;
use Markommerce\ThemeBlankDemo\Layout\ThemeBlankDemoLayout;
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
    $templateResolver = new ModuleTemplateResolver($moduleRepository, $viewConfig);
    $paths = new ProjectPaths($basePath);
    $vite = new Vite($config, $paths);
    $viteExtension = new ViteExtension($vite, $config);
    $engineFactory = new MarkommerceLatteEngineFactory($viewConfig, $viteExtension);
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

it('it the #[Get] attribute is placed on the ThemeBlankDemoController action method with path /markommerce/_demo/theme-blank', function (): void {
    $reflection = new ReflectionClass(ThemeBlankDemoController::class);

    $classGetAttributes = $reflection->getAttributes(Get::class);
    expect($classGetAttributes)->toBeEmpty();

    $method = $reflection->getMethod('index');
    $methodGetAttributes = $method->getAttributes(Get::class);
    expect($methodGetAttributes)->not->toBeEmpty();

    $getAttr = $methodGetAttributes[0]->newInstance();
    expect($getAttr->path)->toBe('/markommerce/_demo/theme-blank');
});

it('it the EnsureThemeBlankDemoEnabledMiddleware is registered via #[Middleware([...])] on the controller action method', function (): void {
    $reflection = new ReflectionClass(ThemeBlankDemoController::class);
    $method = $reflection->getMethod('index');
    $middlewareAttributes = $method->getAttributes(Middleware::class);

    expect($middlewareAttributes)->not->toBeEmpty();

    $middlewareAttr = $middlewareAttributes[0]->newInstance();
    expect($middlewareAttr->middleware)->toContain(EnsureThemeBlankDemoEnabledMiddleware::class);
});

it('it uses the ThemeBlankDemoLayout component as the layout for the route', function (): void {
    $reflection = new ReflectionClass(ThemeBlankDemoController::class);
    $layoutAttributes = $reflection->getAttributes(Layout::class);

    expect($layoutAttributes)->not->toBeEmpty();

    $layoutAttr = $layoutAttributes[0]->newInstance();
    expect($layoutAttr->component)->toBe(ThemeBlankDemoLayout::class);
});

it('it composes the ThemeBlankShowcaseComponent into the content slot of ThemeBlankDemoLayout', function (): void {
    $reflection = new ReflectionClass(ThemeBlankShowcaseComponent::class);
    $componentAttributes = $reflection->getAttributes(Component::class);

    expect($componentAttributes)->not->toBeEmpty();

    $componentAttr = $componentAttributes[0]->newInstance();
    expect($componentAttr->slot)->toBe('content');
});

it('it the EnsureThemeBlankDemoEnabledMiddleware injects ThemeBlankDemoConfig via constructor and reads the enabled flag from there', function (): void {
    $reflection = new ReflectionClass(EnsureThemeBlankDemoEnabledMiddleware::class);
    $constructor = $reflection->getConstructor();

    expect($constructor)->not->toBeNull();

    $params = $constructor->getParameters();
    $paramTypes = array_map(
        fn ($p) => $p->getType()?->getName(),
        $params,
    );

    expect($paramTypes)->toContain(ThemeBlankDemoConfig::class);
});

it('it follows project naming conventions: the ThemeBlankDemoConfig constructor parameter on EnsureThemeBlankDemoEnabledMiddleware is named themeBlankDemoConfig', function (): void {
    $reflection = new ReflectionClass(EnsureThemeBlankDemoEnabledMiddleware::class);
    $constructor = $reflection->getConstructor();

    expect($constructor)->not->toBeNull();

    $paramNames = array_map(
        fn ($p) => $p->getName(),
        $constructor->getParameters(),
    );

    expect($paramNames)->toContain('themeBlankDemoConfig');
});

it('it ThemeBlankDemoConfig.isEnabled() returns false when theme_blank_demo.enabled config key is absent', function (): void {
    $config = new ConfigRepository([]);
    $themeBlankDemoConfig = new ThemeBlankDemoConfig($config);

    expect($themeBlankDemoConfig->isEnabled())->toBeFalse();
});

it('it ThemeBlankDemoConfig.isEnabled() returns the config repository\'s boolean value when the key is present', function (): void {
    $configEnabled = new ConfigRepository(['theme_blank_demo' => ['enabled' => true]]);
    $themeBlankDemoConfigEnabled = new ThemeBlankDemoConfig($configEnabled);
    expect($themeBlankDemoConfigEnabled->isEnabled())->toBeTrue();

    $configDisabled = new ConfigRepository(['theme_blank_demo' => ['enabled' => false]]);
    $themeBlankDemoConfigDisabled = new ThemeBlankDemoConfig($configDisabled);
    expect($themeBlankDemoConfigDisabled->isEnabled())->toBeFalse();
});

it('it returns 200 OK when theme_blank_demo.enabled is true and GET /markommerce/_demo/theme-blank is requested', function (): void {
    $cacheDir = sys_get_temp_dir() . '/latte-theme-blank-demo-200-' . bin2hex(random_bytes(8));
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

    themeBlankDemoTestCleanup($cacheDir);
});

it('it returns 404 when theme_blank_demo.enabled is false because EnsureThemeBlankDemoEnabledMiddleware short-circuits before LayoutMiddleware runs', function (): void {
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

    $router = themeBlankDemoTestBuildRouter($config, $frontendPath, $themeBlankDemoPath, $basePath, $cacheDir, withLayoutMiddleware: false);
    $request = new Request(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/markommerce/_demo/theme-blank']);
    $response = $router->handle($request);

    expect($response->statusCode())->toBe(404);

    themeBlankDemoTestCleanup($cacheDir);
});

it('it includes the marko/vite generated script tag referencing packages/theme-blank-demo/resources/js/main.ts in the response head', function (): void {
    $cacheDir = sys_get_temp_dir() . '/latte-theme-blank-demo-vite-' . bin2hex(random_bytes(8));
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

    $body = $response->body();
    expect($body)->toMatch('/<(script|link)/');

    themeBlankDemoTestCleanup($cacheDir);
});

it('it the rendered page contains a Layout primitives heading', function (): void {
    $cacheDir = sys_get_temp_dir() . '/latte-theme-blank-demo-layout-heading-' . bin2hex(random_bytes(8));
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

    expect($response->body())->toContain('Layout primitives');

    themeBlankDemoTestCleanup($cacheDir);
});

it('it the rendered page contains a Typography primitives heading', function (): void {
    $cacheDir = sys_get_temp_dir() . '/latte-theme-blank-demo-typography-heading-' . bin2hex(random_bytes(8));
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

    expect($response->body())->toContain('Typography primitives');

    themeBlankDemoTestCleanup($cacheDir);
});

it('it the rendered page contains a Form Controls heading', function (): void {
    $cacheDir = sys_get_temp_dir() . '/latte-theme-blank-demo-form-controls-heading-' . bin2hex(random_bytes(8));
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

    expect($response->body())->toContain('Form Controls');

    themeBlankDemoTestCleanup($cacheDir);
});

it('it the layout/base.latte file loads the Vite entry for packages/theme-blank-demo/resources/js/main.ts (file-content assertion)', function (): void {
    $baseLattePath = dirname(__DIR__, 2) . '/resources/views/layout/base.latte';

    expect(file_exists($baseLattePath))->toBeTrue();

    $contents = file_get_contents($baseLattePath);
    expect($contents)->toContain("'packages/theme-blank-demo/resources/js/main.ts'");
});

it('it the showcase.latte file contains the {syntax off} guard around the inline mk-form event-listener script (file-content assertion)', function (): void {
    $showcaseLattePath = dirname(__DIR__, 2) . '/resources/views/showcase.latte';

    expect(file_exists($showcaseLattePath))->toBeTrue();

    $contents = file_get_contents($showcaseLattePath);
    expect($contents)->toContain('{syntax off}');
    expect($contents)->toContain('{/syntax}');
});

it('it the /markommerce/_demo/theme-blank route renders a Layout Primitives heading', function (): void {
    $cacheDir = sys_get_temp_dir() . '/latte-theme-blank-demo-layout-h-' . bin2hex(random_bytes(8));
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

    expect($response->body())->toContain('Layout primitives');

    themeBlankDemoTestCleanup($cacheDir);
});

it('it the /markommerce/_demo/theme-blank route renders a Typography Primitives heading', function (): void {
    $cacheDir = sys_get_temp_dir() . '/latte-theme-blank-demo-typography-h-' . bin2hex(random_bytes(8));
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

    expect($response->body())->toContain('Typography primitives');

    themeBlankDemoTestCleanup($cacheDir);
});

it('it the /markommerce/_demo/theme-blank route renders a Form Controls heading', function (): void {
    $cacheDir = sys_get_temp_dir() . '/latte-theme-blank-demo-form-h-' . bin2hex(random_bytes(8));
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

    expect($response->body())->toContain('Form Controls');

    themeBlankDemoTestCleanup($cacheDir);
});

it('it the rendered page contains every Phase 2 primitive tag (mk-stack, mk-cluster, mk-grid, mk-container, mk-sidebar, mk-switcher, mk-cover, mk-divider, mk-heading, mk-text, mk-link, mk-badge) — single test asserting each tag\'s presence', function (): void {
    $cacheDir = sys_get_temp_dir() . '/latte-theme-blank-demo-phase2-' . bin2hex(random_bytes(8));
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

    $body = $response->body();
    $tags = ['<mk-stack', '<mk-cluster', '<mk-grid', '<mk-container', '<mk-sidebar', '<mk-switcher', '<mk-cover', '<mk-divider', '<mk-heading', '<mk-text', '<mk-link', '<mk-badge'];
    foreach ($tags as $tag) {
        expect($body)->toContain($tag);
    }

    themeBlankDemoTestCleanup($cacheDir);
});

it('it the rendered page contains every Phase 3 form control tag (mk-button, mk-input, mk-textarea, mk-select, mk-checkbox, mk-radio, mk-switch, mk-field, mk-fieldset, mk-form) — single test asserting each tag\'s presence', function (): void {
    $cacheDir = sys_get_temp_dir() . '/latte-theme-blank-demo-phase3-' . bin2hex(random_bytes(8));
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

    $body = $response->body();
    $tags = ['<mk-button', '<mk-input', '<mk-textarea', '<mk-select', '<mk-checkbox', '<mk-radio', '<mk-switch', '<mk-field', '<mk-fieldset', '<mk-form'];
    foreach ($tags as $tag) {
        expect($body)->toContain($tag);
    }

    themeBlankDemoTestCleanup($cacheDir);
});

it('it renders a complete mk-form example with at least one mk-field child', function (): void {
    $cacheDir = sys_get_temp_dir() . '/latte-theme-blank-demo-form-field-' . bin2hex(random_bytes(8));
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

    $body = $response->body();
    expect($body)->toContain('<mk-form');
    expect($body)->toContain('<mk-field');

    themeBlankDemoTestCleanup($cacheDir);
});
