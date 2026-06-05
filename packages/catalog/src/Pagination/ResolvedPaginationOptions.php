<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Pagination;

use Markommerce\Criteria\Page\PageRequest;

readonly class ResolvedPaginationOptions
{
    public function __construct(
        public PageRequest $pageRequest,
        public int $page,
        public PaginationPresentation $presentation,
        public PaginationStrategyKind $strategyKind,
        public CountMode $countMode,
    ) {}
}
