<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Tests\Support;

use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityCollection;
use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Repository\CategoryRepositoryInterface;

/**
 * @implements CategoryRepositoryInterface<Category>
 */
class FakeCategoryRepository implements CategoryRepositoryInterface
{
    /** @var array<Category> */
    public array $categories = [];

    public ?Category $saved = null;

    public ?Category $deleted = null;

    public function find(int|string $id): ?Category
    {
        return array_find($this->categories, fn (Category $c) => $c->id === $id);
    }

    public function findOrFail(int|string $id): Category
    {
        $category = $this->find($id);

        if ($category === null) {
            throw new \RuntimeException("Category {$id} not found");
        }

        return $category;
    }

    /** @return EntityCollection<Category> */
    public function findAll(): EntityCollection
    {
        return new EntityCollection($this->categories);
    }

    /**
     * @param array<string, mixed> $criteria
     * @return EntityCollection<Category>
     */
    public function findBy(array $criteria): EntityCollection
    {
        return new EntityCollection($this->categories);
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
        if ($entity instanceof Category) {
            $entity->id = $entity->id ?? (count($this->categories) + 1);
            $this->saved = $entity;
            $this->categories[] = $entity;
        }
    }

    public function delete(Entity $entity): void
    {
        if ($entity instanceof Category) {
            $this->deleted = $entity;
            $this->categories = array_values(array_filter(
                $this->categories,
                fn (Category $c) => $c->id !== $entity->id,
            ));
        }
    }

    /** @param array<Entity> $entities */
    public function insertBatch(array $entities): void {}
}
