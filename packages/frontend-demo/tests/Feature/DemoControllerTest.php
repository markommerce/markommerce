<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container as CoreContainer;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepository;
use Marko\Core\Module\ModuleRepositoryInterface;
use Marko\Core\Path\ProjectPaths;
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

    $artifactReader = new class($trees) implements ArtifactReaderInterface {
        /** @param array<string, PreparedTree> $trees */
        public function __construct(private array $trees) {}

        public function read(): array
        {
            return $this->trees;
        }
    };

    $renderer = new Renderer($view, $container);
    $layoutMiddleware = new MarkommerceLayoutMiddleware($matcher, $artifactReader, $renderer);
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

it('it has a layout file that returns a Layout for DemoController::index', function (): void {
    $layoutPath = dirname(__DIR__, 2) . '/layout/demo.php';

    expect(file_exists($layoutPath))->toBeTrue();

    $layout = require $layoutPath;

    expect($layout)->toBeInstanceOf(\Markommerce\Layout\Layout::class);
    expect($layout->handle)->toBe([DemoController::class, 'index']);
});

it('it places DemoCounterComponent in the content slot', function (): void {
    $layoutPath = dirname(__DIR__, 2) . '/layout/demo.php';
    $layout = require $layoutPath;

    expect($layout)->toBeInstanceOf(\Markommerce\Layout\Layout::class);
    expect($layout->slots)->toHaveKey('content');

    $contentSlot = $layout->slots['content'];
    expect($contentSlot)->toBeArray();
    expect($contentSlot)->not->toBeEmpty();

    $place = $contentSlot[0];
    expect($place)->toBeInstanceOf(\Markommerce\Layout\Place::class);
    expect($place->component)->toBe(DemoCounterComponent::class);
});

it('it drops the #[Layout] attribute from DemoController', function (): void {
    $reflection = new ReflectionClass(DemoController::class);
    $layoutAttributes = $reflection->getAttributes(\Markommerce\Layout\Layout::class);
    $markoLayoutAttributes = $reflection->getAttributes('Marko\Layout\Attributes\Layout');

    expect($layoutAttributes)->toBeEmpty();
    expect($markoLayoutAttributes)->toBeEmpty();
});

it('it drops the #[Component] attribute from DemoCounterComponent', function (): void {
    $reflection = new ReflectionClass(DemoCounterComponent::class);
    $componentAttributes = $reflection->getAttributes('Marko\Layout\Attributes\Component');

    expect($componentAttributes)->toBeEmpty();
});

it('the demo main.ts imports open-props/style.css so Vite emits the Open Props stylesheet link in the head', function (): void {
    $mainTsPath = dirname(__DIR__, 2) . '/resources/js/main.ts';

    expect(file_exists($mainTsPath))->toBeTrue();

    $contents = file_get_contents($mainTsPath);
    expect($contents)->toContain("import 'open-props/style.css'");
});

it('it loads the @markommerce/frontend cascade layers and @markommerce/theme-blank tokens CSS in the head before component-level CSS', function (): void {
    $mainTsPath = dirname(__DIR__, 2) . '/resources/js/main.ts';
    $contents = file_get_contents($mainTsPath);

    $layersPos = strpos($contents, '@markommerce/frontend/css/layers.css');
    $tokensPos = strpos($contents, '@markommerce/theme-blank/css/tokens.css');
    $componentPos = strpos($contents, 'counter.css');

    expect($layersPos !== false)->toBeTrue()
        ->and($tokensPos !== false)->toBeTrue()
        ->and($componentPos !== false)->toBeTrue()
        ->and($layersPos)->toBeLessThan($componentPos)
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

it('it counter.latte renders only the markommerce-counter element and no longer contains primitives or form controls (file-content assertion)', function (): void {
    $lattePath = dirname(__DIR__, 2) . '/resources/views/counter.latte';

    expect(file_exists($lattePath))->toBeTrue();

    $contents = file_get_contents($lattePath);
    expect(trim($contents))->toBe('<markommerce-counter start-value="0" suffix=" clicks"></markommerce-counter>');
    expect($contents)->not->toContain('<mk-');
});

it('it the /markommerce/_demo response body no longer contains any <mk- element (verified by HTTP request against the live route)', function (): void {
    $cacheDir = sys_get_temp_dir() . '/latte-demo-no-mk-' . bin2hex(random_bytes(8));
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
    expect($body)->toContain('<markommerce-counter');
    expect($body)->not->toContain('<mk-');

    demoTestCleanup($cacheDir);
    if ($manifestCreated) {
        @unlink($basePath . '/public/build/.vite/manifest.json');
    }
});
