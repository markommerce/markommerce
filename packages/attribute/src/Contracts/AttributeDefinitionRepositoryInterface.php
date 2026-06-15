<?php

declare(strict_types=1);

namespace Markommerce\Attribute\Contracts;

use Marko\Database\Repository\RepositoryInterface;
use Markommerce\Attribute\Entity\AttributeDefinition;
use Markommerce\Attribute\Entity\AttributeOption;

/**
 * @extends RepositoryInterface<AttributeDefinition>
 */
interface AttributeDefinitionRepositoryInterface extends RepositoryInterface
{
    public function findByCode(
        string $entityType,
        string $code,
    ): ?AttributeDefinition;

    /**
     * @return list<AttributeOption>
     */
    public function optionsFor(AttributeDefinition $definition): array;

    public function saveOption(AttributeOption $option): void;

    public function deleteOptionsFor(AttributeDefinition $definition): void;
}
