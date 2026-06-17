<?php

declare(strict_types=1);

use Marko\Database\Entity\EntityMetadataFactory;
use Markommerce\Attribute\Entity\AttributeOption;

it('maps AttributeOption to the attribute_options table with its columns', function (): void {
    $factory = new EntityMetadataFactory();
    $metadata = $factory->parse(AttributeOption::class);

    expect($metadata->tableName)->toBe('attribute_options');

    $columnNames = array_map(fn ($col) => $col->name, $metadata->columns);

    expect($columnNames)->toContain('id')
        ->and($columnNames)->toContain('attribute_id')
        ->and($columnNames)->toContain('value')
        ->and($columnNames)->toContain('label')
        ->and($columnNames)->toContain('position');

    $attributeIdColumn = array_find($metadata->columns, fn ($col) => $col->name === 'attribute_id');
    assert($attributeIdColumn !== null);
    expect($attributeIdColumn->references)->toBe('attribute_definitions')
        ->and($attributeIdColumn->onDelete)->toBe('CASCADE');

    $positionColumn = array_find($metadata->columns, fn ($col) => $col->name === 'position');
    assert($positionColumn !== null);
    expect($positionColumn->default)->toBe(0);
});
