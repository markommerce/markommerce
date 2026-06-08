<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Pagination;

use Markommerce\Catalog\Sorting\CategorySortOrderInterface;

readonly class ResolvedPaginationOptions
{
    public function __construct(
        public CategorySortOrderInterface $sortOrder,
        public int $size,
        public int $page,
        public PaginationPresentation $presentation,
        public PaginationStrategyKind $strategyKind,
        public CountMode $countMode,
    ) {}
}
