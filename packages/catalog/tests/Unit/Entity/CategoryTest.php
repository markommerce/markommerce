<?php

declare(strict_types=1);

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Markommerce\Catalog\Entity\Category;

it('maps the Category entity to the catalog_categories table', function (): void {
    $reflection = new ReflectionClass(Category::class);
    $attributes = $reflection->getAttributes(Table::class);

    expect($attributes)->toHaveCount(1);

    $table = $attributes[0]->newInstance();

    expect($table->name)->toBe('catalog_categories');
});

it('does not implement HasScopesInterface on Category', function (): void {
    $hasScopesInterface = 'Markommerce\\Scope\\Storage\\HasScopesInterface';
    $category = new Category();

    $implements = interface_exists($hasScopesInterface)
        ? ($category instanceof $hasScopesInterface)
        : false;

    expect($implements)->toBeFalse();
});

it('does not declare a scopes column on the Category entity reflection', function (): void {
    $reflection = new ReflectionClass(Category::class);

    expect($reflection->hasProperty('scopes'))->toBeFalse();
});

it('has no #[Scoped] attributes on any Category property', function (): void {
    $scopedClass = 'Markommerce\\Scope\\Attributes\\Scoped';
    $reflection = new ReflectionClass(Category::class);

    foreach ($reflection->getProperties() as $property) {
        $scopedAttrs = array_filter(
            $property->getAttributes(),
            fn ($attr) => $attr->getName() === $scopedClass,
        );
        expect(count($scopedAttrs))->toBe(0);
    }
});

it('has no Markommerce\\Scope namespace imports in the Category class file', function (): void {
    $file = (new ReflectionClass(Category::class))->getFileName();
    $contents = file_get_contents($file);

    expect($contents)->not->toContain('Markommerce\\Scope');
});
