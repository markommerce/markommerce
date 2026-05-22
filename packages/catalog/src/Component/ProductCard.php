<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Component;

use Markommerce\Catalog\Data\ProductCardData;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Layout\ExtensionBag;

class ProductCard
{
    public function data(Product $product): ProductCardData
    {
        return new ProductCardData(
            product: $product,
            resolvedName: $product->name ?? '',
            resolvedDesc: $product->description ?? '',
            inStock: true,
            extensions: new ExtensionBag(),
        );
    }
}
