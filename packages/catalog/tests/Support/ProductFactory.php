<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Tests\Support;

use Markommerce\Catalog\Contracts\ProductCategoryAssignmentRepositoryInterface;
use Markommerce\Catalog\Contracts\ProductRepositoryInterface;
use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Entity\ProductCategoryAssignment;
use Markommerce\CatalogPriceIndex\Contracts\ProductPriceIndexRepositoryInterface;
use Markommerce\CatalogPriceIndex\Entity\ProductPriceIndexEntry;
use Markommerce\Testing\Fixtures\Exceptions\MissingModuleException;
use Markommerce\Testing\Fixtures\FixtureFactory;
use Markommerce\Testing\Profile\BootedStore;

class ProductFactory extends FixtureFactory
{
    private string $sku = '';

    private string $name = '';

    private ?string $price = null;

    private ?string $indexedPrice = null;

    private ?Category $category = null;

    private function __construct(BootedStore $store)
    {
        parent::__construct($store);
    }

    public static function new(BootedStore $store): self
    {
        return new self($store);
    }

    public function withSku(string $sku): self
    {
        $clone = clone $this;
        $clone->sku = $sku;

        return $clone;
    }

    public function withName(string $name): self
    {
        $clone = clone $this;
        $clone->name = $name;

        return $clone;
    }

    public function withPrice(string $price): self
    {
        $clone = clone $this;
        $clone->price = $price;

        return $clone;
    }

    /**
     * Set an indexed price for the product.
     *
     * Requires the price-index module to be loaded in the store profile.
     *
     * @throws MissingModuleException when the price-index module is not loaded
     */
    public function withIndexedPrice(string $price): self
    {
        $clone = clone $this;
        $clone->indexedPrice = $price;

        return $clone;
    }

    public function inCategory(Category $category): self
    {
        $clone = clone $this;
        $clone->category = $category;

        return $clone;
    }

    /**
     * @throws MissingModuleException when indexed price is set but the price-index module is not loaded
     */
    public function create(): Product
    {
        $counter = self::nextCounter();

        $product = new Product();
        $product->sku = $this->sku !== '' ? $this->sku : 'PRODUCT-' . str_pad((string) $counter, 6, '0', STR_PAD_LEFT);
        $product->name = $this->name !== '' ? $this->name : 'Product ' . $counter;
        $product->priceAmount = $this->price;

        /** @var ProductRepositoryInterface $repo */
        $repo = $this->store->get(ProductRepositoryInterface::class);
        $repo->save($product);

        if ($this->category !== null) {
            $assignment = new ProductCategoryAssignment();
            $assignment->productId = $product->id;
            $assignment->categoryId = $this->category->id;

            /** @var ProductCategoryAssignmentRepositoryInterface $assignmentRepo */
            $assignmentRepo = $this->store->get(ProductCategoryAssignmentRepositoryInterface::class);
            $assignmentRepo->save($assignment);
        }

        if ($this->indexedPrice !== null) {
            $this->writeIndexedPrice($product, $this->indexedPrice);
        }

        return $product;
    }

    /**
     * @throws MissingModuleException when the price-index repository is not bound in the container
     */
    private function writeIndexedPrice(
        Product $product,
        string $price,
    ): void {
        $repositoryInterface = 'Markommerce\\CatalogPriceIndex\\Contracts\\ProductPriceIndexRepositoryInterface';

        if (!$this->store->container()->has($repositoryInterface)) {
            throw MissingModuleException::forModule(
                'markommerce/catalog-price-index',
                'withIndexedPrice()',
            );
        }

        /** @var ProductPriceIndexRepositoryInterface $indexRepo */
        $indexRepo = $this->store->get($repositoryInterface);

        $entry = new ProductPriceIndexEntry();
        $entry->productId = (int) $product->id;
        $entry->amount = $price;
        $entry->currencyCode = 'USD';

        $indexRepo->upsertMany([$entry]);
    }
}
