<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Repositories;

use Marko\Database\Exceptions\RepositoryException;
use Marko\Database\Repository\Repository;
use Markommerce\Catalog\Contracts\ProductRepositoryInterface;
use Markommerce\Catalog\Entity\Product;

class ProductRepository extends Repository implements ProductRepositoryInterface
{
    protected const string ENTITY_CLASS = Product::class;

    /**
     * Find a product by its SKU.
     *
     * @throws RepositoryException
     */
    public function findBySku(string $sku): ?Product
    {
        /** @var Product|null */
        return $this->findOneBy(['sku' => $sku]);
    }
}
