<?php

declare(strict_types=1);

use Markommerce\Attribute\Entity\AttributeDefinition;
use Markommerce\CatalogAttribute\Definition\ProductAttributeDefinitions;
use Markommerce\CatalogAttribute\Definition\StaticAttributeProvider;
use Markommerce\CatalogAttribute\Tests\Support\FakeAttributeDefinitionRepository;

it('resolves a static definition by its code', function (): void {
    $repository = new FakeAttributeDefinitionRepository();
    $provider = new StaticAttributeProvider();
    $resolver = new ProductAttributeDefinitions($repository, $provider);

    $definition = $resolver->findByCode('sku');

    expect($definition)->toBeInstanceOf(AttributeDefinition::class);
    expect($definition->code)->toBe('sku');
});

it('resolves a custom definition from the repository when not static', function (): void {
    $repository = new FakeAttributeDefinitionRepository();
    $provider = new StaticAttributeProvider();
    $resolver = new ProductAttributeDefinitions($repository, $provider);

    $custom = new AttributeDefinition();
    $custom->code = 'color';
    $custom->entityType = 'product';
    $custom->type = 'text';
    $custom->backing = 'Json';
    $repository->save($custom);

    $definition = $resolver->findByCode('color');

    expect($definition)->toBeInstanceOf(AttributeDefinition::class);
    expect($definition->code)->toBe('color');
});

it('returns null when no static or custom definition matches the code', function (): void {
    $repository = new FakeAttributeDefinitionRepository();
    $provider = new StaticAttributeProvider();
    $resolver = new ProductAttributeDefinitions($repository, $provider);

    $definition = $resolver->findByCode('nonexistent');

    expect($definition)->toBeNull();
});

it('prefers a static definition over a custom one with the same code', function (): void {
    $repository = new FakeAttributeDefinitionRepository();
    $provider = new StaticAttributeProvider();
    $resolver = new ProductAttributeDefinitions($repository, $provider);

    $custom = new AttributeDefinition();
    $custom->code = 'sku';
    $custom->entityType = 'product';
    $custom->type = 'text';
    $custom->backing = 'Json';
    $repository->save($custom);

    $definition = $resolver->findByCode('sku');

    expect($definition)->toBeInstanceOf(AttributeDefinition::class);
    expect($definition->backing()->name)->toBe('Column');
});
