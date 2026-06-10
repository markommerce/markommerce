<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepository;
use Marko\Core\Path\ProjectPaths;
use Marko\View\Latte\LatteView;
use Marko\View\Latte\LatteViewConfig;
use Marko\View\ModuleTemplateResolver;
use Marko\View\ViewConfig;
use Marko\Vite\Vite;
use Markommerce\Frontend\View\Latte\MarkommerceLatteEngineFactory;
use Markommerce\Frontend\View\Latte\ViteExtension;

/**
 * @return array<string, mixed>
 */
function themeBlankTestBuildConfig(string $cacheDir): ConfigRepository
{
    return new ConfigRepository([
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
}

/**
 * Ensures the Vite manifest contains the theme-blank entry.
 * Returns the original manifest contents if it was modified, or null if unchanged.
 *
 * @return string|null Original manifest content if modified, null if already had the entry.
 */
function themeBlankTestEnsureManifest(string $basePath): ?string
{
    $manifestDir = $basePath . '/public/build/.vite';
    $manifestPath = $manifestDir . '/manifest.json';

    $themeBlankEntry = 'packages/theme-blank/resources/js/index.ts';

    if (file_exists($manifestPath)) {
        $original = file_get_contents($manifestPath);
        $manifest = json_decode($original, true);

        if (isset($manifest[$themeBlankEntry])) {
            return null;
        }

        $manifest[$themeBlankEntry] = [
            'file' => 'assets/theme-blank-abc123.js',
            'css' => ['assets/theme-blank-abc123.css'],
            'isEntry' => true,
        ];
        file_put_contents($manifestPath, json_encode($manifest));

        return $original;
    }

    @mkdir($manifestDir, 0755, true);
    file_put_contents($manifestPath, json_encode([
        $themeBlankEntry => [
            'file' => 'assets/theme-blank-abc123.js',
            'css' => ['assets/theme-blank-abc123.css'],
            'isEntry' => true,
        ],
    ]));

    return '';
}

function themeBlankTestBuildView(ConfigRepository $config, string $themeBlankPath, string $basePath): LatteView
{
    $moduleRepository = new ModuleRepository([
        new ModuleManifest(
            name: 'markommerce/theme-blank',
            version: '1.0.0',
            path: $themeBlankPath,
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

    return new LatteView($engine, $templateResolver);
}

function themeBlankTestCleanup(string $dir): void
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
            themeBlankTestCleanup($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

it('it ships base.latte at resources/views/layout/base.latte', function (): void {
    $templatePath = dirname(__DIR__, 2) . '/resources/views/layout/base.latte';
    expect(file_exists($templatePath))->toBeTrue();
});

it('base.latte declares <!doctype html> and <html lang attribute>', function (): void {
    $templatePath = dirname(__DIR__, 2) . '/resources/views/layout/base.latte';
    $contents = file_get_contents($templatePath);

    expect($contents)->toContain('<!doctype html>');
    expect($contents)->toMatch('/<html[^>]+lang=/');
});

it('base.latte renders a vite() call pointing at packages/theme-blank/resources/js/index.ts', function (): void {
    $templatePath = dirname(__DIR__, 2) . '/resources/views/layout/base.latte';
    $contents = file_get_contents($templatePath);

    expect($contents)->toContain('packages/theme-blank/resources/js/index.ts');
    expect($contents)->toMatch('/\{vite\(/');
});

it('it injects content slot data into the 1column layout via marko/view-latte SlotExtension', function (): void {
    $cacheDir = sys_get_temp_dir() . '/latte-theme-blank-slot-' . bin2hex(random_bytes(8));
    mkdir($cacheDir, 0755, true);

    $themeBlankPath = dirname(__DIR__, 2);
    $basePath = dirname(__DIR__, 4);

    $originalManifest = themeBlankTestEnsureManifest($basePath);

    $config = themeBlankTestBuildConfig($cacheDir);
    $view = themeBlankTestBuildView($config, $themeBlankPath, $basePath);

    $result = $view->renderToString('theme-blank::layout/1column', [
        'slots' => ['content' => '<span>hello-content</span>'],
    ]);

    expect($result)->toContain('<span>hello-content</span>');
    expect($result)->toContain('<mk-container>');

    themeBlankTestCleanup($cacheDir);
    if ($originalManifest !== null) {
        $manifestPath = $basePath . '/public/build/.vite/manifest.json';
        if ($originalManifest === '') {
            @unlink($manifestPath);
        } else {
            file_put_contents($manifestPath, $originalManifest);
        }
    }
});

it('it injects sidebar-left and sidebar-right slot data into the 3columns layout', function (): void {
    $cacheDir = sys_get_temp_dir() . '/latte-theme-blank-3col-' . bin2hex(random_bytes(8));
    mkdir($cacheDir, 0755, true);

    $themeBlankPath = dirname(__DIR__, 2);
    $basePath = dirname(__DIR__, 4);

    $originalManifest = themeBlankTestEnsureManifest($basePath);

    $config = themeBlankTestBuildConfig($cacheDir);
    $view = themeBlankTestBuildView($config, $themeBlankPath, $basePath);

    $result = $view->renderToString('theme-blank::layout/3columns', [
        'slots' => [
            'content' => '<span>main-content</span>',
            'sidebar-left' => '<nav>left-nav</nav>',
            'sidebar-right' => '<nav>right-nav</nav>',
        ],
    ]);

    expect($result)->toContain('<span>main-content</span>');
    expect($result)->toContain('<nav>left-nav</nav>');
    expect($result)->toContain('<nav>right-nav</nav>');
    expect($result)->toContain('mk-layout-3col');

    themeBlankTestCleanup($cacheDir);
    if ($originalManifest !== null) {
        $manifestPath = $basePath . '/public/build/.vite/manifest.json';
        if ($originalManifest === '') {
            @unlink($manifestPath);
        } else {
            file_put_contents($manifestPath, $originalManifest);
        }
    }
});

it('each layout template compiles successfully through the Latte engine', function (): void {
    $cacheDir = sys_get_temp_dir() . '/latte-theme-blank-test-' . bin2hex(random_bytes(8));
    mkdir($cacheDir, 0755, true);

    $themeBlankPath = dirname(__DIR__, 2);
    $basePath = dirname(__DIR__, 4);

    $originalManifest = themeBlankTestEnsureManifest($basePath);

    $config = themeBlankTestBuildConfig($cacheDir);
    $view = themeBlankTestBuildView($config, $themeBlankPath, $basePath);

    $templates = [
        'theme-blank::layout/base',
        'theme-blank::layout/empty',
        'theme-blank::layout/1column',
        'theme-blank::layout/2columns-left',
        'theme-blank::layout/2columns-right',
        'theme-blank::layout/3columns',
    ];

    foreach ($templates as $template) {
        $result = $view->renderToString($template);
        expect($result)->not->toBeEmpty();
    }

    themeBlankTestCleanup($cacheDir);
    if ($originalManifest !== null) {
        $manifestPath = $basePath . '/public/build/.vite/manifest.json';
        if ($originalManifest === '') {
            @unlink($manifestPath);
        } else {
            file_put_contents($manifestPath, $originalManifest);
        }
    }
});

it(
    'the {vite()} call in base.latte renders successfully when the engine has the ViteExtension registered',
    function (): void {
        $cacheDir = sys_get_temp_dir() . '/latte-theme-blank-vite-' . bin2hex(random_bytes(8));
        mkdir($cacheDir, 0755, true);

        $themeBlankPath = dirname(__DIR__, 2);
        $basePath = dirname(__DIR__, 4);

        $originalManifest = themeBlankTestEnsureManifest($basePath);

        $config = themeBlankTestBuildConfig($cacheDir);
        $view = themeBlankTestBuildView($config, $themeBlankPath, $basePath);

        $result = $view->renderToString('theme-blank::layout/base');

        expect($result)->not->toBeEmpty();
        expect($result)->toMatch('/<(script|link)/');

        themeBlankTestCleanup($cacheDir);
        if ($originalManifest !== null) {
            $manifestPath = $basePath . '/public/build/.vite/manifest.json';
            if ($originalManifest === '') {
                @unlink($manifestPath);
            } else {
                file_put_contents($manifestPath, $originalManifest);
            }
        }
    },
);
