<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Contracts;

use Marko\Database\Repository\RepositoryInterface;
use Markommerce\Catalog\Entity\ProductCategoryAssignment;

/**
 * @extends RepositoryInterface<ProductCategoryAssignment>
 */
interface ProductCategoryAssignmentRepositoryInterface extends RepositoryInterface
{
    /**
     * Find all assignments for a given category.
     *
     * @return array<ProductCategoryAssignment>
     */
    public function findByCategory(int $categoryId): array;

    /**
     * Find the assignment for a specific product and category combination.
     */
    public function findByProductAndCategory(
        int $productId,
        int $categoryId,
    ): ?ProductCategoryAssignment;
}
