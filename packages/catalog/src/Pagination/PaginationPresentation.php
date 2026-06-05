<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Pagination;

enum PaginationPresentation: string
{
    case Numbered = 'numbered';
    case LoadMore = 'load_more';
    case Infinite = 'infinite';
}
