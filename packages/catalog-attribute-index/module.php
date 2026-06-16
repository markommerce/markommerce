<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Markommerce\CatalogAttributeIndex\AttributeIndexer;
use Markommerce\CatalogAttributeIndex\IndexedAttributeReader;
use Markommerce\CatalogAttributeIndex\Repository\ProductAttributeIndexRepository;
use Markommerce\Indexer\Registry\IndexerRegistry;

return [
    'require' => [
        'marko/core'                            => '*',
        'marko/database'                        => '*',
        'markommerce/attribute'                 => '*',
        'markommerce/catalog'                   => '*',
        'markommerce/catalog-attribute'         => '*',
        'markommerce/catalog-attribute-scope'   => '*',
        'markommerce/indexer'                   => '*',
        'markommerce/scope'                     => '*',
    ],
    'bindings' => [
        AttributeIndexer::class                => AttributeIndexer::class,
        IndexedAttributeReader::class          => IndexedAttributeReader::class,
        ProductAttributeIndexRepository::class => ProductAttributeIndexRepository::class,
    ],
    'boot' => function (
        IndexerRegistry $indexerRegistry,
        ContainerInterface $container,
    ): void {
        $indexerRegistry->register('attribute', static fn (): AttributeIndexer => $container->get(AttributeIndexer::class));
    },
];
