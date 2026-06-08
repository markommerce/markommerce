<?php

declare(strict_types=1);

namespace Markommerce\CatalogPriceIndexMarket\Sorting;

use Marko\Core\Attributes\Preference;
use Markommerce\CatalogPriceIndex\Sorting\AscendingIndexedPriceSortOrder;
use Markommerce\Criteria\Sort\SortDirection;

#[Preference(replaces: AscendingIndexedPriceSortOrder::class)]
class ScopedAscendingIndexedPriceSortOrder extends ScopedIndexedPriceSortOrder
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
