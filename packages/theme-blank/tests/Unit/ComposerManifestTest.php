<?php

declare(strict_types=1);

/**
 * @return array<string, mixed>
 */
function readThemeBlankManifest(string $path): array
{
    $contents = file_get_contents($path);
    expect($contents)->not->toBeFalse();
    /** @var string $contents */

    return json_decode($contents, true);
}

it('has a composer.json declaring name markommerce/theme-blank and type marko-module', function (): void {
    $path = __DIR__ . '/../../composer.json';

    expect(file_exists($path))->toBeTrue();

    $manifest = readThemeBlankManifest($path);

    expect($manifest['name'])->toBe('markommerce/theme-blank');
    expect($manifest['type'])->toBe('marko-module');
});

it('requires marko/core, marko/view, marko/view-latte, marko/vite all at self.version', function (): void {
    $manifest = readThemeBlankManifest(__DIR__ . '/../../composer.json');

    expect($manifest['require']['marko/core'])->toBe('self.version');
    expect($manifest['require']['marko/view'])->toBe('self.version');
    expect($manifest['require']['marko/view-latte'])->toBe('self.version');
    expect($manifest['require']['marko/vite'])->toBe('self.version');
});

it('requires markommerce/frontend at self.version', function (): void {
    $manifest = readThemeBlankManifest(__DIR__ . '/../../composer.json');

    expect($manifest['require']['markommerce/frontend'])->toBe('self.version');
});

it('autoloads Markommerce\\ThemeBlank\\ from src/', function (): void {
    $manifest = readThemeBlankManifest(__DIR__ . '/../../composer.json');

    expect($manifest['autoload']['psr-4']['Markommerce\\ThemeBlank\\'])->toBe('src/');
});

it('autoloads Markommerce\\ThemeBlank\\Tests\\ from tests/', function (): void {
    $manifest = readThemeBlankManifest(__DIR__ . '/../../composer.json');

    expect($manifest['autoload-dev']['psr-4']['Markommerce\\ThemeBlank\\Tests\\'])->toBe('tests/');
});

it('sets extra.marko.module to true', function (): void {
    $manifest = readThemeBlankManifest(__DIR__ . '/../../composer.json');

    expect($manifest['extra']['marko']['module'])->toBeTrue();
});

it('has a module.php returning an empty bindings array', function (): void {
    $path = __DIR__ . '/../../module.php';

    expect(file_exists($path))->toBeTrue();

    $result = require $path;

    expect($result)->toBe([]);
});

it('has a tsconfig.json extending the root tsconfig and including resources/js', function (): void {
    $path = __DIR__ . '/../../tsconfig.json';

    expect(file_exists($path))->toBeTrue();

    $contents = file_get_contents($path);
    expect($contents)->not->toBeFalse();
    /** @var string $contents */

    $config = json_decode($contents, true);

    expect($config['extends'])->toBe('../../tsconfig.json');
    expect($config['include'])->toContain('resources/js/**/*');
});

it('has a package.json named @markommerce/theme-blank with type module', function (): void {
    $path = __DIR__ . '/../../package.json';

    expect(file_exists($path))->toBeTrue();

    $contents = file_get_contents($path);
    expect($contents)->not->toBeFalse();
    /** @var string $contents */

    $manifest = json_decode($contents, true);

    expect($manifest['name'])->toBe('@markommerce/theme-blank');
    expect($manifest['type'])->toBe('module');
});

it('declares markommerce.extension pointing at resources/js/index.ts with priority 100', function (): void {
    $manifest = json_decode(file_get_contents(__DIR__ . '/../../package.json'), true);

    expect($manifest['markommerce']['extension'])->toBe('./resources/js/index.ts');
    expect($manifest['markommerce']['priority'])->toBe(100);
});

it('declares peer dependencies on lit and open-props matching the workspace versions', function (): void {
    $manifest = json_decode(file_get_contents(__DIR__ . '/../../package.json'), true);

    expect($manifest['peerDependencies']['lit'])->toBe('^3.0');
    expect($manifest['peerDependencies']['open-props'])->toBe('^1.7');
});

