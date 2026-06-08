<?php

declare(strict_types=1);

namespace Markommerce\Criteria\Sort;

readonly class SortField
{
    public function __construct(
        public string $column,
        public SortDirection $direction = SortDirection::Ascending,
        public ?string $expression = null,
        public ?NullsPlacement $nulls = null,
    ) {}

    public function sortExpression(): string
    {
        return $this->expression ?? $this->column;
    }
}
