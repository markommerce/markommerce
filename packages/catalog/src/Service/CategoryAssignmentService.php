<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Service;

use Marko\Core\Event\EventDispatcherInterface;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\TransactionInterface;
use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Event\ProductAssignedToCategory;
use Markommerce\Catalog\Event\ProductRemovedFromCategory;
use Markommerce\Catalog\Exception\CategoryNotFoundException;
use Markommerce\Catalog\Exception\ProductNotFoundException;
use Markommerce\Catalog\Repository\CategoryRepositoryInterface;
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
        private ConnectionInterface $connection,
        private TransactionInterface $transaction,
        private ?EventDispatcherInterface $eventDispatcher = null,
    ) {}

    /**
     * @throws ProductNotFoundException
     * @throws CategoryNotFoundException
     * @throws Throwable
     */
    public function assign(int $productId, int $categoryId): void
    {
        $product = $this->productRepository->find($productId);

        if ($product === null) {
            throw ProductNotFoundException::forId($productId);
        }

        $category = $this->categoryRepository->find($categoryId);

        if ($category === null) {
            throw CategoryNotFoundException::forId($categoryId);
        }

        $this->transaction->beginTransaction();

        try {
            $rows = $this->connection->query(
                'SELECT 1 FROM product_categories WHERE product_id = ? AND category_id = ? FOR UPDATE',
                [$productId, $categoryId],
            );

            if (count($rows) > 0) {
                $this->transaction->commit();

                return;
            }

            $this->connection->execute(
                'INSERT INTO product_categories (product_id, category_id) VALUES (?, ?)',
                [$productId, $categoryId],
            );

            $this->transaction->commit();
        } catch (Throwable $e) {
            $this->transaction->rollback();
            throw $e;
        }

        $this->eventDispatcher?->dispatch(new ProductAssignedToCategory($productId, $categoryId));
    }

    public function unassign(int $productId, int $categoryId): void
    {
        $affected = $this->connection->execute(
            'DELETE FROM product_categories WHERE product_id = ? AND category_id = ?',
            [$productId, $categoryId],
        );

        if ($affected > 0) {
            $this->eventDispatcher?->dispatch(new ProductRemovedFromCategory($productId, $categoryId));
        }
    }

    /**
     * @return array<Category>
     * @throws ProductNotFoundException
     */
    public function getCategoriesForProduct(int $productId): array
    {
        $product = $this->productRepository->find($productId);

        if ($product === null) {
            throw ProductNotFoundException::forId($productId);
        }

        $rows = $this->connection->query(
            'SELECT category_id FROM product_categories WHERE product_id = ?',
            [$productId],
        );

        $ids = array_map(fn (array $r) => (int) $r['category_id'], $rows);

        if ($ids === []) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn (int $id) => $this->categoryRepository->find($id), $ids),
            fn (?Category $c) => $c !== null,
        ));
    }

    /**
     * @return array<Product>
     * @throws CategoryNotFoundException
     */
    public function getProductsInCategory(int $categoryId): array
    {
        $category = $this->categoryRepository->find($categoryId);

        if ($category === null) {
            throw CategoryNotFoundException::forId($categoryId);
        }

        $rows = $this->connection->query(
            'SELECT product_id FROM product_categories WHERE category_id = ?',
            [$categoryId],
        );

        $ids = array_map(fn (array $r) => (int) $r['product_id'], $rows);

        if ($ids === []) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn (int $id) => $this->productRepository->find($id), $ids),
            fn (?Product $p) => $p !== null,
        ));
    }
}
