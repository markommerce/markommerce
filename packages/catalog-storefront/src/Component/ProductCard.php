<?php

declare(strict_types=1);

namespace Markommerce\CatalogStorefront\Component;

use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Pricing\Contracts\PriceResolverInterface;
use Markommerce\Catalog\Pricing\Exceptions\PriceUnavailableException;
use Markommerce\Catalog\Pricing\PriceContext;
use Markommerce\CatalogStorefront\Data\ProductCardData;
use Markommerce\Layout\ExtensionBag;
use Markommerce\MoneyIntl\MoneyFormatter;

class ProductCard
{
    public function __construct(
        private PriceResolverInterface $priceResolver,
        private MoneyFormatter $moneyFormatter,
    ) {}

    /**
     * @param array<int, string|null>|null $formattedPrices Pre-computed prices keyed by product ID (from parent grid).
     */
    public function data(Product $product, ?array $formattedPrices = null): ProductCardData
    {
        $formattedPrice = null;

        if ($formattedPrices !== null && $product->id !== null) {
            $formattedPrice = $formattedPrices[$product->id] ?? null;
        } else {
            try {
                $money = $this->priceResolver->resolve(PriceContext::forProduct($product));
                $formattedPrice = $this->moneyFormatter->format($money);
            } catch (PriceUnavailableException) {
                $formattedPrice = null;
            }
        }

        return new ProductCardData(
            product: $product,
            resolvedName: $product->name,
            resolvedDesc: $product->description ?? '',
            inStock: true,
            formattedPrice: $formattedPrice,
            extensions: new ExtensionBag(),
        );
    }
}
