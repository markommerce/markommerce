<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepository;
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
use Markommerce\Layout\Cache\ArtifactReaderInterface;
use Markommerce\Layout\Cache\PreparedTree;
use Markommerce\Layout\Cache\PreparedTreeBuilder;
use Markommerce\Layout\Compiler\Compiler;
use Markommerce\Layout\Compiler\ResolutionPhase;
use Markommerce\Layout\Compiler\ValidationPhase;
use Markommerce\Layout\Discovery\LayoutDiscovery;
use Markommerce\Layout\Middleware\MarkommerceLayoutMiddleware;
use Markommerce\Layout\Runtime\Renderer;
use Markommerce\LayoutDemo\Component\FeaturedBadgeComponent;
use Markommerce\LayoutDemo\Component\FeaturedCalloutComponent;
use Markommerce\LayoutDemo\Component\GalleryComponent;
use Markommerce\LayoutDemo\Component\GalleryFooterComponent;
use Markommerce\LayoutDemo\Component\GalleryHeaderComponent;
use Markommerce\LayoutDemo\Component\GalleryWrapperDecorator;
use Markommerce\LayoutDemo\Component\ItemComponent;
use Markommerce\LayoutDemo\Component\SitewideNoticeComponent;
use Markommerce\LayoutDemo\Config\LayoutDemoConfig;
use Markommerce\LayoutDemo\Context\GalleryContextProvider;
use Markommerce\LayoutDemo\Controller\LayoutDemoController;
use Markommerce\LayoutDemo\Handle\GalleryVariantHandleProvider;
use Markommerce\LayoutDemo\Middleware\EnsureLayoutDemoEnabledMiddleware;
use Markommerce\LayoutDemo\Service\DefaultLabelFormatter;
use Markommerce\LayoutDemo\Service\LabelFormatterInterface;

