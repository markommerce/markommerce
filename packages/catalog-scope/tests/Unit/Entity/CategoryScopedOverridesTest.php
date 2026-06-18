<?php

declare(strict_types=1);

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Markommerce\Catalog\Entity\Category;
use Markommerce\CatalogScope\Entity\CategoryScopedOverrides;
use Markommerce\Scope\Storage\HasScopesInterface;

it(
    'declares CategoryScopedOverrides with #[Table(extends: Category::class)] and no additional columns',
    function (): void {
        $reflection = new ReflectionClass(CategoryScopedOverrides::class);
        $attributes = $reflection->getAttributes(Table::class);

        expect($attributes)->toHaveCount(1);

        $table = $attributes[0]->newInstance();

        expect($table->extends)->toBe(Category::class)
            ->and($table->name)->toBeNull();

        // The only Column-annotated property must be 'scopes' (from HasScopes trait)
        // No additional custom columns declared directly on the class
        $columnProperties = array_filter(
            $reflection->getProperties(ReflectionProperty::IS_PUBLIC),
            fn (ReflectionProperty $p) => count($p->getAttributes(Column::class)) > 0,
        );

        $columnNames = array_map(fn (ReflectionProperty $p) => $p->getName(), $columnProperties);

        expect(array_values($columnNames))->toBe(['scopes']);
    },
);

it('has CategoryScopedOverrides implement HasScopesInterface via the HasScopes trait', function (): void {
    $overrides = new CategoryScopedOverrides();

    expect($overrides)->toBeInstanceOf(HasScopesInterface::class)
        ->and($overrides)->toBeInstanceOf(Entity::class);
});
