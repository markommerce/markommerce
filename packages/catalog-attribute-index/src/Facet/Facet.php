<?php

declare(strict_types=1);

namespace Markommerce\CatalogAttributeIndex\Facet;

readonly class Facet
{
    /**
     * @param list<FacetValue> $values
     */
    public function __construct(
        public string $code,
        public string $type,
        public array $values,
    ) {}
}
