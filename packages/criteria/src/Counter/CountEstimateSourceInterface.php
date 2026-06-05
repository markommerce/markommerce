<?php

declare(strict_types=1);

namespace Markommerce\Criteria\Counter;

use Marko\Database\Repository\RepositoryQueryBuilder;

/**
 * Provides a fast planner/catalog row-count estimate for a query.
 *
 * Returns null when no estimate is available (e.g. the database statistics
 * have not been collected yet, or the implementation does not support this
 * particular query shape).
 */
interface CountEstimateSourceInterface
{
    public function estimate(RepositoryQueryBuilder $query): ?int;
}
