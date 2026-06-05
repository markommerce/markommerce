<?php

declare(strict_types=1);

namespace Markommerce\Criteria\Sort;

enum SortDirection: string
{
    case Ascending = 'ASC';
    case Descending = 'DESC';
}
