<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Pagination;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Repository\RepositoryQueryBuilder;
use Markommerce\Criteria\Contracts\RowCounterInterface;

/**
 * Join-safe, filter-aware row counter for the category-products query.
 *
 * The query builder's own `count()` drops JOIN clauses, so counting on it
 * directly returns wrong totals for the joined category query. Instead this
 * counter compiles the fully-built query (JOINs, WHEREs, and any
 * ProductListFilter EXISTS constraints) into a subquery and counts its rows:
 *
 *     SELECT COUNT(*) FROM ( <the category query> ) AS sub
 *
 * This keeps the total accurate whether or not attribute filters are applied.
 * The previous implementation counted raw category assignments and ignored the
 * query entirely, over-reporting totals (and page counts) for filtered listings.
 */
class CategoryProductRowCounter implements RowCounterInterface
{
    public function __construct(
        private readonly ConnectionInterface $connection,
    ) {}

    public function count(RepositoryQueryBuilder $query): int
    {
        $bindings = [];
        $subquery = $query->compileSubquery($bindings);

        $rows = $this->connection->query(
            'SELECT COUNT(*) AS aggregate FROM (' . $subquery . ') AS category_products_count',
            $bindings,
        );

        return (int) ($rows[0]['aggregate'] ?? 0);
    }
}
