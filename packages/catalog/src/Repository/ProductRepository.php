<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Repository;

use Marko\Database\Repository\Repository;
use Markommerce\Catalog\Entity\Product;

/**
 * @extends Repository<Product>
 * @implements ProductRepositoryInterface<Product>
 */
class ProductRepository extends Repository implements ProductRepositoryInterface
{
    protected const string ENTITY_CLASS = Product::class;

    public function findBySku(string $sku): ?Product
    {
        return $this->findOneBy(['sku' => $sku]);
    }
}
