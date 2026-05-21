<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Tests\Support;

use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityCollection;
use Marko\Database\Exceptions\RepositoryException;
use Markommerce\Catalog\Contracts\CategoryRepositoryInterface;
use Markommerce\Catalog\Entity\Category;

class FakeCategoryRepository implements CategoryRepositoryInterface
{
    /** @var array<int, Category> */
    public array $categories = [];

    private int $nextId = 1;

    public function find(int|string $id): ?Category
    {
        return array_find($this->categories, fn (Category $c) => $c->id === $id);
    }

    /**
     * @throws RepositoryException
     */
    public function findOrFail(int|string $id): Category
    {
        $category = $this->find($id);

        if ($category === null) {
            throw RepositoryException::entityNotFound(Category::class, $id);
        }

        return $category;
    }

    /**
     * @return EntityCollection<Category>
     */
    public function findAll(): EntityCollection
    {
        return new EntityCollection(array_values($this->categories));
    }

    /**
     * @param array<string, mixed> $criteria
     * @return EntityCollection<Category>
     */
    public function findBy(array $criteria): EntityCollection
    {
        $matches = array_values(array_filter(
            $this->categories,
            fn (Category $c) => $this->matchesCriteria($c, $criteria),
        ));

        return new EntityCollection($matches);
    }

    /**
     * @param array<string, mixed> $criteria
     */
    public function findOneBy(array $criteria): ?Category
    {
        return array_find(
            $this->categories,
            fn (Category $c) => $this->matchesCriteria($c, $criteria),
        );
    }

    /**
     * @param array<string, mixed> $criteria
     */
    public function existsBy(array $criteria): bool
    {
        return array_any(
            $this->categories,
            fn (Category $c) => $this->matchesCriteria($c, $criteria),
        );
    }

    /**
     * @throws RepositoryException
     */
    public function save(Entity $entity): void
    {
        if (!$entity instanceof Category) {
            throw RepositoryException::invalidEntityType(self::class, Category::class, $entity::class);
        }

        if ($entity->id === null) {
            $entity->id = $this->nextId++;
        }

        $this->categories[$entity->id] = $entity;
    }

    /**
     * @throws RepositoryException
     */
    public function delete(Entity $entity): void
    {
        if (!$entity instanceof Category) {
            throw RepositoryException::invalidEntityType(self::class, Category::class, $entity::class);
        }

        if ($entity->id !== null) {
            unset($this->categories[$entity->id]);
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
     * @param array<string, mixed> $criteria
     */
    private function matchesCriteria(Category $category, array $criteria): bool
    {
        return array_all(
            array_keys($criteria),
            fn (string $key) => $category->$key === $criteria[$key],
        );
    }
}
