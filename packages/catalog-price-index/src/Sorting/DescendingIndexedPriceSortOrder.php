<?php

declare(strict_types=1);

namespace Markommerce\CatalogPriceIndex\Sorting;

use Markommerce\Criteria\Sort\SortDirection;

class DescendingIndexedPriceSortOrder extends IndexedPriceSortOrder
{
    public function key(): string
    {
        return 'price_desc';
    }

    public function label(): string
    {
        return 'Price: High to Low';
    }

    protected function direction(): SortDirection
    {
        return SortDirection::Descending;
    }
}
