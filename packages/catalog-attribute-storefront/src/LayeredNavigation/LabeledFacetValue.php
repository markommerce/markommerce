<?php

declare(strict_types=1);

namespace Markommerce\CatalogAttributeStorefront\LayeredNavigation;

/**
 * A facet value enriched with a display label resolved for the active scope.
 *
 * `value`  — the raw stored value (used in filter params, e.g. "red")
 * `label`  — the display label for the active scope (e.g. "Red" or a scoped translation)
 * `count`  — number of products with this value in the current filtered context
 * `selected` — whether this value is part of the active filter selection
 */
readonly class LabeledFacetValue
{
    public function __construct(
        public string $value,
        public string $label,
        public int $count,
        public bool $selected,
    ) {}
}
