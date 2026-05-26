<?php

declare(strict_types=1);

use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Scope\Metadata\ScopedFieldRegistry;

return [
    'require' => [
        'markommerce/catalog-scope' => '*',
        'markommerce/locale' => '*',
    ],
    'boot' => function (ScopedFieldRegistry $scopedFieldRegistry): void {
        foreach ([Product::class, Category::class] as $entityClass) {
            foreach (['name', 'description'] as $property) {
                $scopedFieldRegistry->register(
                    entityClass: $entityClass,
                    property: $property,
                    axes: ['locale'],
                );
            }
        }
    },
];
