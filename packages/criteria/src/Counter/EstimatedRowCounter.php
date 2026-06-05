<?php

declare(strict_types=1);

namespace Markommerce\Criteria\Counter;

use Marko\Database\Repository\RepositoryQueryBuilder;
use Markommerce\Criteria\Contracts\RowCounterInterface;

/**
 * Fast row counter that uses a planner/catalog estimate when possible.
 *
 * Strategy:
 *  1. If the query has active filters (WHERE clauses) the estimate covers the
 *     full table and is therefore unreliable — fall through to the exact counter.
 *  2. Ask the injected CountEstimateSourceInterface for an estimate.
 *     If the source returns null (statistics not collected, unsupported query
 *     shape, etc.) fall through to the exact counter.
 *  3. Return the estimate.
 *
 * The estimate source and filter detector are injected as interfaces so the
 * class is unit-testable without a real database connection.
 */
class EstimatedRowCounter implements RowCounterInterface
{
    public function __construct(
        private readonly RowCounterInterface $rowCounter,
        private readonly CountEstimateSourceInterface $countEstimateSource,
        private readonly QueryFilterDetectorInterface $queryFilterDetector,
    ) {}

    public function count(RepositoryQueryBuilder $query): int
    {
        if ($this->queryFilterDetector->isFiltered($query)) {
            return $this->rowCounter->count($query);
        }

        $estimate = $this->countEstimateSource->estimate($query);

        if ($estimate === null) {
            return $this->rowCounter->count($query);
        }

        return $estimate;
    }
}
