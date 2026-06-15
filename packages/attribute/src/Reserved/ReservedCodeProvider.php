<?php

declare(strict_types=1);

namespace Markommerce\Attribute\Reserved;

use Marko\Database\Entity\ColumnMetadata;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Exceptions\EntityException;
use Marko\Database\Exceptions\MissingPrimaryKeyException;

class ReservedCodeProvider
{
    public function __construct(
        private EntityMetadataFactory $entityMetadataFactory,
    ) {}

    /**
     * Returns reserved attribute codes for the given entity class.
     *
     * Column names and property names are both reserved to prevent custom
     * attributes from shadowing any native entity column.
     *
     * @param class-string $entityClass
     * @return array<string>
     *
     * @throws EntityException|MissingPrimaryKeyException
     */
    public function reservedCodes(string $entityClass): array
    {
        $metadata = $this->entityMetadataFactory->parse($entityClass);

        $columnNames = array_map(
            fn (ColumnMetadata $column) => $column->name,
            $metadata->columns,
        );

        $propertyNames = array_keys($metadata->properties);

        return array_values(array_unique([...$columnNames, ...$propertyNames]));
    }
}
