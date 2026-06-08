<?php

declare(strict_types=1);

namespace Markommerce\CatalogPriceIndexMarket;

use Marko\Core\Attributes\Preference;
use Markommerce\CatalogPriceIndex\Contracts\IndexedMarketsProviderInterface;
use Markommerce\CatalogPriceIndex\DefaultIndexedMarketsProvider;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Registry\ScopeRegistryInterface;

#[Preference(replaces: DefaultIndexedMarketsProvider::class)]
class ScopedIndexedMarketsProvider implements IndexedMarketsProviderInterface
{
    public function __construct(private ScopeRegistryInterface $scopeRegistry) {}

    /**
     * @return list<string>
     */
    public function markets(): array
    {
        try {
            $defaultMarket = $this->scopeRegistry->getAxis('market')->default;
            $paths         = $this->scopeRegistry->getHierarchy('market')->paths();
        } catch (UnknownAxisException) {
            return [];
        }

        return array_values(array_filter(
            $paths,
            fn (string $m): bool => $m !== $defaultMarket,
        ));
    }
}
