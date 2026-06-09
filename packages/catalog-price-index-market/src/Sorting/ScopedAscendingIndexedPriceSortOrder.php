<?php

declare(strict_types=1);

namespace Markommerce\CatalogPriceIndexMarket\Sorting;

use Marko\Core\Attributes\Preference;
use Markommerce\CatalogPriceIndex\Sorting\AscendingIndexedPriceSortOrder;
use Markommerce\Criteria\Sort\SortField;

#[Preference(replaces: AscendingIndexedPriceSortOrder::class)]
class ScopedAscendingIndexedPriceSortOrder extends AscendingIndexedPriceSortOrder
{
    public function __construct(private MarketScopedPriceExpression $marketScopedPriceExpression) {}

    /** @return list<SortField> */
    public function sortFields(): array
    {
        return $this->marketScopedPriceExpression->sortFields($this->direction());
    }
}
