<?php

declare(strict_types=1);

namespace Markommerce\CatalogPriceIndex\Contracts;

interface IndexedMarketsProviderInterface
{
    /**
     * @return list<string> the market scope keys to index per-market amounts for
     */
    public function markets(): array;
}
