<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Repositories;

use Marko\Database\Entity\Entity;
use Marko\Database\Exceptions\RepositoryException;
use Marko\Database\Repository\Repository;
use Markommerce\Catalog\Contracts\CategoryTreeMarketAssignmentRepositoryInterface;
use Markommerce\Catalog\Entity\CategoryTreeMarketAssignment;

/**
 * Concrete repository for CategoryTreeMarketAssignment entities.
 *
 * Upsert strategy: PostgreSQL `INSERT INTO … ON CONFLICT (market) DO UPDATE SET …`.
 *
 * The entity uses a string primary key (`market`) rather than an auto-increment
 * integer. The base Repository::save() delegates to EntityHydrator::isNew(), which
 * for non-auto-increment PKs returns `true` unless the entity has a registered
 * originalValues snapshot (i.e. it was previously hydrated or saved through this
 * repository). This means two separate `new CategoryTreeMarketAssignment()` objects
 * with the same `market` value would both be treated as new, causing a duplicate-key
 * error on the second INSERT.
 *
 * To provide safe upsert semantics, save() is overridden to emit a single
 * `INSERT … ON CONFLICT (market) DO UPDATE SET tree_id = EXCLUDED.tree_id`
 * statement, which is idempotent and never throws a duplicate-key error.
 *
 * @extends Repository<CategoryTreeMarketAssignment>
 */
class CategoryTreeMarketAssignmentRepository extends Repository implements CategoryTreeMarketAssignmentRepositoryInterface
{
    protected const string ENTITY_CLASS = CategoryTreeMarketAssignment::class;

    /**
     * Upsert the assignment keyed by market using PostgreSQL ON CONFLICT.
     *
     * @throws RepositoryException
     */
    public function save(Entity $entity): void
    {
        /** @var CategoryTreeMarketAssignment $entity */
        $data = $this->hydrator->extract($entity, $this->metadata);

        $sql = sprintf(
            'INSERT INTO %s (market, tree_id) VALUES (?, ?) ON CONFLICT (market) DO UPDATE SET tree_id = EXCLUDED.tree_id',
            $this->metadata->tableName,
        );

        $this->connection->execute($sql, [$data['market'], $data['tree_id']]);

        $this->hydrator->registerOriginalValues($entity, $this->metadata);
    }

    /**
     * @throws RepositoryException
     */
    public function findByMarket(string $market): ?CategoryTreeMarketAssignment
    {
        /** @var CategoryTreeMarketAssignment|null */
        return $this->findOneBy(['market' => $market]);
    }

    /**
     * @return list<CategoryTreeMarketAssignment>
     * @throws RepositoryException
     */
    public function findByTree(int $treeId): array
    {
        /** @var list<CategoryTreeMarketAssignment> */
        return $this->findBy(['treeId' => $treeId])->toArray();
    }
}
