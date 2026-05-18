<?php

declare(strict_types=1);

/**
 * @return array<string, mixed>
 */
function readThemeBlankDemoManifest(string $path): array
{
    $contents = file_get_contents($path);
    expect($contents)->not->toBeFalse();
    /** @var string $contents */

    return json_decode($contents, true);
}

it('has a composer.json declaring name markommerce/theme-blank-demo and type marko-module', function (): void {
    $path = __DIR__ . '/../../composer.json';

    expect(file_exists($path))->toBeTrue();

    $manifest = readThemeBlankDemoManifest($path);

    expect($manifest['name'])->toBe('markommerce/theme-blank-demo');
    expect($manifest['type'])->toBe('marko-module');
});

it(
    'requires markommerce/frontend, markommerce/theme-blank, marko/core, marko/routing, marko/view, marko/view-latte, marko/layout, marko/config all at self.version',
    function (): void {
        $manifest = readThemeBlankDemoManifest(__DIR__ . '/../../composer.json');

        expect($manifest['require']['markommerce/frontend'])->toBe('self.version');
        expect($manifest['require']['markommerce/theme-blank'])->toBe('self.version');
        expect($manifest['require']['marko/core'])->toBe('self.version');
        expect($manifest['require']['marko/routing'])->toBe('self.version');
        expect($manifest['require']['marko/view'])->toBe('self.version');
        expect($manifest['require']['marko/view-latte'])->toBe('self.version');
        expect($manifest['require']['marko/layout'])->toBe('self.version');
        expect($manifest['require']['marko/config'])->toBe('self.version');
    },
);

it('autoloads Markommerce\\ThemeBlankDemo\\ from src/', function (): void {
    $manifest = readThemeBlankDemoManifest(__DIR__ . '/../../composer.json');

    expect($manifest['autoload']['psr-4']['Markommerce\\ThemeBlankDemo\\'])->toBe('src/');
});

it('autoloads Markommerce\\ThemeBlankDemo\\Tests\\ from tests/', function (): void {
    $manifest = readThemeBlankDemoManifest(__DIR__ . '/../../composer.json');

    expect($manifest['autoload-dev']['psr-4']['Markommerce\\ThemeBlankDemo\\Tests\\'])->toBe('tests/');
});

it('sets extra.marko.module to true', function (): void {
    $manifest = readThemeBlankDemoManifest(__DIR__ . '/../../composer.json');

    expect($manifest['extra']['marko']['module'])->toBeTrue();
});

it('provides config/theme_blank_demo.php with the enabled key defaulting to false', function (): void {
    $path = __DIR__ . '/../../config/theme_blank_demo.php';

    expect(file_exists($path))->toBeTrue();

    $config = require $path;

    expect($config)->toBeArray();
    expect($config['enabled'])->toBeFalse();
});

it('the repo-root composer.json registers markommerce/theme-blank-demo under require-dev at self.version', function (): void {
    $manifest = readThemeBlankDemoManifest(__DIR__ . '/../../../../composer.json');

    expect($manifest['require-dev']['markommerce/theme-blank-demo'])->toBe('self.version');
});

it('has a placeholder README.md mentioning markommerce', function (): void {
    $path = __DIR__ . '/../../README.md';

    expect(file_exists($path))->toBeTrue();

    $contents = file_get_contents($path);
    expect($contents)->not->toBeFalse();
    expect($contents)->toContain('markommerce');
});

it('the package.json declares markommerce.extension pointing at ./resources/js/index.ts and markommerce.priority set to 1010', function (): void {
    $path = __DIR__ . '/../../package.json';

    expect(file_exists($path))->toBeTrue();

    $contents = file_get_contents($path);
    expect($contents)->not->toBeFalse();
    /** @var string $contents */

    $manifest = json_decode($contents, true);

    expect($manifest['markommerce']['extension'])->toBe('./resources/js/index.ts');
    expect($manifest['markommerce']['priority'])->toBe(1010);
});
