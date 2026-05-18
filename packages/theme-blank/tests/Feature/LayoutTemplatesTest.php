<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepository;
use Marko\Core\Path\ProjectPaths;
use Marko\View\Latte\LatteView;
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
    $templateResolver = new ModuleTemplateResolver($moduleRepository, $viewConfig);
    $paths = new ProjectPaths($basePath);
    $vite = new Vite($config, $paths);
    $viteExtension = new ViteExtension($vite, $config);
    $engineFactory = new MarkommerceLatteEngineFactory($viewConfig, $viteExtension);
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

it('base.latte exposes title, head-extra, body, header, main, footer blocks', function (): void {
    $templatePath = dirname(__DIR__, 2) . '/resources/views/layout/base.latte';
    $contents = file_get_contents($templatePath);

    expect($contents)->toContain('{block title}');
    expect($contents)->toContain('{block head-extra}');
    expect($contents)->toContain('{block body}');
    expect($contents)->toContain('{block header}');
    expect($contents)->toContain('{block main}');
    expect($contents)->toContain('{block footer}');
});

it('empty.latte extends base.latte and exposes only a content block via body', function (): void {
    $templatePath = dirname(__DIR__, 2) . '/resources/views/layout/empty.latte';

    expect(file_exists($templatePath))->toBeTrue();

    $contents = file_get_contents($templatePath);
    expect($contents)->toContain("layout 'theme-blank::layout/base'");
    expect($contents)->toContain('{block body}');
    expect($contents)->toContain('{block content}');
});

it('1column.latte extends base.latte, wraps main in mk-container, exposes a content block', function (): void {
    $templatePath = dirname(__DIR__, 2) . '/resources/views/layout/1column.latte';

    expect(file_exists($templatePath))->toBeTrue();

    $contents = file_get_contents($templatePath);
    expect($contents)->toContain("layout 'theme-blank::layout/base'");
    expect($contents)->toContain('{block main}');
    expect($contents)->toContain('<mk-container>');
    expect($contents)->toContain('{block content}');
});

it('2columns-left.latte extends base.latte, wraps main in mk-container and mk-sidebar, exposes sidebar-left and content blocks', function (): void {
    $templatePath = dirname(__DIR__, 2) . '/resources/views/layout/2columns-left.latte';

    expect(file_exists($templatePath))->toBeTrue();

    $contents = file_get_contents($templatePath);
    expect($contents)->toContain("layout 'theme-blank::layout/base'");
    expect($contents)->toContain('{block main}');
    expect($contents)->toContain('<mk-container>');
    expect($contents)->toContain('<mk-sidebar>');
    expect($contents)->toContain('<aside>');
    expect($contents)->toContain('{block sidebar-left}');
    expect($contents)->toContain('{block content}');
});

it('2columns-right.latte extends base.latte, wraps main in mk-container and mk-sidebar with side=right, exposes sidebar-right and content blocks', function (): void {
    $templatePath = dirname(__DIR__, 2) . '/resources/views/layout/2columns-right.latte';

    expect(file_exists($templatePath))->toBeTrue();

    $contents = file_get_contents($templatePath);
    expect($contents)->toContain("layout 'theme-blank::layout/base'");
    expect($contents)->toContain('{block main}');
    expect($contents)->toContain('<mk-container>');
    expect($contents)->toContain('mk-sidebar side="right"');
    expect($contents)->toContain('<aside>');
    expect($contents)->toContain('{block sidebar-right}');
    expect($contents)->toContain('{block content}');
});

it('3columns.latte extends base.latte, wraps main in .mk-layout-3col, exposes sidebar-left, sidebar-right, and content blocks', function (): void {
    $templatePath = dirname(__DIR__, 2) . '/resources/views/layout/3columns.latte';

    expect(file_exists($templatePath))->toBeTrue();

    $contents = file_get_contents($templatePath);
    expect($contents)->toContain("layout 'theme-blank::layout/base'");
    expect($contents)->toContain('{block main}');
    expect($contents)->toContain('mk-layout-3col');
    expect($contents)->toContain('{block sidebar-left}');
    expect($contents)->toContain('{block sidebar-right}');
    expect($contents)->toContain('{block content}');
});

it('layouts.css wraps all rules in @layer theme', function (): void {
    $cssPath = dirname(__DIR__, 2) . '/resources/css/layouts.css';

    expect(file_exists($cssPath))->toBeTrue();

    $contents = file_get_contents($cssPath);
    expect($contents)->toMatch('/@layer theme\s*\{/');
});

it('layouts.css does not define .mk-layout-1col or .mk-layout-2col-* (replaced by mk-container and mk-sidebar primitives)', function (): void {
    $cssPath = dirname(__DIR__, 2) . '/resources/css/layouts.css';
    $contents = file_get_contents($cssPath);

    expect($contents)->not->toContain('.mk-layout-1col');
    expect($contents)->not->toContain('.mk-layout-2col-left');
    expect($contents)->not->toContain('.mk-layout-2col-right');
});

it('layouts.css defines responsive grid columns for .mk-layout-3col using --mk-breakpoint-lg', function (): void {
    $cssPath = dirname(__DIR__, 2) . '/resources/css/layouts.css';
    $contents = file_get_contents($cssPath);

    expect($contents)->toContain('.mk-layout-3col');
    expect($contents)->toContain('--mk-breakpoint-lg');
    expect($contents)->toContain('240px 1fr 240px');
});

it('it is exported from theme-blank package.json so import \'@markommerce/theme-blank/css/layouts.css\' resolves', function (): void {
    $packageJsonPath = dirname(__DIR__, 2) . '/package.json';
    $manifest = json_decode(file_get_contents($packageJsonPath), true);

    expect($manifest['exports']['./css/layouts.css'])->toBe('./resources/css/layouts.css');
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

it('the {vite()} call in base.latte renders successfully when the engine has the ViteExtension registered', function (): void {
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
});
