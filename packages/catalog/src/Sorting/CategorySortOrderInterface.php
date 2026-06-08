<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Sorting;

use Marko\Database\Repository\RepositoryQueryBuilder;
use Markommerce\Criteria\Sort\SortField;

interface CategorySortOrderInterface
{
    /**
     * Stable URL/config token that uniquely identifies this sort order (e.g. `price_asc`).
     */
    public function key(): string;

    /**
     * Human-readable label shown in the storefront sort-order dropdown.
     */
    public function label(): string;

    /**
     * Whether this sort order is compatible with keyset (cursor-based) pagination.
     */
    public function supportsKeyset(): bool;

    /**
     * Add any JOINs required by this sort order to the category product query.
     * Column-only sort orders should implement this as a no-op.
     */
    public function prepareQuery(RepositoryQueryBuilder $repositoryQueryBuilder): void;

    /**
     * The sort fields to apply to the ORDER BY clause.
     *
     * @return list<SortField>
     */
    public function sortFields(): array;
}
