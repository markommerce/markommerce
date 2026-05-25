<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Services;

use Marko\Database\Exceptions\RepositoryException;
use Markommerce\Catalog\Contracts\CategoryRepositoryInterface;
use Markommerce\Catalog\Contracts\ProductCategoryAssignmentRepositoryInterface;
use Markommerce\Catalog\Contracts\ProductRepositoryInterface;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Entity\ProductCategoryAssignment;
use Markommerce\Catalog\Exceptions\CategoryNotFoundException;
use Markommerce\Catalog\Exceptions\ProductNotFoundException;

class CategoryAssignmentService
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private CategoryRepositoryInterface $categoryRepository,
        private ProductCategoryAssignmentRepositoryInterface $productCategoryAssignmentRepository,
    ) {}

    /**
     * @throws ProductNotFoundException|CategoryNotFoundException
     */
    public function assign(
        int $productId,
        int $categoryId,
    ): void
    {
        if ($this->productRepository->find($productId) === null) {
            throw ProductNotFoundException::forId($productId);
        }

        if ($this->categoryRepository->find($categoryId) === null) {
            throw CategoryNotFoundException::forId($categoryId);
        }

        $existing = $this->productCategoryAssignmentRepository->findByProductAndCategory($productId, $categoryId);

        if ($existing !== null) {
            return;
        }

        $assignment = new ProductCategoryAssignment();
        $assignment->productId = $productId;
        $assignment->categoryId = $categoryId;

        $this->productCategoryAssignmentRepository->save($assignment);
    }

    /**
     * @throws RepositoryException
     */
    public function detach(
        int $productId,
        int $categoryId,
    ): void
    {
        $existing = $this->productCategoryAssignmentRepository->findByProductAndCategory($productId, $categoryId);

        if ($existing === null) {
            return;
        }

        $this->productCategoryAssignmentRepository->delete($existing);
    }

    /**
     * @return list<Product>
     * @throws CategoryNotFoundException
     */
    public function productsInCategory(int $categoryId): array
    {
        if ($this->categoryRepository->find($categoryId) === null) {
            throw CategoryNotFoundException::forId($categoryId);
        }

        $assignments = $this->productCategoryAssignmentRepository->findByCategory($categoryId);

        $products = [];

        foreach ($assignments as $assignment) {
            $product = $this->productRepository->find($assignment->productId);

            if ($product === null) {
                continue;
            }

            $products[] = $product;
        }

        return $products;
    }
}
