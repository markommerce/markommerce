<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Service;

use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Exception\CategoryNotFoundException;
use Markommerce\Catalog\Exception\ProductNotFoundException;

interface CategoryAssignmentServiceInterface
{
    /**
     * @throws ProductNotFoundException
     * @throws CategoryNotFoundException
     */
    public function assign(int $productId, int $categoryId): void;

    public function unassign(int $productId, int $categoryId): void;

    /**
     * @return array<Category>
     * @throws ProductNotFoundException
     */
    public function getCategoriesForProduct(int $productId): array;

    /**
     * @return array<Product>
     * @throws CategoryNotFoundException
     */
    public function getProductsInCategory(int $categoryId): array;
}
