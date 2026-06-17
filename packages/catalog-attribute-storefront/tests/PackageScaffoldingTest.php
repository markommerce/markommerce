<?php

declare(strict_types=1);

use Markommerce\CatalogAttributeStorefront\Module;

it('autoloads a class from the Markommerce\CatalogAttributeStorefront namespace', function (): void {
    expect(class_exists(Module::class))->toBeTrue();
});

it('marks catalog-attribute-storefront as a marko module in composer extra', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode((string) file_get_contents($composerPath), true);

    expect($composer['extra']['marko']['module'])->toBeTrue();
});

it('declares markommerce/catalog-attribute-index as a dependency', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode((string) file_get_contents($composerPath), true);

    expect($composer['require'])->toHaveKey('markommerce/catalog-attribute-index')
        ->and($composer['require']['markommerce/catalog-attribute-index'])->toBe('self.version');
});
