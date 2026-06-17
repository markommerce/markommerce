<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Filtering;

use Marko\Database\Repository\RepositoryQueryBuilder;

interface ProductListFilterInterface
{
    /**
     * Inspect the selection for the keys this filter handles and add constraints to the query.
     * Should be a no-op when none of the handled keys are present in the selection.
     */
    public function apply(
        RepositoryQueryBuilder $repositoryQueryBuilder,
        FilterSelection $filterSelection,
    ): void;
}
