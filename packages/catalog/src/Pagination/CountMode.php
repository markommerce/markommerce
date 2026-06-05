<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Pagination;

enum CountMode: string
{
    case Exact = 'exact';
    case Estimated = 'estimated';
}
