<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Data;

use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Layout\ExtensibleData;
use Markommerce\Layout\ExtensionBag;

readonly class ProductGridData extends ExtensibleData
{
    /**
     * @param list<Product> $products
     * @param array<int, string> $resolvedNames
     * @param array<int, string|null> $resolvedDescs
     */
    public function __construct(
        public Category $category,
        public array $products,
        public array $resolvedNames,
        public array $resolvedDescs,
        ExtensionBag $extensions = new ExtensionBag(),
    ) {
        parent::__construct($extensions);
    }
}
