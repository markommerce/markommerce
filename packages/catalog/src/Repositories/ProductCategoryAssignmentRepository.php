<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Repositories;

use Marko\Database\Exceptions\RepositoryException;
use Marko\Database\Repository\Repository;
use Markommerce\Catalog\Contracts\ProductCategoryAssignmentRepositoryInterface;
use Markommerce\Catalog\Entity\ProductCategoryAssignment;

class ProductCategoryAssignmentRepository extends Repository implements ProductCategoryAssignmentRepositoryInterface
{
    protected const string ENTITY_CLASS = ProductCategoryAssignment::class;

    /**
     * Find all assignments for a given category.
     *
     * @return array<ProductCategoryAssignment>
     * @throws RepositoryException
     */
    public function findByCategory(int $categoryId): array
    {
        /** @var array<ProductCategoryAssignment> */
        return $this->findBy(['categoryId' => $categoryId])->toArray();
    }

    /**
     * Find the assignment for a specific product and category combination.
     *
     * @throws RepositoryException
     */
    public function findByProductAndCategory(int $productId, int $categoryId): ?ProductCategoryAssignment
    {
        /** @var ProductCategoryAssignment|null */
        return $this->findOneBy(['productId' => $productId, 'categoryId' => $categoryId]);
    }
}
