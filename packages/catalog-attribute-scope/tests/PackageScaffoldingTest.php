<?php

declare(strict_types=1);
use Markommerce\CatalogAttributeScope\ScopedProductAttributeAccessor;

it('autoloads a class from the Markommerce\CatalogAttributeScope namespace', function (): void {
    expect(class_exists(ScopedProductAttributeAccessor::class))->toBeTrue();
});

it('marks catalog-attribute-scope as a marko module in composer extra', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['extra']['marko']['module'])->toBeTrue();
});

it('declares markommerce/scope as a dependency of catalog-attribute-scope', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['require'])->toHaveKey('markommerce/scope')
        ->and($composer['require']['markommerce/scope'])->toBe('self.version');
});
