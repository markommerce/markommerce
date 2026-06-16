<?php

declare(strict_types=1);

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Markommerce\Attribute\Entity\AttributeOption;
use Markommerce\AttributeScope\Entity\AttributeOptionScopedLabels;
use Markommerce\Scope\Storage\HasScopesInterface;

it('maps AttributeOptionScopedLabels to the attribute_options table via the scoped_labels column', function (): void {
    $reflection = new ReflectionClass(AttributeOptionScopedLabels::class);
    $attributes = $reflection->getAttributes(Table::class);

    expect($attributes)->toHaveCount(1);

    $table = $attributes[0]->newInstance();

    expect($table->extends)->toBe(AttributeOption::class)
        ->and($table->name)->toBeNull();

    $columnProperties = array_filter(
        $reflection->getProperties(ReflectionProperty::IS_PUBLIC),
        fn (ReflectionProperty $p) => count($p->getAttributes(Column::class)) > 0,
    );

    $columnNames = array_map(fn (ReflectionProperty $p) => $p->getName(), $columnProperties);

    expect(array_values($columnNames))->toBe(['scopedLabels']);

    $scopedLabelsProperty = $reflection->getProperty('scopedLabels');
    $columnAttributes = $scopedLabelsProperty->getAttributes(Column::class);

    expect($columnAttributes)->toHaveCount(1);

    $column = $columnAttributes[0]->newInstance();

    expect($column->name)->toBe('scoped_labels')
        ->and($column->type)->toBe('json')
        ->and($column->nullable)->toBeTrue();
});

it('sets and reads a label override for a signature', function (): void {
    $companion = new AttributeOptionScopedLabels();

    $companion->setOverride('locale:en', 'label', 'English Label');

    expect($companion->override('locale:en', 'label'))->toBe('English Label')
        ->and($companion->scopedLabels)->toBe(['locale:en' => ['label' => 'English Label']]);
});

it('reports whether a signature has a label override', function (): void {
    $companion = new AttributeOptionScopedLabels();

    expect($companion->hasOverride('locale:en', 'label'))->toBeFalse();

    $companion->setOverride('locale:en', 'label', 'English Label');

    expect($companion->hasOverride('locale:en', 'label'))->toBeTrue()
        ->and($companion->hasOverride('locale:fr', 'label'))->toBeFalse();
});

it('clears a label override and nulls the column when empty', function (): void {
    $companion = new AttributeOptionScopedLabels();

    $companion->setOverride('locale:en', 'label', 'English Label');
    $companion->setOverride('locale:fr', 'label', 'French Label');

    $companion->clearOverride('locale:en', 'label');

    expect($companion->hasOverride('locale:en', 'label'))->toBeFalse()
        ->and($companion->hasOverride('locale:fr', 'label'))->toBeTrue()
        ->and($companion->scopedLabels)->toBe(['locale:fr' => ['label' => 'French Label']]);

    $companion->clearOverride('locale:fr', 'label');

    expect($companion->scopedLabels)->toBeNull();
});

it('implements HasScopesInterface', function (): void {
    $companion = new AttributeOptionScopedLabels();

    expect($companion)->toBeInstanceOf(HasScopesInterface::class)
        ->and($companion)->toBeInstanceOf(Entity::class);
});
