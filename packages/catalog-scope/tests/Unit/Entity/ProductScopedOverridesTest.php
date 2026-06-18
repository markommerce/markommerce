<?php

declare(strict_types=1);

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Markommerce\Catalog\Entity\Product;
use Markommerce\CatalogScope\Entity\ProductScopedOverrides;
use Markommerce\Scope\Storage\HasScopesInterface;

it(
    'declares ProductScopedOverrides with #[Table(extends: Product::class)] and no additional columns',
    function (): void {
        $reflection = new ReflectionClass(ProductScopedOverrides::class);
        $attributes = $reflection->getAttributes(Table::class);

        expect($attributes)->toHaveCount(1);

        $table = $attributes[0]->newInstance();

        expect($table->extends)->toBe(Product::class)
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

it('has ProductScopedOverrides implement HasScopesInterface via the HasScopes trait', function (): void {
    $overrides = new ProductScopedOverrides();

    expect($overrides)->toBeInstanceOf(HasScopesInterface::class)
        ->and($overrides)->toBeInstanceOf(Entity::class);
});
