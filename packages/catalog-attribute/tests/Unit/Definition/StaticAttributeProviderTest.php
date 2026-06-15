<?php

declare(strict_types=1);

use Markommerce\Attribute\Type\AttributeBacking;
use Markommerce\CatalogAttribute\Definition\StaticAttributeProvider;

it('provides Column-backed static definitions for sku name and priceAmount', function (): void {
    $provider = new StaticAttributeProvider();

    $definitions = $provider->definitions();

    expect($definitions)->toHaveCount(3);

    $codes = array_map(fn ($d) => $d->code, $definitions);
    expect($codes)->toContain('sku')
        ->toContain('name')
        ->toContain('priceAmount');

    foreach ($definitions as $definition) {
        expect($definition->backing())->toBe(AttributeBacking::Column);
    }
});

it('marks static definitions with the product entity type and a config property mapping', function (): void {
    $provider = new StaticAttributeProvider();

    $definitions = $provider->definitions();

    foreach ($definitions as $definition) {
        expect($definition->entityType)->toBe('product');
        expect($definition->config)->toBeArray();
        expect($definition->config)->toHaveKey('property');
    }

    $byCode = [];
    foreach ($definitions as $definition) {
        $byCode[$definition->code] = $definition;
    }

    expect($byCode['sku']->config['property'])->toBe('sku');
    expect($byCode['name']->config['property'])->toBe('name');
    expect($byCode['priceAmount']->config['property'])->toBe('priceAmount');
});
