<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Contracts;

use Marko\Database\Repository\RepositoryInterface;
use Marko\Database\Repository\RepositoryQueryBuilder;
use Markommerce\Catalog\Entity\Product;

/**
 * @extends RepositoryInterface<Product>
 */
interface ProductRepositoryInterface extends RepositoryInterface
{
    public function findBySku(string $sku): ?Product;

    /**
     * Create a query builder scoped to the catalog_products table.
     * Enables callers to perform joins and advanced filtering without N+1 lookups.
     */
    public function query(): RepositoryQueryBuilder;
}