function handleFeatureTestCleanup(string $dir): void
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
            handleFeatureTestCleanup($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

/**
 * Build a compiled layout artifact for the layout-demo.
 *
 * @return array<string, PreparedTree>
 */
function handleFeatureTestBuildArtifact(string $layoutDemoPath): array
{
    $moduleRepository = new ModuleRepository([
        new ModuleManifest(
            name: 'markommerce/layout-demo',
            version: '1.0.0',
            path: $layoutDemoPath,
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

function handleFeatureTestEnsureManifest(string $basePath): bool
{
    $manifestDir = $basePath . '/public/build/.vite';
    $manifestPath = $manifestDir . '/manifest.json';

    $themeBlankEntry = 'packages/theme-blank/resources/js/index.ts';
    $catalogAttributeStorefrontEntry = 'packages/catalog-attribute-storefront/resources/js/index.ts';

    if (file_exists($manifestPath)) {
        $contents = file_get_contents($manifestPath);
        if ($contents !== false) {
            $manifest = json_decode($contents, true);
            if (
                is_array($manifest)
                && isset($manifest[$themeBlankEntry])
                && isset($manifest[$catalogAttributeStorefrontEntry])
            ) {
                return false;
            }
            if (is_array($manifest)) {
                if (!isset($manifest[$themeBlankEntry])) {
                    $manifest[$themeBlankEntry] = [
                        'file' => 'assets/theme-blank-abc123.js',
                        'css' => ['assets/theme-blank-abc123.css'],
                        'isEntry' => true,
                    ];
                }
                if (!isset($manifest[$catalogAttributeStorefrontEntry])) {
                    $manifest[$catalogAttributeStorefrontEntry] = [
                        'file' => 'assets/catalog-attribute-storefront-abc123.js',
                        'isEntry' => true,
                    ];
                }
                file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT));

                return true;
            }
        }
    }

    @mkdir($manifestDir, 0755, true);
    file_put_contents($manifestPath, json_encode([
        $themeBlankEntry => [
            'file' => 'assets/theme-blank-abc123.js',
            'css' => ['assets/theme-blank-abc123.css'],
            'isEntry' => true,
        ],
        $catalogAttributeStorefrontEntry => [
            'file' => 'assets/catalog-attribute-storefront-abc123.js',
            'isEntry' => true,
        ],
    ]));

    return true;
}

function handleFeatureTestBuildRouter(
    ConfigRepositoryInterface $config,
    string $frontendPath,
    string $layoutDemoPath,
    string $themeBlankPath,
    string $basePath,
    array $extraComponents = [],
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
            name: 'markommerce/layout-demo',
            version: '1.0.0',
            path: $layoutDemoPath,
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

    $layoutDemoConfig = new LayoutDemoConfig($config);
    $ensureMiddleware = new EnsureLayoutDemoEnabledMiddleware($layoutDemoConfig);

    $routes = new RouteCollection();
    $discovery = new RouteDiscovery();
    $controllerRoutes = $discovery->discoverFromClass(LayoutDemoController::class);
    foreach ($controllerRoutes as $route) {
        $routes->add($route);
    }

    $matcher = new RouteMatcher($routes);

    $labelFormatter = new DefaultLabelFormatter();
    $galleryContextProvider = new GalleryContextProvider();
    $galleryHeaderComponent = new GalleryHeaderComponent();
    $galleryComponent = new GalleryComponent();
    $galleryFooterComponent = new GalleryFooterComponent();
    $itemComponent = new ItemComponent($labelFormatter);
    $featuredBadgeComponent = new FeaturedBadgeComponent();
    $galleryWrapperDecorator = new GalleryWrapperDecorator();
    $sitewideNoticeComponent = new SitewideNoticeComponent();
    $featuredCalloutComponent = new FeaturedCalloutComponent();
    $galleryVariantHandleProvider = new GalleryVariantHandleProvider();

    $container = new Container();
    $container->instance(ConfigRepositoryInterface::class, $config);
    $container->instance(ViewInterface::class, $view);
    $container->instance(RouteMatcherInterface::class, $matcher);
    $container->instance(TemplateResolverInterface::class, $templateResolver);
    $container->instance(LayoutDemoConfig::class, $layoutDemoConfig);
    $container->instance(EnsureLayoutDemoEnabledMiddleware::class, $ensureMiddleware);
    $container->instance(LayoutDemoController::class, new LayoutDemoController());
    $container->instance(GalleryContextProvider::class, $galleryContextProvider);
    $container->instance(GalleryHeaderComponent::class, $galleryHeaderComponent);
    $container->instance(GalleryComponent::class, $galleryComponent);
    $container->instance(GalleryFooterComponent::class, $galleryFooterComponent);
    $container->instance(ItemComponent::class, $itemComponent);
    $container->instance(FeaturedBadgeComponent::class, $featuredBadgeComponent);
    $container->instance(GalleryWrapperDecorator::class, $galleryWrapperDecorator);
    $container->instance(LabelFormatterInterface::class, $labelFormatter);
    $container->instance(SitewideNoticeComponent::class, $sitewideNoticeComponent);
    $container->instance(FeaturedCalloutComponent::class, $featuredCalloutComponent);
    $container->instance(GalleryVariantHandleProvider::class, $galleryVariantHandleProvider);

    foreach ($extraComponents as $class => $instance) {
        $container->instance($class, $instance);
    }

    $trees = handleFeatureTestBuildArtifact($layoutDemoPath);

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

function handleFeatureTestMakeConfig(string $cacheDir): ConfigRepository
{
    return new ConfigRepository([
        'layout_demo' => ['enabled' => true],
        'vite' => [
            'entry' => 'packages/layout-demo/resources/js/main.ts',
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
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('it renders the dynamic featured-callout when variant=featured is on the query string', function (): void {
    $cacheDir = sys_get_temp_dir() . '/latte-handle-variant-' . bin2hex(random_bytes(8));
    mkdir($cacheDir, 0755, true);

    $layoutDemoPath = dirname(__DIR__, 2);
    $frontendPath = $layoutDemoPath . '/../frontend';
    $themeBlankPath = $layoutDemoPath . '/../theme-blank';
    $basePath = dirname(__DIR__, 4);

    $manifestCreated = handleFeatureTestEnsureManifest($basePath);

    $config = handleFeatureTestMakeConfig($cacheDir);
    $router = handleFeatureTestBuildRouter($config, $frontendPath, $layoutDemoPath, $themeBlankPath, $basePath);
    $request = new Request(
        server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/markommerce/_demo/layout/1'],
        query: ['variant' => 'featured'],
    );
    $response = $router->handle($request);

    expect($response->statusCode())->toBe(200);
    expect($response->body())->toContain('layout-demo-featured-callout');

    handleFeatureTestCleanup($cacheDir);
    if ($manifestCreated) {
        $manifestPath = $basePath . '/public/build/.vite/manifest.json';
        $manifest = json_decode(file_get_contents($manifestPath) ?: '{}', true);
        unset($manifest['packages/theme-blank/resources/js/index.ts']);
        if (empty($manifest)) {
            @unlink($manifestPath);
        } else {
            file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT));
        }
    }
});

it('it does not render the featured-callout when variant is absent', function (): void {
    $cacheDir = sys_get_temp_dir() . '/latte-handle-no-variant-' . bin2hex(random_bytes(8));
    mkdir($cacheDir, 0755, true);

    $layoutDemoPath = dirname(__DIR__, 2);
    $frontendPath = $layoutDemoPath . '/../frontend';
    $themeBlankPath = $layoutDemoPath . '/../theme-blank';
    $basePath = dirname(__DIR__, 4);

    $manifestCreated = handleFeatureTestEnsureManifest($basePath);

    $config = handleFeatureTestMakeConfig($cacheDir);
    $router = handleFeatureTestBuildRouter($config, $frontendPath, $layoutDemoPath, $themeBlankPath, $basePath);
    $request = new Request(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/markommerce/_demo/layout/1']);
    $response = $router->handle($request);

    expect($response->statusCode())->toBe(200);
    expect($response->body())->not->toContain('layout-demo-featured-callout');

    handleFeatureTestCleanup($cacheDir);
    if ($manifestCreated) {
        $manifestPath = $basePath . '/public/build/.vite/manifest.json';
        $manifest = json_decode(file_get_contents($manifestPath) ?: '{}', true);
        unset($manifest['packages/theme-blank/resources/js/index.ts']);
        if (empty($manifest)) {
            @unlink($manifestPath);
        } else {
            file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT));
        }
    }
});
