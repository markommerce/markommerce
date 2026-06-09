<?php

declare(strict_types=1);

namespace Markommerce\CatalogPriceIndex\Sorting;

use Markommerce\Criteria\Sort\SortDirection;

class AscendingIndexedPriceSortOrder extends IndexedPriceSortOrder
{
    public function key(): string
    {
        return 'price_asc';
    }

    public function label(): string
    {
        return 'Price: Low to High';
    }

    protected function direction(): SortDirection
    {
        return SortDirection::Ascending;
    }
}
