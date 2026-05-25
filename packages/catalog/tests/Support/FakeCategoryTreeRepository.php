<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Tests\Support;

use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityCollection;
use Marko\Database\Exceptions\RepositoryException;
use Markommerce\Catalog\Contracts\CategoryTreeRepositoryInterface;
use Markommerce\Catalog\Entity\CategoryTree;
use Markommerce\Catalog\Exceptions\DefaultTreeMissingException;

class FakeCategoryTreeRepository implements CategoryTreeRepositoryInterface
{
    /** @var array<int, CategoryTree> */
    public array $trees = [];

    private int $nextId = 1;

    public function find(int|string $id): ?CategoryTree
    {
        return array_find($this->trees, fn (CategoryTree $t) => $t->id === $id);
    }

    /**
     * @throws RepositoryException
     */
    public function findOrFail(int|string $id): CategoryTree
    {
        $tree = $this->find($id);

        if ($tree === null) {
            throw RepositoryException::entityNotFound(CategoryTree::class, $id);
        }

        return $tree;
    }

    /**
     * @return EntityCollection<CategoryTree>
     */
    public function findAll(): EntityCollection
    {
        return new EntityCollection(array_values($this->trees));
    }

    /**
     * @param array<string, mixed> $criteria
     * @return EntityCollection<CategoryTree>
     */
    public function findBy(array $criteria): EntityCollection
    {
        $matches = array_values(array_filter(
            $this->trees,
            fn (CategoryTree $t) => $this->matchesCriteria($t, $criteria),
        ));

        return new EntityCollection($matches);
    }

    /**
     * @param array<string, mixed> $criteria
     */
    public function findOneBy(array $criteria): ?CategoryTree
    {
        return array_find(
            $this->trees,
            fn (CategoryTree $t) => $this->matchesCriteria($t, $criteria),
        );
    }

    /**
     * @param array<string, mixed> $criteria
     */
    public function existsBy(array $criteria): bool
    {
        return array_any(
            $this->trees,
            fn (CategoryTree $t) => $this->matchesCriteria($t, $criteria),
        );
    }

    /**
     * @throws RepositoryException
     */
    public function save(Entity $entity): void
    {
        if (!$entity instanceof CategoryTree) {
            throw RepositoryException::invalidEntityType(self::class, CategoryTree::class, $entity::class);
        }

        if ($entity->id === null) {
            $entity->id = $this->nextId++;
        }

        $this->trees[$entity->id] = $entity;
    }

    /**
     * @throws RepositoryException
     */
    public function delete(Entity $entity): void
    {
        if (!$entity instanceof CategoryTree) {
            throw RepositoryException::invalidEntityType(self::class, CategoryTree::class, $entity::class);
        }

        if ($entity->id !== null) {
            unset($this->trees[$entity->id]);
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

    public function findByCode(string $code): ?CategoryTree
    {
        return array_find($this->trees, fn (CategoryTree $t) => $t->code === $code);
    }

    /**
     * @throws DefaultTreeMissingException
     */
    public function findDefault(): CategoryTree
    {
        $tree = array_find($this->trees, fn (CategoryTree $t) => $t->isDefault);

        if ($tree === null) {
            throw DefaultTreeMissingException::forResolution();
        }

        return $tree;
    }

    /**
     * @param array<string, mixed> $criteria
     */
    private function matchesCriteria(CategoryTree $tree, array $criteria): bool
    {
        return array_all(
            array_keys($criteria),
            fn (string $key) => $tree->$key === $criteria[$key],
        );
    }
}
