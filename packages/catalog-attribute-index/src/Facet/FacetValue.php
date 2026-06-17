<?php

declare(strict_types=1);

namespace Markommerce\CatalogAttributeIndex\Facet;

readonly class FacetValue
{
    public function __construct(
        public string $value,
        public int $count,
        public bool $selected,
    ) {}
}
