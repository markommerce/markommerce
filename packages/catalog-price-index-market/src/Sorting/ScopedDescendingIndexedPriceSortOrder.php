<?php

declare(strict_types=1);

namespace Markommerce\CatalogPriceIndexMarket\Sorting;

use Marko\Core\Attributes\Preference;
use Markommerce\CatalogPriceIndex\Sorting\DescendingIndexedPriceSortOrder;
use Markommerce\Criteria\Sort\SortDirection;

#[Preference(replaces: DescendingIndexedPriceSortOrder::class)]
class ScopedDescendingIndexedPriceSortOrder extends ScopedIndexedPriceSortOrder
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
