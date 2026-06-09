<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Sorting;

use Marko\Database\Repository\RepositoryQueryBuilder;
use Markommerce\Criteria\Sort\NullsPlacement;
use Markommerce\Criteria\Sort\SortDirection;
use Markommerce\Criteria\Sort\SortField;

class ColumnSortOrder implements CategorySortOrderInterface
{
    public function __construct(
        private readonly string $key,
        private readonly string $label,
        private readonly string $column,
        private readonly SortDirection $direction = SortDirection::Ascending,
        private readonly bool $supportsKeyset = false,
        private readonly ?NullsPlacement $nulls = null,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function supportsKeyset(): bool
    {
        return $this->supportsKeyset;
    }

    public function prepareQuery(RepositoryQueryBuilder $repositoryQueryBuilder): void
    {
        // Plain column orders add no JOINs — intentional no-op.
    }

    /** @return list<SortField> */
    public function sortFields(): array
    {
        return [new SortField($this->column, $this->direction, null, $this->nulls)];
    }
}
