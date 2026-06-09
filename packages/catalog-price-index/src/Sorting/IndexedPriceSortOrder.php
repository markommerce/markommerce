<?php

declare(strict_types=1);

namespace Markommerce\CatalogPriceIndex\Sorting;

use Marko\Database\Repository\RepositoryQueryBuilder;
use Markommerce\Catalog\Sorting\CategorySortOrderInterface;
use Markommerce\Criteria\Sort\NullsPlacement;
use Markommerce\Criteria\Sort\SortDirection;
use Markommerce\Criteria\Sort\SortField;

abstract class IndexedPriceSortOrder implements CategorySortOrderInterface
{
    abstract public function key(): string;

    abstract public function label(): string;

    abstract protected function direction(): SortDirection;

    public function supportsKeyset(): bool
    {
        return false;
    }

    public function prepareQuery(RepositoryQueryBuilder $repositoryQueryBuilder): void
    {
        $repositoryQueryBuilder->leftJoin(
            'catalog_product_price_index',
            'catalog_products.id',
            '=',
            'catalog_product_price_index.product_id',
        );
    }

    /** @return list<SortField> */
    public function sortFields(): array
    {
        return [
            new SortField(
                column: 'catalog_product_price_index.amount',
                direction: $this->direction(),
                nulls: NullsPlacement::Last,
            ),
        ];
    }
}
