<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Service;

use Marko\Core\Event\EventDispatcherInterface;
use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Event\ProductAssignedToCategory;
use Markommerce\Catalog\Event\ProductRemovedFromCategory;
use Markommerce\Catalog\Exception\CategoryNotFoundException;
use Markommerce\Catalog\Exception\ProductNotFoundException;
use Markommerce\Catalog\Repository\CategoryRepositoryInterface;
use Markommerce\Catalog\Repository\ProductCategoryRepositoryInterface;
use Markommerce\Catalog\Repository\ProductRepositoryInterface;
use Throwable;

class CategoryAssignmentService implements CategoryAssignmentServiceInterface
{
    /**
     * @param ProductRepositoryInterface<Product> $productRepository
     * @param CategoryRepositoryInterface<Category> $categoryRepository
     */
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private CategoryRepositoryInterface $categoryRepository,
        private ProductCategoryRepositoryInterface $productCategoryRepository,
        private ?EventDispatcherInterface $eventDispatcher = null,
    ) {}

    /**
     * @throws ProductNotFoundException
     * @throws CategoryNotFoundException
     * @throws Throwable
     */
    public function assign(int $productId, int $categoryId): void
    {
        if ($this->productRepository->find($productId) === null) {
            throw ProductNotFoundException::forId($productId);
        }

        if ($this->categoryRepository->find($categoryId) === null) {
            throw CategoryNotFoundException::forId($categoryId);
        }

        $inserted = $this->productCategoryRepository->assign($productId, $categoryId);

        if ($inserted) {
            $this->eventDispatcher?->dispatch(new ProductAssignedToCategory($productId, $categoryId));
        }
    }

    public function unassign(int $productId, int $categoryId): void
    {
        $removed = $this->productCategoryRepository->unassign($productId, $categoryId);

        if ($removed) {
            $this->eventDispatcher?->dispatch(new ProductRemovedFromCategory($productId, $categoryId));
        }
    }

    /**
     * @return array<Category>
     * @throws ProductNotFoundException
     */
    public function getCategoriesForProduct(int $productId): array
    {
        if ($this->productRepository->find($productId) === null) {
            throw ProductNotFoundException::forId($productId);
        }

        $ids = $this->productCategoryRepository->findCategoryIdsForProduct($productId);

        if ($ids === []) {
            return [];
        }

        return $this->categoryRepository->findBy(['id' => $ids])->toArray();
    }

    /**
     * @return array<Product>
     * @throws CategoryNotFoundException
     */
    public function getProductsInCategory(int $categoryId): array
    {
        if ($this->categoryRepository->find($categoryId) === null) {
            throw CategoryNotFoundException::forId($categoryId);
        }

        $ids = $this->productCategoryRepository->findProductIdsForCategory($categoryId);

        if ($ids === []) {
            return [];
        }

        return $this->productRepository->findBy(['id' => $ids])->toArray();
    }
}
