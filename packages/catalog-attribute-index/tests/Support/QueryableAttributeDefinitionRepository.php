<?php

declare(strict_types=1);

namespace Markommerce\CatalogAttributeIndex\Tests\Support;

use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityCollection;
use Marko\Database\Exceptions\RepositoryException;
use Markommerce\Attribute\Contracts\AttributeDefinitionRepositoryInterface;
use Markommerce\Attribute\Entity\AttributeDefinition;
use Markommerce\Attribute\Entity\AttributeOption;

/**
 * In-memory fake that extends FakeAttributeDefinitionRepository with a working query() method,
 * so the AttributeIndexer can call query()->where(...)->getEntities() in unit tests.
 */
class QueryableAttributeDefinitionRepository implements AttributeDefinitionRepositoryInterface
{
    /** @var array<int, AttributeDefinition> */
    public array $definitions = [];

    /** @var array<int, AttributeOption> */
    public array $options = [];

    private int $nextDefinitionId = 1;

    private int $nextOptionId = 1;

    public function find(int|string $id): ?AttributeDefinition
    {
        return array_find($this->definitions, fn (AttributeDefinition $d) => $d->id === $id);
    }

    /**
     * @throws RepositoryException
     */
    public function findOrFail(int|string $id): AttributeDefinition
    {
        $definition = $this->find($id);

        if ($definition === null) {
            throw RepositoryException::entityNotFound(AttributeDefinition::class, $id);
        }

        return $definition;
    }

    /** @return EntityCollection<AttributeDefinition> */
    public function findAll(): EntityCollection
    {
        return new EntityCollection(array_values($this->definitions));
    }

    /**
     * @param array<string, mixed> $criteria
     * @return EntityCollection<AttributeDefinition>
     */
    public function findBy(array $criteria): EntityCollection
    {
        $matches = array_values(array_filter(
            $this->definitions,
            fn (AttributeDefinition $d) => $this->matchesCriteria($d, $criteria),
        ));

        return new EntityCollection($matches);
    }

    /**
     * @param array<string, mixed> $criteria
     */
    public function findOneBy(array $criteria): ?AttributeDefinition
    {
        return array_find(
            $this->definitions,
            fn (AttributeDefinition $d) => $this->matchesCriteria($d, $criteria),
        );
    }

    /**
     * @param array<string, mixed> $criteria
     */
    public function existsBy(array $criteria): bool
    {
        return array_any(
            $this->definitions,
            fn (AttributeDefinition $d) => $this->matchesCriteria($d, $criteria),
        );
    }

    /**
     * @throws RepositoryException
     */
    public function save(Entity $entity): void
    {
        if (!$entity instanceof AttributeDefinition) {
            throw RepositoryException::invalidEntityType(self::class, AttributeDefinition::class, $entity::class);
        }

        if ($entity->id === null) {
            $entity->id = $this->nextDefinitionId++;
        }

        $this->definitions[$entity->id] = $entity;
    }

    /**
     * @throws RepositoryException
     */
    public function delete(Entity $entity): void
    {
        if (!$entity instanceof AttributeDefinition) {
            throw RepositoryException::invalidEntityType(self::class, AttributeDefinition::class, $entity::class);
        }

        if ($entity->id !== null) {
            unset($this->definitions[$entity->id]);
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

    public function findByCode(
        string $entityType,
        string $code,
    ): ?AttributeDefinition {
        return array_find(
            $this->definitions,
            fn (AttributeDefinition $d) => $d->entityType === $entityType && $d->code === $code,
        );
    }

    /** @return list<AttributeOption> */
    public function optionsFor(AttributeDefinition $definition): array
    {
        return array_values(array_filter(
            $this->options,
            fn (AttributeOption $o) => $o->attributeId === $definition->id,
        ));
    }

    public function saveOption(AttributeOption $option): void
    {
        if ($option->id === null) {
            $option->id = $this->nextOptionId++;
        }

        $this->options[$option->id] = $option;
    }

    public function deleteOptionsFor(AttributeDefinition $definition): void
    {
        $this->options = array_values(array_filter(
            $this->options,
            fn (AttributeOption $o) => $o->attributeId !== $definition->id,
        ));
    }

    public function query(): FakeDefQueryBuilder
    {
        return new FakeDefQueryBuilder(array_values($this->definitions));
    }

    /**
     * @param array<string, mixed> $criteria
     */
    private function matchesCriteria(
        AttributeDefinition $definition,
        array $criteria,
    ): bool {
        return array_all(
            array_keys($criteria),
            fn (string $key) => $definition->$key === $criteria[$key],
        );
    }
}
