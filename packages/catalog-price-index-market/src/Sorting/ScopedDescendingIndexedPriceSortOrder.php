<?php

declare(strict_types=1);

namespace Markommerce\CatalogPriceIndexMarket\Sorting;

use Marko\Core\Attributes\Preference;
use Markommerce\CatalogPriceIndex\Sorting\DescendingIndexedPriceSortOrder;
use Markommerce\Criteria\Sort\SortField;

#[Preference(replaces: DescendingIndexedPriceSortOrder::class)]
class ScopedDescendingIndexedPriceSortOrder extends DescendingIndexedPriceSortOrder
{
    public function __construct(private MarketScopedPriceExpression $marketScopedPriceExpression) {}

    /** @return list<SortField> */
    public function sortFields(): array
    {
        return $this->marketScopedPriceExpression->sortFields($this->direction());
    }
}
