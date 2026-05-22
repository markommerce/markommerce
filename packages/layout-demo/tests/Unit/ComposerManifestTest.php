<?php

declare(strict_types=1);

/**
 * @return array<string, mixed>
 */
function readLayoutDemoManifest(string $path): array
{
    $contents = file_get_contents($path);
    expect($contents)->not->toBeFalse();
    /** @var string $contents */

    $decoded = json_decode($contents, true);
    expect($decoded)->toBeArray();

    return $decoded;
}

it('has a composer.json with name markommerce/layout-demo', function (): void {
    $path = __DIR__ . '/../../composer.json';

    expect(file_exists($path))->toBeTrue();

    $manifest = readLayoutDemoManifest($path);

    expect($manifest['name'])->toBe('markommerce/layout-demo');
});

it(
    'it composer.json requires marko/config, marko/core, marko/routing, marko/view, marko/view-latte, markommerce/layout, markommerce/theme-blank at self.version',
    function (): void {
        $manifest = readLayoutDemoManifest(__DIR__ . '/../../composer.json');

        expect($manifest['require']['marko/config'])->toBe('self.version');
        expect($manifest['require']['marko/core'])->toBe('self.version');
        expect($manifest['require']['marko/routing'])->toBe('self.version');
        expect($manifest['require']['marko/view'])->toBe('self.version');
        expect($manifest['require']['marko/view-latte'])->toBe('self.version');
        expect($manifest['require']['markommerce/layout'])->toBe('self.version');
        expect($manifest['require']['markommerce/theme-blank'])->toBe('self.version');
    },
);

it('it has a config/layout_demo.php file with enabled defaulting to false', function (): void {
    $path = __DIR__ . '/../../config/layout_demo.php';

    expect(file_exists($path))->toBeTrue();

    $config = require $path;

    expect($config)->toBeArray();
    expect($config['enabled'])->toBeFalse();
});

it('it the repo-root composer.json requires markommerce/layout-demo in require-dev at self.version', function (): void {
    $manifest = readLayoutDemoManifest(__DIR__ . '/../../../../composer.json');

    expect($manifest['require-dev']['markommerce/layout-demo'])->toBe('self.version');
});
