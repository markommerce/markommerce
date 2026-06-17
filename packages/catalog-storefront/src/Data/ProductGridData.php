<?php

declare(strict_types=1);

namespace Markommerce\CatalogStorefront\Data;

use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Pagination\PaginationPresentation;
use Markommerce\Layout\ExtensibleData;
use Markommerce\Layout\ExtensionBag;

readonly class ProductGridData extends ExtensibleData
{
    /**
     * @param list<Product> $products
     * @param array<int, string> $resolvedNames
     * @param array<int, string|null> $resolvedDescs
     * @param array<int, string|null> $formattedPrices
     * @param list<string> $pageLinkUrls Crawlable numbered page URLs (e.g. ['?page=1&size=24', ...])
     * @param list<array{key: string, label: string}> $sortOptions Available sort orders for the dropdown
     * @param list<object> $facets Labeled facet groups from layered navigation (empty when assembler not present)
     * @param list<object> $activeFilters Active filter chips from the current selection (empty when assembler not present)
     */
    public function __construct(
        public Category $category,
        public array $products,
        public array $resolvedNames,
        public array $resolvedDescs,
        public array $formattedPrices = [],
        public PaginationPresentation $presentation = PaginationPresentation::Numbered,
        public ?int $currentPage = null,
        public ?int $totalPages = null,
        public bool $hasNext = false,
        public bool $hasPrevious = false,
        public array $pageLinkUrls = [],
        public ?string $nextPageUrl = null,
        public ?string $previousPageUrl = null,
        public ?string $canonicalPageUrl = null,
        public array $sortOptions = [],
        public string $activeSort = '',
        ExtensionBag $extensions = new ExtensionBag(),
        public array $facets = [],
        public array $activeFilters = [],
    ) {
        parent::__construct($extensions);
    }
}
