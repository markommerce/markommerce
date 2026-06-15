<?php

declare(strict_types=1);

use Marko\Database\Entity\EntityMetadataFactory;
use Markommerce\Attribute\Reserved\ReservedCodeProvider;
use Markommerce\Attribute\Tests\Fixtures\ExtendedEntity;
use Markommerce\Attribute\Tests\Fixtures\SimpleEntity;

it('derives reserved codes from a fixture entity column names', function (): void {
    $factory = new EntityMetadataFactory();
    $provider = new ReservedCodeProvider($factory);

    $codes = $provider->reservedCodes(SimpleEntity::class);

    expect($codes)->toContain('id')
        ->and($codes)->toContain('product_sku')
        ->and($codes)->toContain('name');
});

it('includes property names as reserved codes', function (): void {
    $factory = new EntityMetadataFactory();
    $provider = new ReservedCodeProvider($factory);

    $codes = $provider->reservedCodes(SimpleEntity::class);

    // 'productSku' is the property name; 'product_sku' is the column name
    expect($codes)->toContain('productSku')
        ->and($codes)->toContain('id')
        ->and($codes)->toContain('name');
});

it('returns a de-duplicated list of reserved codes', function (): void {
    $factory = new EntityMetadataFactory();
    $provider = new ReservedCodeProvider($factory);

    $codes = $provider->reservedCodes(SimpleEntity::class);

    // 'id' maps to column 'id' — same string for property and column, so it must appear only once
    // 'name' also maps to column 'name' — same dedup scenario
    expect($codes)->toBe(array_unique($codes));
});

it('reflects added columns when the fixture entity declares more', function (): void {
    $factory = new EntityMetadataFactory();
    $provider = new ReservedCodeProvider($factory);

    $simpleCodes = $provider->reservedCodes(SimpleEntity::class);
    $extendedCodes = $provider->reservedCodes(ExtendedEntity::class);

    // ExtendedEntity has 'sku', 'createdAt'/'created_at', 'updatedAt'/'updated_at' beyond basic SimpleEntity
    expect($extendedCodes)->toContain('sku')
        ->and($extendedCodes)->toContain('created_at')
        ->and($extendedCodes)->toContain('createdAt')
        ->and($extendedCodes)->toContain('updated_at')
        ->and($extendedCodes)->toContain('updatedAt')
        ->and(count($extendedCodes))->toBeGreaterThan(count($simpleCodes));
});
