<?php

declare(strict_types=1);

use Markommerce\CatalogAttribute\ProductAttributeAccessor;

it('autoloads a class from the Markommerce\CatalogAttribute namespace', function (): void {
    expect(class_exists(ProductAttributeAccessor::class))->toBeTrue();
});

it('marks the catalog-attribute package as a marko module in composer extra', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode((string) file_get_contents($composerPath), true);

    expect($composer['type'])->toBe('marko-module')
        ->and($composer['extra']['marko']['module'])->toBeTrue();
});

it('declares markommerce/catalog and markommerce/attribute as dependencies', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode((string) file_get_contents($composerPath), true);

    expect($composer['require'])->toHaveKey('markommerce/catalog')
        ->and($composer['require'])->toHaveKey('markommerce/attribute');
});

it('documents the catalog-attribute purpose companion and accessor in its README', function (): void {
    $readme = (string) file_get_contents(dirname(__DIR__) . '/README.md');

    expect($readme)
        ->toContain('markommerce/catalog-attribute')
        ->toContain('ProductAttributeValues')
        ->toContain('ProductAttributeAccessor')
        ->toContain('AttributeValueAccessorInterface')
        ->toContain('## Installation')
        ->toContain('## Quick Example')
        ->toContain('## Documentation');
});

it('documents the static sku name and priceAmount attributes in its README', function (): void {
    $readme = (string) file_get_contents(dirname(__DIR__) . '/README.md');

    expect($readme)
        ->toContain('sku')
        ->toContain('name')
        ->toContain('priceAmount');
});
