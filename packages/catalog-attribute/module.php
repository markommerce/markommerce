<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Database\Entity\EntityMetadataFactory;
use Markommerce\Attribute\Contracts\AttributeValueAccessorInterface;
use Markommerce\Attribute\Registry\AttributeEntityClassMap;
use Markommerce\Catalog\Entity\Product;
use Markommerce\CatalogAttribute\Entity\ProductAttributeValues;
use Markommerce\CatalogAttribute\ProductAttributeAccessor;

return [
    'require' => [
        'markommerce/catalog' => true,
        'markommerce/attribute' => true,
    ],
    'bindings' => [
        AttributeValueAccessorInterface::class => ProductAttributeAccessor::class,
    ],
    'singletons' => [],
    'boot' => static function (ContainerInterface $container): void {
        $entityClassMap = $container->get(AttributeEntityClassMap::class);
        $entityClassMap->register('product', Product::class);

        $metadataFactory = $container->get(EntityMetadataFactory::class);
        $metadataFactory->linkExtenders(Product::class, [ProductAttributeValues::class]);
    },
];
