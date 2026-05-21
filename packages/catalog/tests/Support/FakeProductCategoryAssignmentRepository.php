<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Tests\Support;

use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityCollection;
use Marko\Database\Exceptions\RepositoryException;
use Markommerce\Catalog\Contracts\ProductCategoryAssignmentRepositoryInterface;
use Markommerce\Catalog\Entity\ProductCategoryAssignment;

class FakeProductCategoryAssignmentRepository implements ProductCategoryAssignmentRepositoryInterface
{
    /** @var array<int, ProductCategoryAssignment> */
    public array $assignments = [];

    private int $nextId = 1;

    public function find(int|string $id): ?ProductCategoryAssignment
    {
        return array_find($this->assignments, fn (ProductCategoryAssignment $a) => $a->id === $id);
    }

    /**
     * @throws RepositoryException
     */
    public function findOrFail(int|string $id): ProductCategoryAssignment
    {
        $assignment = $this->find($id);

        if ($assignment === null) {
            throw RepositoryException::entityNotFound(ProductCategoryAssignment::class, $id);
        }

        return $assignment;
    }

    /**
     * @return EntityCollection<ProductCategoryAssignment>
     */
    public function findAll(): EntityCollection
    {
        return new EntityCollection(array_values($this->assignments));
    }

    /**
     * @param array<string, mixed> $criteria
     * @return EntityCollection<ProductCategoryAssignment>
     */
    public function findBy(array $criteria): EntityCollection
    {
        $matches = array_values(array_filter(
            $this->assignments,
            fn (ProductCategoryAssignment $a) => $this->matchesCriteria($a, $criteria),
        ));

        return new EntityCollection($matches);
    }

    /**
     * @param array<string, mixed> $criteria
     */
    public function findOneBy(array $criteria): ?ProductCategoryAssignment
    {
        return array_find(
            $this->assignments,
            fn (ProductCategoryAssignment $a) => $this->matchesCriteria($a, $criteria),
        );
    }

    /**
     * @param array<string, mixed> $criteria
     */
    public function existsBy(array $criteria): bool
    {
        return array_any(
            $this->assignments,
            fn (ProductCategoryAssignment $a) => $this->matchesCriteria($a, $criteria),
        );
    }

    /**
     * @throws RepositoryException
     */
    public function save(Entity $entity): void
    {
        if (!$entity instanceof ProductCategoryAssignment) {
            throw RepositoryException::invalidEntityType(self::class, ProductCategoryAssignment::class, $entity::class);
        }

        if ($entity->id === null) {
            $entity->id = $this->nextId++;
        }

        $this->assignments[$entity->id] = $entity;
    }

    /**
     * @throws RepositoryException
     */
    public function delete(Entity $entity): void
    {
        if (!$entity instanceof ProductCategoryAssignment) {
            throw RepositoryException::invalidEntityType(self::class, ProductCategoryAssignment::class, $entity::class);
        }

        if ($entity->id !== null) {
            unset($this->assignments[$entity->id]);
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

    /**
     * @return array<ProductCategoryAssignment>
     */
    public function findByCategory(int $categoryId): array
    {
        return array_values(array_filter(
            $this->assignments,
            fn (ProductCategoryAssignment $a) => $a->categoryId === $categoryId,
        ));
    }

    public function findByProductAndCategory(int $productId, int $categoryId): ?ProductCategoryAssignment
    {
        return array_find(
            $this->assignments,
            fn (ProductCategoryAssignment $a) => $a->productId === $productId && $a->categoryId === $categoryId,
        );
    }

    /**
     * @param array<string, mixed> $criteria
     */
    private function matchesCriteria(ProductCategoryAssignment $assignment, array $criteria): bool
    {
        return array_all(
            array_keys($criteria),
            fn (string $key) => $assignment->$key === $criteria[$key],
        );
    }
}
