<?php

declare(strict_types=1);

namespace Markommerce\CatalogMarket\Pricing;

use Marko\Core\Attributes\Preference;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Pricing\Contracts\ProductBasePriceProviderInterface;
use Markommerce\Catalog\Pricing\RawProductBasePriceProvider;
use Markommerce\Scope\Resolver\ScopeResolver;

#[Preference(replaces: RawProductBasePriceProvider::class)]
class ScopedProductBasePriceProvider implements ProductBasePriceProviderInterface
{
    public function __construct(private ScopeResolver $scopeResolver) {}

    /**
     * @param array<array-key, Product> $products
     * @return array<array-key, ?string>
     */
    public function amountsFor(array $products): array
    {
        return array_map(
            function (Product $p): ?string {
                /** @var ?string $amount */
                $amount = $this->scopeResolver->resolved($p, 'priceAmount');

                return $amount;
            },
            $products,
        );
    }
}
