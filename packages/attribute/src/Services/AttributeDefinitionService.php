<?php

declare(strict_types=1);

namespace Markommerce\Attribute\Services;

use Markommerce\Attribute\Contracts\AttributeDefinitionRepositoryInterface;
use Markommerce\Attribute\Entity\AttributeDefinition;
use Markommerce\Attribute\Entity\AttributeOption;
use Markommerce\Attribute\Exceptions\DuplicateAttributeCodeException;
use Markommerce\Attribute\Exceptions\OptionsNotAllowedException;
use Markommerce\Attribute\Exceptions\ReservedAttributeCodeException;
use Markommerce\Attribute\Exceptions\UnknownAttributeTypeException;
use Markommerce\Attribute\Registry\AttributeEntityClassMap;
use Markommerce\Attribute\Registry\AttributeTypeRegistry;
use Markommerce\Attribute\Reserved\ReservedCodeProvider;

class AttributeDefinitionService
{
    /** @var string[] */
    private const array SELECT_TYPES = ['select', 'multiselect'];

    /**
     * @param array<string, class-string> $entityTypeMap  Maps entity-type strings to entity classes.
     *                                                     When an entity type has no mapped class,
     *                                                     reserved-code checking is skipped for that type.
     *                                                     Takes precedence over $entityClassMap.
     */
    public function __construct(
        private AttributeDefinitionRepositoryInterface $attributeDefinitionRepository,
        private AttributeTypeRegistry $attributeTypeRegistry,
        private ReservedCodeProvider $reservedCodeProvider,
        private array $entityTypeMap = [],
        private ?AttributeEntityClassMap $entityClassMap = null,
    ) {}

    /**
     * Create and persist a new attribute definition, enforcing structural guards.
     *
     * @param list<AttributeOption> $options  Options are only permitted for select/multiselect types.
     *
     * @throws UnknownAttributeTypeException|ReservedAttributeCodeException|DuplicateAttributeCodeException|OptionsNotAllowedException
     */
    public function create(
        AttributeDefinition $definition,
        array $options = [],
    ): void {
        $this->guardType($definition->type);
        $this->guardReservedCode($definition->entityType, $definition->code);
        $this->guardDuplicateCode($definition->entityType, $definition->code);

        if ($options !== [] && !in_array($definition->type, self::SELECT_TYPES, strict: true)) {
            throw OptionsNotAllowedException::forType($definition->type);
        }

        $this->attributeDefinitionRepository->save($definition);

        foreach ($options as $option) {
            $option->attributeId = $definition->id;
            $this->attributeDefinitionRepository->saveOption($option);
        }
    }

    /**
     * Delete a definition and cascade-remove its options.
     */
    public function delete(AttributeDefinition $definition): void
    {
        $this->attributeDefinitionRepository->deleteOptionsFor($definition);
        $this->attributeDefinitionRepository->delete($definition);
    }

    /**
     * @throws UnknownAttributeTypeException
     */
    private function guardType(string $typeCode): void
    {
        if (!$this->attributeTypeRegistry->has($typeCode)) {
            throw UnknownAttributeTypeException::forType($typeCode);
        }
    }

    /**
     * @throws ReservedAttributeCodeException
     */
    private function guardReservedCode(
        string $entityType,
        string $code,
    ): void {
        $entityClass = $this->entityTypeMap[$entityType] ?? $this->entityClassMap?->all()[$entityType] ?? null;

        if ($entityClass === null) {
            // No mapped class for this entity type — skip reserved-code checking.
            return;
        }

        $reserved = $this->reservedCodeProvider->reservedCodes($entityClass);

        if (in_array($code, $reserved, strict: true)) {
            throw ReservedAttributeCodeException::forCode($entityType, $code);
        }
    }

    /**
     * @throws DuplicateAttributeCodeException
     */
    private function guardDuplicateCode(
        string $entityType,
        string $code,
    ): void {
        $existing = $this->attributeDefinitionRepository->findByCode($entityType, $code);

        if ($existing !== null) {
            throw DuplicateAttributeCodeException::forCode($entityType, $code);
        }
    }
}
