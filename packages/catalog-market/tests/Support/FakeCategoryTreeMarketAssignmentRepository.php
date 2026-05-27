<?php

declare(strict_types=1);

namespace Markommerce\CatalogMarket\Tests\Support;

use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityCollection;
use Marko\Database\Exceptions\RepositoryException;
use Markommerce\CatalogMarket\Contracts\CategoryTreeMarketAssignmentRepositoryInterface;
use Markommerce\CatalogMarket\Entity\CategoryTreeMarketAssignment;

/**
 * In-memory fake repository for CategoryTreeMarketAssignment.
 *
 * Storage is keyed by `market` string (the PK column). Because `market` is a
 * string primary key rather than an auto-increment integer, the framework's
 * default `isNew` heuristic does not apply. `save()` performs a simple
 * assign-by-PK upsert: any existing assignment for the same market is
 * silently replaced.
 */
class FakeCategoryTreeMarketAssignmentRepository implements CategoryTreeMarketAssignmentRepositoryInterface
{
    /** @var array<string, CategoryTreeMarketAssignment> */
    public array $byMarket = [];

    /**
     * Find an assignment by its market PK.
     */
    public function find(int|string $id): ?CategoryTreeMarketAssignment
    {
        if (!is_string($id)) {
            return null;
        }

        return $this->byMarket[$id] ?? null;
    }

    /**
     * @throws RepositoryException
     */
    public function findOrFail(int|string $id): CategoryTreeMarketAssignment
    {
        $assignment = $this->find($id);

        if ($assignment === null) {
            throw RepositoryException::entityNotFound(CategoryTreeMarketAssignment::class, $id);
        }

        return $assignment;
    }

    /**
     * @return EntityCollection<CategoryTreeMarketAssignment>
     */
    public function findAll(): EntityCollection
    {
        return new EntityCollection(array_values($this->byMarket));
    }

    /**
     * @param array<string, mixed> $criteria
     * @return EntityCollection<CategoryTreeMarketAssignment>
     */
    public function findBy(array $criteria): EntityCollection
    {
        $matches = array_values(array_filter(
            $this->byMarket,
            fn (CategoryTreeMarketAssignment $a) => $this->matchesCriteria($a, $criteria),
        ));

        return new EntityCollection($matches);
    }

    /**
     * @param array<string, mixed> $criteria
     */
    public function findOneBy(array $criteria): ?CategoryTreeMarketAssignment
    {
        return array_find(
            $this->byMarket,
            fn (CategoryTreeMarketAssignment $a) => $this->matchesCriteria($a, $criteria),
        );
    }

    /**
     * @param array<string, mixed> $criteria
     */
    public function existsBy(array $criteria): bool
    {
        return array_any(
            $this->byMarket,
            fn (CategoryTreeMarketAssignment $a) => $this->matchesCriteria($a, $criteria),
        );
    }

    /**
     * Upsert: assigns the entity by market PK, replacing any existing entry
     * for the same market.
     *
     * @throws RepositoryException
     */
    public function save(Entity $entity): void
    {
        if (!$entity instanceof CategoryTreeMarketAssignment) {
            throw RepositoryException::invalidEntityType(
                self::class,
                CategoryTreeMarketAssignment::class,
                $entity::class,
            );
        }

        $this->byMarket[$entity->market] = $entity;
    }

    /**
     * @throws RepositoryException
     */
    public function delete(Entity $entity): void
    {
        if (!$entity instanceof CategoryTreeMarketAssignment) {
            throw RepositoryException::invalidEntityType(
                self::class,
                CategoryTreeMarketAssignment::class,
                $entity::class,
            );
        }

        unset($this->byMarket[$entity->market]);
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

    public function findByMarket(string $market): ?CategoryTreeMarketAssignment
    {
        return $this->byMarket[$market] ?? null;
    }

    /**
     * @return list<CategoryTreeMarketAssignment>
     */
    public function findByTree(int $treeId): array
    {
        return array_values(array_filter(
            $this->byMarket,
            fn (CategoryTreeMarketAssignment $a) => $a->treeId === $treeId,
        ));
    }

    /**
     * @param array<string, mixed> $criteria
     */
    private function matchesCriteria(
        CategoryTreeMarketAssignment $assignment,
        array $criteria,
    ): bool {
        return array_all(
            array_keys($criteria),
            fn (string $key) => $assignment->$key === $criteria[$key],
        );
    }
}
