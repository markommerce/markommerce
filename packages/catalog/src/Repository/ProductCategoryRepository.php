<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Repository;

use Marko\Database\Connection\TransactionInterface;
use Marko\Database\Repository\Repository;
use Markommerce\Catalog\Entity\ProductCategory;

/** @extends Repository<ProductCategory> */
class ProductCategoryRepository extends Repository implements ProductCategoryRepositoryInterface
{
    protected const string ENTITY_CLASS = ProductCategory::class;

    /**
     * @throws \Throwable
     */
    public function assign(int $productId, int $categoryId): bool
    {
        $ownsTx = false;
        if ($this->connection instanceof TransactionInterface && !$this->connection->inTransaction()) {
            $this->connection->beginTransaction();
            $ownsTx = true;
        }
        try {
            $rows = $this->connection->query(
                'SELECT 1 FROM product_categories WHERE product_id = ? AND category_id = ? FOR UPDATE',
                [$productId, $categoryId],
            );
            if (count($rows) > 0) {
                if ($ownsTx) {
                    $this->connection->commit();
                }
                return false;
            }
            $this->connection->execute(
                'INSERT INTO product_categories (product_id, category_id) VALUES (?, ?)',
                [$productId, $categoryId],
            );
            if ($ownsTx) {
                $this->connection->commit();
            }
            return true;
        } catch (\Throwable $e) {
            if ($ownsTx) {
                $this->connection->rollback();
            }
            throw $e;
        }
    }

    public function unassign(int $productId, int $categoryId): bool
    {
        $affected = $this->connection->execute(
            'DELETE FROM product_categories WHERE product_id = ? AND category_id = ?',
            [$productId, $categoryId],
        );
        return $affected > 0;
    }

    /** @return array<int> */
    public function findCategoryIdsForProduct(int $productId): array
    {
        $rows = $this->connection->query(
            'SELECT category_id FROM product_categories WHERE product_id = ?',
            [$productId],
        );
        return array_map(fn (array $r): int => (int) $r['category_id'], $rows);
    }

    /** @return array<int> */
    public function findProductIdsForCategory(int $categoryId): array
    {
        $rows = $this->connection->query(
            'SELECT product_id FROM product_categories WHERE category_id = ?',
            [$categoryId],
        );
        return array_map(fn (array $r): int => (int) $r['product_id'], $rows);
    }
}
