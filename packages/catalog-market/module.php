<?php

declare(strict_types=1);

/**
 * catalog-market bridge — placeholder module.
 *
 * This bridge is intentionally empty today. It reserves the ScopedFieldRegistry
 * hook for future market-scoped fields (e.g. price, visibility) once those
 * columns land on the Product entity.
 *
 * See FEATURES.md tier 3 for the planned scope of this bridge.
 */

use Markommerce\Scope\Metadata\ScopedFieldRegistry;

return [
    'require' => [
        'markommerce/catalog-scope' => '*',
        'markommerce/market' => '*',
    ],
    'boot' => function (ScopedFieldRegistry $scopedFieldRegistry): void {
        // No fields registered yet — Product does not have price or visibility columns.
        // This closure is type-hinted on ScopedFieldRegistry for future expansion.
    },
];
