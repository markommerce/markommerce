<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Tests\Support;

use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityCollection;
use Marko\Database\Exceptions\RepositoryException;
use Markommerce\Catalog\Contracts\ProductRepositoryInterface;
use Markommerce\Catalog\Entity\Product;

class FakeProductRepository implements ProductRepositoryInterface
{
    /** @var array<int, Product> */
    public array $products = [];

    private int $nextId = 1;

    public function find(int|string $id): ?Product
    {
        return array_find($this->products, fn (Product $p) => $p->id === $id);
    }

    /**
     * @throws RepositoryException
     */
    public function findOrFail(int|string $id): Product
    {
        $product = $this->find($id);

        if ($product === null) {
            throw RepositoryException::entityNotFound(Product::class, $id);
        }

        return $product;
    }

    /**
     * @return EntityCollection<Product>
     */
    public function findAll(): EntityCollection
    {
        return new EntityCollection(array_values($this->products));
    }

    /**
     * @param array<string, mixed> $criteria
     * @return EntityCollection<Product>
     */
    public function findBy(array $criteria): EntityCollection
    {
        $matches = array_values(array_filter(
            $this->products,
            fn (Product $p) => $this->matchesCriteria($p, $criteria),
        ));

        return new EntityCollection($matches);
    }

    /**
     * @param array<string, mixed> $criteria
     */
    public function findOneBy(array $criteria): ?Product
    {
        return array_find(
            $this->products,
            fn (Product $p) => $this->matchesCriteria($p, $criteria),
        );
    }

    /**
     * @param array<string, mixed> $criteria
     */
    public function existsBy(array $criteria): bool
    {
        return array_any(
            $this->products,
            fn (Product $p) => $this->matchesCriteria($p, $criteria),
        );
    }

    /**
     * @throws RepositoryException
     */
    public function save(Entity $entity): void
    {
        if (!$entity instanceof Product) {
            throw RepositoryException::invalidEntityType(self::class, Product::class, $entity::class);
        }

        if ($entity->id === null) {
            $entity->id = $this->nextId++;
        }

        $this->products[$entity->id] = $entity;
    }

    /**
     * @throws RepositoryException
     */
    public function delete(Entity $entity): void
    {
        if (!$entity instanceof Product) {
            throw RepositoryException::invalidEntityType(self::class, Product::class, $entity::class);
        }

        if ($entity->id !== null) {
            unset($this->products[$entity->id]);
        }
    }

    /**
     * @param array<Entity> $entities
     * @throws RepositoryException
     */
    public function insertBatch(array $entities): void
    {
        foreach ($entities as $entity) {
            $this->save($entity);
        }
    }

    public function findBySku(string $sku): ?Product
    {
        return array_find($this->products, fn (Product $p) => $p->sku === $sku);
    }

    /**
     * @param array<string, mixed> $criteria
     */
    private function matchesCriteria(
        Product $product,
        array $criteria,
    ): bool {
        return array_all(
            array_keys($criteria),
            fn (string $key) => $product->$key === $criteria[$key],
        );
    }
}
