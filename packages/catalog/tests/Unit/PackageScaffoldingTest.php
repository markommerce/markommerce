<?php

declare(strict_types=1);

/**
 * @return array<string, mixed>
 */
function readCatalogManifest(): array
{
    $path = __DIR__ . '/../../composer.json';
    $contents = file_get_contents($path);
    expect($contents)->not->toBeFalse();
    /** @var string $contents */

    return json_decode($contents, true);
}

it('defines the markommerce/catalog package with type marko-module', function (): void {
    $path = __DIR__ . '/../../composer.json';

    expect(file_exists($path))->toBeTrue();

    $manifest = readCatalogManifest();

    expect($manifest['name'])->toBe('markommerce/catalog');
    expect($manifest['type'])->toBe('marko-module');
});

it('autoloads the Markommerce\Catalog namespace from the src directory', function (): void {
    $manifest = readCatalogManifest();

    expect($manifest['autoload']['psr-4']['Markommerce\\Catalog\\'])->toBe('src/');
});

it('autoloads the Markommerce\Catalog\Seed namespace from the Seed directory', function (): void {
    $manifest = readCatalogManifest();

    expect($manifest['autoload']['psr-4']['Markommerce\\Catalog\\Seed\\'])->toBe('Seed/');
});

it('autoloads the Markommerce\Catalog\Tests namespace from the tests directory', function (): void {
    $manifest = readCatalogManifest();

    expect($manifest['autoload-dev']['psr-4']['Markommerce\\Catalog\\Tests\\'])->toBe('tests/');
});

it('requires markommerce/scope and marko/database', function (): void {
    $manifest = readCatalogManifest();

    expect($manifest['require'])->toHaveKey('markommerce/scope');
    expect($manifest['require']['markommerce/scope'])->toBe('self.version');
    expect($manifest['require'])->toHaveKey('marko/database');
    expect($manifest['require']['marko/database'])->toBe('self.version');
});

it('requires the marko routing, view, view-latte and layout packages', function (): void {
    $manifest = readCatalogManifest();

    expect($manifest['require'])->toHaveKey('marko/routing');
    expect($manifest['require']['marko/routing'])->toBe('self.version');
    expect($manifest['require'])->toHaveKey('marko/view');
    expect($manifest['require']['marko/view'])->toBe('self.version');
    expect($manifest['require'])->toHaveKey('marko/view-latte');
    expect($manifest['require']['marko/view-latte'])->toBe('self.version');
    expect($manifest['require'])->toHaveKey('marko/layout');
    expect($manifest['require']['marko/layout'])->toBe('self.version');
});

it('enables the marko module flag in composer extra', function (): void {
    $manifest = readCatalogManifest();

    expect($manifest['extra']['marko']['module'])->toBeTrue();
});
