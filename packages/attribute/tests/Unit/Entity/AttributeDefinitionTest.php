<?php

declare(strict_types=1);

use Marko\Database\Entity\EntityMetadataFactory;
use Markommerce\Attribute\Contracts\AttributeDefinitionInterface;
use Markommerce\Attribute\Entity\AttributeDefinition;
use Markommerce\Attribute\Type\AttributeBacking;

it('maps AttributeDefinition to the attribute_definitions table with its columns', function (): void {
    $factory = new EntityMetadataFactory();
    $metadata = $factory->parse(AttributeDefinition::class);

    expect($metadata->tableName)->toBe('attribute_definitions');

    $columnNames = array_map(fn ($col) => $col->name, $metadata->columns);

    expect($columnNames)->toContain('id')
        ->and($columnNames)->toContain('code')
        ->and($columnNames)->toContain('entity_type')
        ->and($columnNames)->toContain('type')
        ->and($columnNames)->toContain('label')
        ->and($columnNames)->toContain('required')
        ->and($columnNames)->toContain('default_value')
        ->and($columnNames)->toContain('backing')
        ->and($columnNames)->toContain('filterable')
        ->and($columnNames)->toContain('searchable')
        ->and($columnNames)->toContain('facetable')
        ->and($columnNames)->toContain('scopable')
        ->and($columnNames)->toContain('config');
});

it('implements AttributeDefinitionInterface and exposes the getters', function (): void {
    expect(AttributeDefinition::class)->toImplement(AttributeDefinitionInterface::class);

    $definition = new AttributeDefinition();
    $definition->code = 'my_code';
    $definition->entityType = 'product';
    $definition->type = 'text';
    $definition->label = 'My Label';
    $definition->required = true;
    $definition->backing = 'Json';

    expect($definition->code())->toBe('my_code')
        ->and($definition->entityType())->toBe('product')
        ->and($definition->type())->toBe('text')
        ->and($definition->isRequired())->toBeTrue()
        ->and($definition->backing())->toBe(AttributeBacking::Json);
});

it('returns an empty array from config when the config column is null', function (): void {
    $definition = new AttributeDefinition();
    $definition->config = null;

    expect($definition->config())->toBe([]);
});

it('defaults a new AttributeDefinition backing to Json', function (): void {
    $definition = new AttributeDefinition();

    expect($definition->backing())->toBe(AttributeBacking::Json);
});

it('defaults the boolean flag columns to false', function (): void {
    $definition = new AttributeDefinition();

    expect($definition->required)->toBeFalse()
        ->and($definition->filterable)->toBeFalse()
        ->and($definition->searchable)->toBeFalse()
        ->and($definition->facetable)->toBeFalse()
        ->and($definition->scopable)->toBeFalse();
});

it('stores type-specific params in the config column', function (): void {
    $definition = new AttributeDefinition();
    $definition->config = ['max_length' => 255, 'multiline' => false];

    expect($definition->config())->toBe(['max_length' => 255, 'multiline' => false]);
});
