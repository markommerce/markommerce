<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Tests\Support;

use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityCollection;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Repository\ProductRepositoryInterface;

/**
 * @implements ProductRepositoryInterface<Product>
 */
class FakeProductRepository implements ProductRepositoryInterface
{
    /** @var array<Product> */
    public array $products = [];

    public ?Product $saved = null;

    public ?Product $deleted = null;

    public function find(int|string $id): ?Product
    {
        return array_find($this->products, fn (Product $p) => $p->id === $id);
    }

    public function findOrFail(int|string $id): Product
    {
        $product = $this->find($id);

        if ($product === null) {
            throw new \RuntimeException("Product {$id} not found");
        }

        return $product;
    }

    /** @return EntityCollection<Product> */
    public function findAll(): EntityCollection
    {
        return new EntityCollection($this->products);
    }

    /**
     * @param array<string, mixed> $criteria
     * @return EntityCollection<Product>
     */
    public function findBy(array $criteria): EntityCollection
    {
        if (isset($criteria['id']) && is_array($criteria['id'])) {
            $ids = $criteria['id'];

            return new EntityCollection(array_values(array_filter(
                $this->products,
                fn (Product $p) => in_array($p->id, $ids, true),
            )));
        }

        return new EntityCollection($this->products);
    }

    /**
     * @param array<string, mixed> $criteria
     */
    public function findOneBy(array $criteria): ?Entity
    {
        return null;
    }

    /**
     * @param array<string, mixed> $criteria
     */
    public function existsBy(array $criteria): bool
    {
        return false;
    }

    public function save(Entity $entity): void
    {
        if ($entity instanceof Product) {
            $entity->id = $entity->id ?? (count($this->products) + 1);
            $this->saved = $entity;
            $this->products[] = $entity;
        }
    }

    public function delete(Entity $entity): void
    {
        if ($entity instanceof Product) {
            $this->deleted = $entity;
            $this->products = array_values(array_filter(
                $this->products,
                fn (Product $p) => $p->id !== $entity->id,
            ));
        }
    }

    /** @param array<Entity> $entities */
    public function insertBatch(array $entities): void {}

    public function findBySku(string $sku): ?Product
    {
        return array_find($this->products, fn (Product $p) => $p->sku === $sku);
    }
}
