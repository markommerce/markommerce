<?php

declare(strict_types=1);

use Markommerce\CatalogAttributeIndex\AttributeIndexer;
use Markommerce\CatalogAttributeIndex\IndexedAttributeReader;
use Markommerce\CatalogAttributeIndex\Repository\ProductAttributeIndexRepository;
use Markommerce\Indexer\Registry\IndexerRegistry;
use Markommerce\Indexer\ServedScopes\CartesianServedScopesProvider;
use Markommerce\Indexer\ServedScopes\ServedScopesProviderInterface;

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
        ServedScopesProviderInterface::class   => CartesianServedScopesProvider::class,
    ],
    'boot' => function (
        IndexerRegistry $indexerRegistry,
        AttributeIndexer $attributeIndexer,
    ): void {
        $indexerRegistry->register('attribute', $attributeIndexer);
    },
];
