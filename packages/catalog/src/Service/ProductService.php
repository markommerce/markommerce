<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Service;

use Marko\Core\Event\EventDispatcherInterface;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Event\ProductCreated;
use Markommerce\Catalog\Event\ProductDeleted;
use Markommerce\Catalog\Event\ProductUpdated;
use Markommerce\Catalog\Exception\DuplicateSkuException;
use Markommerce\Catalog\Exception\InvalidProductDataException;
use Markommerce\Catalog\Exception\ProductNotFoundException;
use Markommerce\Catalog\Repository\ProductRepositoryInterface;
use Markommerce\Money\CurrencyConfigInterface;
use Markommerce\Money\MoneyInterface;

class ProductService implements ProductServiceInterface
{
    /**
     * @param ProductRepositoryInterface<Product> $productRepository
     */
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private CurrencyConfigInterface $currencyConfig,
        private ?EventDispatcherInterface $eventDispatcher = null,
    ) {}

    /**
     * @throws InvalidProductDataException
     * @throws DuplicateSkuException
     */
    public function create(string $sku, string $name, MoneyInterface $basePrice): Product
    {
        if (trim($sku) === '') {
            throw InvalidProductDataException::emptySku();
        }

        if (trim($name) === '') {
            throw InvalidProductDataException::emptyName();
        }

        if ($basePrice->currency() !== $this->currencyConfig->getDefault()) {
            throw InvalidProductDataException::currencyMismatch(
                $this->currencyConfig->getDefault(),
                $basePrice->currency(),
            );
        }

        if ($basePrice->amount() < 0) {
            throw InvalidProductDataException::negativeBasePrice($basePrice->amount());
        }

        if ($this->productRepository->findBySku($sku) !== null) {
            throw DuplicateSkuException::forSku($sku);
        }

        $product = new Product();
        $product->sku = $sku;
        $product->name = $name;
        $product->basePriceAmount = $basePrice->amount();

        $this->productRepository->save($product);

        $this->eventDispatcher?->dispatch(new ProductCreated($product));

        return $product;
    }

    public function get(int $id): ?Product
    {
        return $this->productRepository->find($id);
    }

    public function getBySku(string $sku): ?Product
    {
        return $this->productRepository->findBySku($sku);
    }

    /**
     * @throws InvalidProductDataException
     * @throws DuplicateSkuException
     */
    public function update(Product $product): Product
    {
        if (trim($product->sku) === '') {
            throw InvalidProductDataException::emptySku();
        }

        if (trim($product->name) === '') {
            throw InvalidProductDataException::emptyName();
        }

        if ($product->basePriceAmount < 0) {
            throw InvalidProductDataException::negativeBasePrice($product->basePriceAmount);
        }

        $existing = $this->productRepository->findBySku($product->sku);

        if ($existing !== null && $existing->id !== $product->id) {
            throw DuplicateSkuException::forSku($product->sku);
        }

        $this->productRepository->save($product);

        $this->eventDispatcher?->dispatch(new ProductUpdated($product));

        return $product;
    }

    /** @throws ProductNotFoundException */
    public function delete(int $id): void
    {
        $product = $this->productRepository->find($id);

        if ($product === null) {
            throw ProductNotFoundException::forId($id);
        }

        $this->productRepository->delete($product);

        $this->eventDispatcher?->dispatch(new ProductDeleted($id));
    }

    /** @return array<Product> */
    public function list(): array
    {
        return $this->productRepository->findAll()->toArray();
    }
}
