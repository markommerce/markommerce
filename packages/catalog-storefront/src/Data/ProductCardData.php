<?php

declare(strict_types=1);

namespace Markommerce\CatalogStorefront\Data;

use Markommerce\Catalog\Entity\Product;
use Markommerce\Layout\ExtensibleData;
use Markommerce\Layout\ExtensionBag;

readonly class ProductCardData extends ExtensibleData
{
    public function __construct(
        public Product $product,
        public string $resolvedName,
        public string $resolvedDesc,
        public bool $inStock,
        public ?string $formattedPrice = null,
        ExtensionBag $extensions = new ExtensionBag(),
    ) {
        parent::__construct($extensions);
    }
}
