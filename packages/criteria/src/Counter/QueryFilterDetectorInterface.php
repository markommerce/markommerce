<?php

declare(strict_types=1);

namespace Markommerce\Criteria\Counter;

use Marko\Database\Repository\RepositoryQueryBuilder;

/**
 * Detects whether a query has been narrowed by filters (WHERE clauses).
 *
 * Used by EstimatedRowCounter to decide whether a planner estimate is still
 * representative: when filters are present the estimate covers the full table,
 * not the filtered subset, so the exact counter must be used instead.
 */
interface QueryFilterDetectorInterface
{
    public function isFiltered(RepositoryQueryBuilder $query): bool;
}
