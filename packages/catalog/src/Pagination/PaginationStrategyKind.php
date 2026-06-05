<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Pagination;

enum PaginationStrategyKind: string
{
    case Offset = 'offset';
    case Keyset = 'keyset';
}
