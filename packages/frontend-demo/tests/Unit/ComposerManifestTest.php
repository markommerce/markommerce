<?php

declare(strict_types=1);

/**
 * @return array<string, mixed>
 */
function readFrontendDemoManifest(string $path): array
{
    $contents = file_get_contents($path);
    expect($contents)->not->toBeFalse();
    /** @var string $contents */

    return json_decode($contents, true);
}

it('has a composer.json declaring name markommerce/frontend-demo and type marko-module', function (): void {
    $path = __DIR__ . '/../../composer.json';

    expect(file_exists($path))->toBeTrue();

    $manifest = readFrontendDemoManifest($path);

    expect($manifest['name'])->toBe('markommerce/frontend-demo');
    expect($manifest['type'])->toBe('marko-module');
});

it(
    'requires markommerce/frontend, marko/core, marko/routing, marko/view, marko/view-latte all at self.version',
    function (): void {
        $manifest = readFrontendDemoManifest(__DIR__ . '/../../composer.json');

        expect($manifest['require']['markommerce/frontend'])->toBe('self.version');
        expect($manifest['require']['marko/core'])->toBe('self.version');
        expect($manifest['require']['marko/routing'])->toBe('self.version');
        expect($manifest['require']['marko/view'])->toBe('self.version');
        expect($manifest['require']['marko/view-latte'])->toBe('self.version');
    },
);

it('composer.json no longer lists marko/layout in require', function (): void {
    $manifest = readFrontendDemoManifest(__DIR__ . '/../../composer.json');

    expect($manifest['require'])->not->toHaveKey('marko/layout');
});

it('composer.json lists markommerce/layout in require at self.version', function (): void {
    $manifest = readFrontendDemoManifest(__DIR__ . '/../../composer.json');

    expect($manifest['require']['markommerce/layout'])->toBe('self.version');
});

it('autoloads Markommerce\\FrontendDemo\\ from src/', function (): void {
    $manifest = readFrontendDemoManifest(__DIR__ . '/../../composer.json');

    expect($manifest['autoload']['psr-4']['Markommerce\\FrontendDemo\\'])->toBe('src/');
});

it('autoloads Markommerce\\FrontendDemo\\Tests\\ from tests/', function (): void {
    $manifest = readFrontendDemoManifest(__DIR__ . '/../../composer.json');

    expect($manifest['autoload-dev']['psr-4']['Markommerce\\FrontendDemo\\Tests\\'])->toBe('tests/');
});

it('sets extra.marko.module to true', function (): void {
    $manifest = readFrontendDemoManifest(__DIR__ . '/../../composer.json');

    expect($manifest['extra']['marko']['module'])->toBeTrue();
});

it(
    'declares type marko-module so it matches the convention used by every other Marko module package',
    function (): void {
        $manifest = readFrontendDemoManifest(__DIR__ . '/../../composer.json');

        expect($manifest['type'])->toBe('marko-module');
    },
);

it(
    'requires marko/config so the route-gating middleware (task 018) can read the enabled flag from the config repository',
    function (): void {
        $manifest = readFrontendDemoManifest(__DIR__ . '/../../composer.json');

        expect($manifest['require']['marko/config'])->toBe('self.version');
    },
);

it('provides config/frontend_demo.php with the enabled key defaulting to false', function (): void {
    $path = __DIR__ . '/../../config/frontend_demo.php';

    expect(file_exists($path))->toBeTrue();

    $config = require $path;

    expect($config)->toBeArray();
    expect($config['enabled'])->toBeFalse();
});

it('the repo-root composer.json now requires markommerce/frontend-demo in require-dev', function (): void {
    $manifest = readFrontendDemoManifest(__DIR__ . '/../../../../composer.json');

    expect($manifest['require-dev']['markommerce/frontend-demo'])->toBe('self.version');
});

it('has a placeholder README.md pointing at the docs site', function (): void {
    $path = __DIR__ . '/../../README.md';

    expect(file_exists($path))->toBeTrue();

    $contents = file_get_contents($path);
    expect($contents)->not->toBeFalse();
    expect($contents)->toContain('markommerce');
});

it('composer validate exits 0 on the new package manifest', function (): void {
    $manifestPath = __DIR__ . '/../../composer.json';

    exec('composer validate --no-check-all ' . escapeshellarg($manifestPath) . ' 2>&1', $output, $exitCode);

    expect($exitCode)->toBe(0);
});
