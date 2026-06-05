<?php

declare(strict_types=1);

namespace Markommerce\Criteria\Counter;

use Marko\Database\Repository\RepositoryQueryBuilder;
use Markommerce\Criteria\Contracts\RowCounterInterface;

/**
 * Exact row counter — executes a real COUNT(*) over the filtered query.
 *
 * KNOWN LIMITATION: marko/database's aggregate path emits
 * `SELECT COUNT(*) FROM <table> WHERE …` and does NOT include any JOIN clauses
 * that were added to the query. Therefore ExactRowCounter is correct only for
 * single-table (WHERE-filtered) queries. When the row set is defined by a JOIN
 * (e.g. catalog's category membership join, Task 012) the caller MUST supply a
 * counter that wraps a join-safe query instead of using this class directly.
 * Silently returning a wrong number is a bug — document and respect this boundary.
 */
class ExactRowCounter implements RowCounterInterface
{
    public function count(RepositoryQueryBuilder $query): int
    {
        return $query->count();
    }
}
