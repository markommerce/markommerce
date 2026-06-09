<?php

declare(strict_types=1);

namespace Markommerce\Criteria\Strategy;

use Marko\Database\Repository\RepositoryQueryBuilder;
use Markommerce\Criteria\Sort\NullsPlacement;
use Markommerce\Criteria\Sort\SortField;

readonly class SortFieldApplier
{
    public function apply(RepositoryQueryBuilder $query, SortField $field): void
    {
        $expr = $field->sortExpression();
        $direction = $field->direction->value;

        if ($field->nulls !== null) {
            $companionDirection = $field->nulls === NullsPlacement::Last ? 'ASC' : 'DESC';
            $query->orderByRaw("($expr) IS NULL", $companionDirection);
            $query->orderByRaw($expr, $direction);

            return;
        }

        if ($field->expression !== null) {
            $query->orderByRaw($expr, $direction);

            return;
        }

        $query->orderBy($field->column, $direction);
    }
}