it('exposes the css subpath via package.json exports for tokens.css, base.css, layouts.css', function (): void {
    $manifest = json_decode(file_get_contents(__DIR__ . '/../../package.json'), true);

    expect($manifest['exports']['.'])->toBe('./resources/js/index.ts');
    expect($manifest['exports']['./css/tokens.css'])->toBe('./resources/css/tokens.css');
    expect($manifest['exports']['./css/base.css'])->toBe('./resources/css/base.css');
    expect($manifest['exports']['./css/layouts.css'])->toBe('./resources/css/layouts.css');
});

it('has placeholder src/, tests/Unit/, tests/Feature/, tests/Browser/, resources/{css,js,views/layout}, config/ directories with .gitkeep', function (): void {
    $packageRoot = __DIR__ . '/../..';

    expect(is_dir($packageRoot . '/src'))->toBeTrue();
    expect(is_dir($packageRoot . '/tests/Unit'))->toBeTrue();
    expect(is_dir($packageRoot . '/tests/Feature'))->toBeTrue();
    expect(is_dir($packageRoot . '/tests/Browser'))->toBeTrue();
    expect(is_dir($packageRoot . '/resources/css'))->toBeTrue();
    expect(is_dir($packageRoot . '/resources/js'))->toBeTrue();
    expect(is_dir($packageRoot . '/resources/views/layout'))->toBeTrue();
    expect(is_dir($packageRoot . '/config'))->toBeTrue();

    expect(file_exists($packageRoot . '/src/.gitkeep'))->toBeTrue();
    expect(file_exists($packageRoot . '/tests/Feature/.gitkeep'))->toBeTrue();
    expect(file_exists($packageRoot . '/tests/Browser/.gitkeep'))->toBeTrue();
    expect(file_exists($packageRoot . '/resources/css/.gitkeep'))->toBeTrue();
    expect(file_exists($packageRoot . '/resources/views/layout/.gitkeep'))->toBeTrue();
    expect(file_exists($packageRoot . '/config/.gitkeep'))->toBeTrue();
});

it('makes the repo-root composer.json require markommerce/theme-blank at self.version', function (): void {
    $manifest = readThemeBlankManifest(__DIR__ . '/../../../../composer.json');

    expect($manifest['require']['markommerce/theme-blank'])->toBe('self.version');
});

it('composer validate exits 0 on the new package manifest', function (): void {
    $manifestPath = __DIR__ . '/../../composer.json';

    exec('composer validate --no-check-all ' . escapeshellarg($manifestPath) . ' 2>&1', $output, $exitCode);

    expect($exitCode)->toBe(0);
});

it('root tsconfig.json has paths entries for @markommerce/theme-blank and @markommerce/theme-blank/css/*', function (): void {
    $contents = file_get_contents(__DIR__ . '/../../../../tsconfig.json');
    expect($contents)->not->toBeFalse();
    /** @var string $contents */

    $config = json_decode($contents, true);

    expect($config['compilerOptions']['paths']['@markommerce/theme-blank'])->toBe(['packages/theme-blank/resources/js/index.ts']);
    expect($config['compilerOptions']['paths']['@markommerce/theme-blank/css/*'])->toBe(['packages/theme-blank/resources/css/*']);
});

it('root vite.config.ts has resolve.alias entries for @markommerce/theme-blank and @markommerce/theme-blank/css', function (): void {
    $path = __DIR__ . '/../../../../vite.config.ts';

    expect(file_exists($path))->toBeTrue();

    $contents = file_get_contents($path);
    expect($contents)->not->toBeFalse();
    /** @var string $contents */

    expect($contents)->toContain('@markommerce/theme-blank/css');
    expect($contents)->toContain('@markommerce/theme-blank');
    expect($contents)->toContain('packages/theme-blank/resources/css');
    expect($contents)->toContain('packages/theme-blank/resources/js/index.ts');
});
