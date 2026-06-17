<?php

declare(strict_types=1);

namespace Markommerce\CatalogAttributeStorefront\LayeredNavigation;

/**
 * A facet enriched with display labels for the active scope.
 *
 * Mirrors `Markommerce\CatalogAttributeIndex\Facet\Facet` but carries `LabeledFacetValue`
 * objects whose `label` fields are resolved via the scoped label resolver.
 */
readonly class LabeledFacet
{
    /**
     * @param list<LabeledFacetValue> $values
     */
    public function __construct(
        public string $code,
        public string $type,
        public array $values,
    ) {}
}
