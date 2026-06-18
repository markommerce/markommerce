<?php

declare(strict_types=1);

namespace Markommerce\Attribute\PgSql;

use Marko\Core\Event\EventDispatcherInterface;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Exceptions\RepositoryException;
use Marko\Database\Query\QueryBuilderFactoryInterface;
use Marko\Database\Repository\Repository;
use Markommerce\Attribute\Contracts\AttributeDefinitionRepositoryInterface;
use Markommerce\Attribute\Entity\AttributeDefinition;
use Markommerce\Attribute\Entity\AttributeOption;

/**
 * @extends Repository<AttributeDefinition>
 */
class PgSqlAttributeDefinitionRepository extends Repository implements AttributeDefinitionRepositoryInterface
{
    protected const string ENTITY_CLASS = AttributeDefinition::class;

    private readonly PgSqlAttributeOptionRepository $optionRepository;

    public function __construct(
        ConnectionInterface $connection,
        EntityMetadataFactory $metadataFactory,
        EntityHydrator $hydrator,
        ?QueryBuilderFactoryInterface $queryBuilderFactory = null,
        ?EventDispatcherInterface $eventDispatcher = null,
    ) {
        parent::__construct($connection, $metadataFactory, $hydrator, $queryBuilderFactory, $eventDispatcher);

        $this->optionRepository = new PgSqlAttributeOptionRepository(
            $connection,
            $metadataFactory,
            $hydrator,
            $queryBuilderFactory,
            $eventDispatcher,
        );
    }

    /**
     * Cascade-delete options before deleting the definition.
     *
     * The entity annotation `onDelete: 'CASCADE'` is not translated into a database-level
     * FK constraint by the schema provisioner (it requires `references: 'table.column'`
     * format). We therefore cascade explicitly in application code.
     *
     * @throws RepositoryException
     */
    public function delete(Entity $entity): void
    {
        if ($entity instanceof AttributeDefinition) {
            $this->deleteOptionsFor($entity);
        }

        parent::delete($entity);
    }

    public function findByCode(
        string $entityType,
        string $code,
    ): ?AttributeDefinition {
        /** @var AttributeDefinition|null */
        return $this->findOneBy(['entity_type' => $entityType, 'code' => $code]);
    }

    /**
     * @return list<AttributeOption>
     */
    public function optionsFor(AttributeDefinition $definition): array
    {
        /** @var list<AttributeOption> */
        return $this->optionRepository->findBy(['attribute_id' => $definition->id])->toArray();
    }

    public function saveOption(AttributeOption $option): void
    {
        $this->optionRepository->save($option);
    }

    public function deleteOptionsFor(AttributeDefinition $definition): void
    {
        foreach ($this->optionsFor($definition) as $option) {
            $this->optionRepository->delete($option);
        }
    }
}
