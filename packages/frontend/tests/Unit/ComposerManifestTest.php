<?php

declare(strict_types=1);

/**
 * @return array<string, mixed>
 */
function readManifest(string $path): array
{
    $contents = file_get_contents($path);
    expect($contents)->not->toBeFalse();
    /** @var string $contents */

    return json_decode($contents, true);
}

it('has a composer.json declaring name markommerce/frontend and type marko-module', function (): void {
    $path = __DIR__ . '/../../composer.json';

    expect(file_exists($path))->toBeTrue();

    $manifest = readManifest($path);

    expect($manifest['name'])->toBe('markommerce/frontend');
    expect($manifest['type'])->toBe('marko-module');
});

it('requires marko/core, marko/vite, marko/view, marko/view-latte all at self.version', function (): void {
    $manifest = readManifest(__DIR__ . '/../../composer.json');

    expect($manifest['require']['marko/core'])->toBe('self.version');
    expect($manifest['require']['marko/vite'])->toBe('self.version');
    expect($manifest['require']['marko/view'])->toBe('self.version');
    expect($manifest['require']['marko/view-latte'])->toBe('self.version');
});

it('autoloads Markommerce\\Frontend\\ from src/', function (): void {
    $manifest = readManifest(__DIR__ . '/../../composer.json');

    expect($manifest['autoload']['psr-4']['Markommerce\\Frontend\\'])->toBe('src/');
});

it('autoloads Markommerce\\Frontend\\Tests\\ from tests/', function (): void {
    $manifest = readManifest(__DIR__ . '/../../composer.json');

    expect($manifest['autoload-dev']['psr-4']['Markommerce\\Frontend\\Tests\\'])->toBe('tests/');
});

it('sets extra.marko.module to true', function (): void {
    $manifest = readManifest(__DIR__ . '/../../composer.json');

    expect($manifest['extra']['marko']['module'])->toBeTrue();
});

it('has an empty module.php returning []', function (): void {
    $path = __DIR__ . '/../../module.php';

    expect(file_exists($path))->toBeTrue();

    $result = require $path;

    expect($result)->toBe([]);
});

it('has placeholder src/ and tests/Unit/ directories', function (): void {
    $packageRoot = __DIR__ . '/../..';

    expect(is_dir($packageRoot . '/src'))->toBeTrue();
    expect(is_dir($packageRoot . '/tests/Unit'))->toBeTrue();
    expect(file_exists($packageRoot . '/src/.gitkeep'))->toBeTrue();
});

it('the repo-root composer.json now requires markommerce/frontend at self.version', function (): void {
    $manifest = readManifest(__DIR__ . '/../../../../composer.json');

    expect($manifest['require']['markommerce/frontend'])->toBe('self.version');
});

it('composer validate exits 0 on the new package manifest', function (): void {
    $manifestPath = __DIR__ . '/../../composer.json';

    exec('composer validate --no-check-all ' . escapeshellarg($manifestPath) . ' 2>&1', $output, $exitCode);

    expect($exitCode)->toBe(0);
});
