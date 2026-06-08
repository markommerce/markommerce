<?php

declare(strict_types=1);

namespace Markommerce\CatalogPriceIndex;

use Markommerce\CatalogPriceIndex\Contracts\IndexedMarketsProviderInterface;

class DefaultIndexedMarketsProvider implements IndexedMarketsProviderInterface
{
    /**
     * @return list<string>
     */
    public function markets(): array
    {
        return [];
    }
}
