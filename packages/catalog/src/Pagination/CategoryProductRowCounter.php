<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Pagination;

use Marko\Database\Repository\RepositoryQueryBuilder;
use Markommerce\Catalog\Contracts\ProductCategoryAssignmentRepositoryInterface;
use Markommerce\Criteria\Contracts\RowCounterInterface;

/**
 * Join-safe row counter for the category-products query.
 *
 * The joined query builder's count() drops JOIN clauses, so counting on it
 * would return wrong totals. This counter performs a simple single-table
 * count on catalog_product_category filtered by category_id — no JOINs
 * required, always accurate.
 */
class CategoryProductRowCounter implements RowCounterInterface
{
    public function __construct(
        private readonly ProductCategoryAssignmentRepositoryInterface $productCategoryAssignmentRepository,
        private readonly int $categoryId,
    ) {}

    /**
     * Count assignments for the category directly from the assignment table,
     * ignoring the passed query builder entirely (which may have JOIN clauses
     * that would corrupt a standard COUNT).
     */
    public function count(RepositoryQueryBuilder $query): int
    {
        return count($this->productCategoryAssignmentRepository->findByCategory($this->categoryId));
    }
}
