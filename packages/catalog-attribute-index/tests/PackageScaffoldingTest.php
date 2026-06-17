<?php

declare(strict_types=1);

use Markommerce\CatalogAttributeIndex\AttributeIndexer;

it('autoloads a class from the Markommerce\CatalogAttributeIndex namespace', function (): void {
    expect(class_exists(AttributeIndexer::class))->toBeTrue();
});

it('marks catalog-attribute-index as a marko module in composer extra', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['extra']['marko']['module'])->toBeTrue();
});

it('declares markommerce/indexer as a dependency of catalog-attribute-index', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['require'])->toHaveKey('markommerce/indexer')
        ->and($composer['require']['markommerce/indexer'])->toBe('self.version');
});

it('documents the attribute index EAV shape rebuild command and live fallback in its README', function (): void {
    $readme = (string) file_get_contents(dirname(__DIR__) . '/README.md');

    expect($readme)
        ->toContain('markommerce/catalog-attribute-index')
        ->toContain('ProductAttributeIndexEntry')
        ->toContain('AttributeIndexer')
        ->toContain('IndexedAttributeReader')
        ->toContain('index:rebuild attribute')
        ->toContain('## Installation')
        ->toContain('## Quick Example')
        ->toContain('## Documentation');
});
