<?php

declare(strict_types=1);

namespace Markommerce\CatalogAttributeStorefront\LayeredNavigation;

readonly class ActiveFilter
{
    /**
     * @param list<string> $labels  resolved display labels for the active values
     * @param list<string> $values  raw attribute values
     */
    public function __construct(
        public string $code,
        public string $type,
        public array $labels,
        public array $values,
    ) {}
}
