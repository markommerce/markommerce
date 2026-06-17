<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Database\Entity\EntityMetadataFactory;
use Markommerce\Catalog\Entity\Product;
use Markommerce\CatalogAttributeScope\Entity\ProductScopedAttributeValues;

return [
    'require' => [
        'markommerce/catalog-attribute' => '*',
        'markommerce/attribute' => '*',
        'markommerce/scope' => '*',
        'markommerce/catalog' => '*',
    ],
    'boot' => static function (ContainerInterface $container): void {
        $metadataFactory = $container->get(EntityMetadataFactory::class);
        $existing = $metadataFactory->parse(Product::class)->extenders;
        $metadataFactory->linkExtenders(
            Product::class,
            array_values(array_unique(array_merge($existing, [ProductScopedAttributeValues::class]))),
        );
    },
];
