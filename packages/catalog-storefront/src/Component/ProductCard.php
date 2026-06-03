<?php

declare(strict_types=1);

namespace Markommerce\CatalogStorefront\Component;

use Markommerce\Catalog\Entity\Product;
use Markommerce\CatalogStorefront\Data\ProductCardData;
use Markommerce\Layout\ExtensionBag;
use Markommerce\MoneyIntl\MoneyFormatter;
use Markommerce\Pricing\Contracts\PriceResolverInterface;
use Markommerce\Pricing\Exceptions\PriceUnavailableException;
use Markommerce\Pricing\PriceContext;

class ProductCard
{
    public function __construct(
        private PriceResolverInterface $priceResolver,
        private MoneyFormatter $moneyFormatter,
    ) {}

    public function data(Product $product): ProductCardData
    {
        $formattedPrice = null;

        try {
            $money = $this->priceResolver->resolve(PriceContext::forProduct($product));
            $formattedPrice = $this->moneyFormatter->format($money);
        } catch (PriceUnavailableException) {
            $formattedPrice = null;
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
