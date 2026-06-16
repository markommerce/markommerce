<?php

declare(strict_types=1);

use Markommerce\Indexer\Contracts\IndexRepositoryInterface;
use Markommerce\Indexer\Registry\IndexerRegistry;
use Markommerce\Indexer\Repository\IndexRepository;
use Markommerce\Indexer\ServedScopes\CartesianServedScopesProvider;
use Markommerce\Indexer\ServedScopes\ServedScopesProviderInterface;

return [
    'require' => [
        'marko/core'         => '*',
        'marko/database'     => '*',
        'markommerce/scope'  => '*',
    ],
    'bindings' => [
        IndexRepositoryInterface::class    => IndexRepository::class,
        ServedScopesProviderInterface::class => CartesianServedScopesProvider::class,
    ],
    'singletons' => [
        IndexerRegistry::class,
    ],
];
