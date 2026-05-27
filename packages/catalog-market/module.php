<?php

declare(strict_types=1);

/**
 * catalog-market module manifest.
 *
 * The boot closure currently registers no scoped fields — Product does not yet have
 * price/visibility columns. See FEATURES.md tier 3 for the planned end state.
 */

use Markommerce\CatalogMarket\Contracts\CategoryTreeMarketAssignmentRepositoryInterface;
use Markommerce\CatalogMarket\Repositories\CategoryTreeMarketAssignmentRepository;
use Markommerce\Scope\Metadata\ScopedFieldRegistry;

return [
    'require' => [
        'markommerce/catalog-scope' => '*',
        'markommerce/market'        => '*',
        'markommerce/catalog'       => '*',
    ],
    'bindings' => [
        CategoryTreeMarketAssignmentRepositoryInterface::class => CategoryTreeMarketAssignmentRepository::class,
    ],
    'boot' => function (ScopedFieldRegistry $scopedFieldRegistry): void {
        // Placeholder: no scoped fields registered yet.
    },
];
