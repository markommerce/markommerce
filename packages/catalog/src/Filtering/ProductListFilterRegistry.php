<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Filtering;

class ProductListFilterRegistry
{
    /** @var list<ProductListFilterInterface> */
    private array $registered = [];

    public function register(ProductListFilterInterface $productListFilter): void
    {
        $this->registered[] = $productListFilter;
    }

    /** @return list<ProductListFilterInterface> */
    public function all(): array
    {
        return $this->registered;
    }
}
