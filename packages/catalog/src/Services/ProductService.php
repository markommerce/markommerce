<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Services;

use Marko\Database\Exceptions\RepositoryException;
use Markommerce\Catalog\Contracts\ProductRepositoryInterface;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Exceptions\DuplicateSkuException;
use Markommerce\Catalog\Exceptions\ProductNotFoundException;

class ProductService
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
    ) {}

    /**
     * @throws DuplicateSkuException
     */
    public function createProduct(
        string $sku,
        string $name,
        ?string $description = null,
    ): Product {
        $existing = $this->productRepository->findBySku($sku);

        if ($existing !== null) {
            throw DuplicateSkuException::forSku($sku);
        }

        $product = new Product();
        $product->sku = $sku;
        $product->name = $name;
        $product->description = $description;

        try {
            $this->productRepository->save($product);
        } catch (RepositoryException $e) {
            throw DuplicateSkuException::forSku($sku);
        }

        return $product;
    }

    /**
     * @throws ProductNotFoundException
     */
    public function getProduct(int $id): Product
    {
        $product = $this->productRepository->find($id);

        if ($product === null) {
            throw ProductNotFoundException::forId($id);
        }

        return $product;
    }
}
