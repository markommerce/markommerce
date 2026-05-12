<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Repository;

interface ProductCategoryRepositoryInterface
{
    /**
     * Idempotently assign a product to a category.
     *
     * @return bool true when a new row was inserted; false when already existed.
     * @throws \Throwable
     */
    public function assign(int $productId, int $categoryId): bool;

    /**
     * Remove a product↔category assignment.
     *
     * @return bool true when a row was deleted; false when no assignment existed.
     */
    public function unassign(int $productId, int $categoryId): bool;

    /** @return array<int> */
    public function findCategoryIdsForProduct(int $productId): array;

    /** @return array<int> */
    public function findProductIdsForCategory(int $categoryId): array;
}
