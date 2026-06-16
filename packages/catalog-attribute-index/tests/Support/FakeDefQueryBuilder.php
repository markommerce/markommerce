<?php

declare(strict_types=1);

namespace Markommerce\CatalogAttributeIndex\Tests\Support;

use Marko\Database\Entity\EntityCollection;
use Marko\Database\Repository\RepositoryQueryBuilder;
use Markommerce\Attribute\Entity\AttributeDefinition;

/**
 * An in-memory fake RepositoryQueryBuilder for AttributeDefinition queries.
 * Supports where() and getEntities() only — enough for AttributeIndexer.
 */
class FakeDefQueryBuilder extends RepositoryQueryBuilder
{
    /** @var list<AttributeDefinition> */
    private array $filtered;

    /** @param list<AttributeDefinition> $definitions */
    public function __construct(array $definitions)
    {
        // Skip parent constructor — no real DB needed.
        $this->filtered = $definitions;
    }

    public function where(
        string $column,
        string $operator,
        mixed $value,
    ): static {
        $this->filtered = array_values(array_filter(
            $this->filtered,
            fn (AttributeDefinition $d): bool => $this->matchesWhere($d, $column, $operator, $value),
        ));

        return $this;
    }

    /** @return EntityCollection<AttributeDefinition> */
    public function getEntities(): EntityCollection
    {
        return new EntityCollection($this->filtered);
    }

    private function matchesWhere(
        AttributeDefinition $def,
        string $column,
        string $operator,
        mixed $value,
    ): bool {
        $actual = match ($column) {
            'entity_type' => $def->entityType,
            'filterable'  => $def->filterable,
            'facetable'   => $def->facetable,
            'scopable'    => $def->scopable,
            'code'        => $def->code,
            default       => null,
        };

        return match ($operator) {
            '='  => $actual === $value,
            '!=' => $actual !== $value,
            default => false,
        };
    }
}
