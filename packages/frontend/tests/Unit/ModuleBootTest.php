<?php

declare(strict_types=1);

use Latte\Engine;
use Marko\Config\ConfigRepository;
use Marko\Core\Attributes\Preference;
use Marko\Core\Path\ProjectPaths;
use Marko\View\Latte\Extensions\SlotExtension;
use Marko\View\Latte\LatteEngineFactory;
use Marko\View\Latte\LatteViewConfig;
use Marko\View\ViewConfig;
use Marko\Vite\Vite;
use Markommerce\Frontend\View\Latte\MarkommerceLatteEngineFactory;
use Markommerce\Frontend\View\Latte\ViteExtension;

it('provides default config at config/vite.php setting useDevServer to false', function (): void {
    $configPath = __DIR__ . '/../../config/vite.php';

    expect(file_exists($configPath))->toBeTrue();

    $config = require $configPath;

    expect($config['useDevServer'])->toBeFalse();
});

it('provides a default entry pointing at the frontend-demo main.ts', function (): void {
    $configPath = __DIR__ . '/../../config/vite.php';

    $config = require $configPath;

    expect($config['entry'])->toBe('packages/frontend-demo/resources/js/main.ts');
});

it('the MarkommerceLatteEngineFactory has the #[Preference(LatteEngineFactory::class)] attribute', function (): void {
    $reflection = new ReflectionClass(MarkommerceLatteEngineFactory::class);
    $attributes = $reflection->getAttributes(Preference::class);

    expect($attributes)->toHaveCount(1);

    $preferenceAttribute = $attributes[0]->newInstance();

    expect($preferenceAttribute->replaces)->toBe(LatteEngineFactory::class);
});

it(
    'the MarkommerceLatteEngineFactory::create() returns an Engine with both SlotExtension and ViteExtension registered',
    function (): void {
        $cacheDir = sys_get_temp_dir() . '/latte-markommerce-test-' . bin2hex(random_bytes(8));
        mkdir($cacheDir, 0755, true);

        $config = new ConfigRepository([
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
                'auto_refresh' => true,
                'strict_types' => false,
            ],
        ]);

        $viewConfig = new ViewConfig($config);
        $latteViewConfig = new LatteViewConfig($config);
        $paths = new ProjectPaths(sys_get_temp_dir());
        $vite = new Vite($config, $paths);
        $viteExtension = new ViteExtension($vite, $config);
        $factory = new MarkommerceLatteEngineFactory($viewConfig, $latteViewConfig, $viteExtension);

        $engine = $factory->create();

        expect($engine)->toBeInstanceOf(Engine::class);

        $reflection = new ReflectionClass($engine);
        $extensionsProperty = $reflection->getProperty('extensions');
        $extensions = $extensionsProperty->getValue($engine);

        $extensionClasses = array_map(fn ($ext) => $ext::class, $extensions);

        expect($extensionClasses)->toContain(SlotExtension::class);
        expect($extensionClasses)->toContain(ViteExtension::class);

        array_map('unlink', glob($cacheDir . '/*') ?: []);
        rmdir($cacheDir);
    },
);

it('the Latte engine reports the vite function as registered after the module boots', function (): void {
    $cacheDir = sys_get_temp_dir() . '/latte-markommerce-vite-test-' . bin2hex(random_bytes(8));
    mkdir($cacheDir, 0755, true);

    $config = new ConfigRepository([
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
            'auto_refresh' => true,
            'strict_types' => false,
        ],
    ]);

    $viewConfig = new ViewConfig($config);
    $latteViewConfig = new LatteViewConfig($config);
    $paths = new ProjectPaths(sys_get_temp_dir());
    $vite = new Vite($config, $paths);
    $viteExtension = new ViteExtension($vite, $config);
    $factory = new MarkommerceLatteEngineFactory($viewConfig, $latteViewConfig, $viteExtension);

    $engine = $factory->create();

    $functions = [];
    $reflection = new ReflectionClass($engine);
    $extensionsProperty = $reflection->getProperty('extensions');
    $extensions = $extensionsProperty->getValue($engine);

    foreach ($extensions as $extension) {
        foreach ($extension->getFunctions() as $name => $callable) {
            $functions[$name] = $callable;
        }
    }

    expect($functions)->toHaveKey('vite');

    array_map('unlink', glob($cacheDir . '/*') ?: []);
    rmdir($cacheDir);
});
