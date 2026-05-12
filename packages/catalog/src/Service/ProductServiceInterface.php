<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Service;

use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Exception\DuplicateSkuException;
use Markommerce\Catalog\Exception\InvalidProductDataException;
use Markommerce\Catalog\Exception\ProductNotFoundException;
use Markommerce\Money\MoneyInterface;

interface ProductServiceInterface
{
    /**
     * @throws InvalidProductDataException
     * @throws DuplicateSkuException
     */
    public function create(string $sku, string $name, MoneyInterface $basePrice): Product;

    public function get(int $id): ?Product;

    public function getBySku(string $sku): ?Product;

    /**
     * @throws InvalidProductDataException
     * @throws DuplicateSkuException
     */
    public function update(Product $product): Product;

    /** @throws ProductNotFoundException */
    public function delete(int $id): void;

    /** @return array<Product> */
    public function list(): array;
}
