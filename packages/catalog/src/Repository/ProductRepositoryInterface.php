<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Repository;

use Marko\Database\Repository\RepositoryInterface;
use Markommerce\Catalog\Entity\Product;

/**
 * Interface for Product entity repository.
 *
 * @template TEntity of Product
 * @extends RepositoryInterface<TEntity>
 */
interface ProductRepositoryInterface extends RepositoryInterface
{
    public function findBySku(string $sku): ?Product;
}
