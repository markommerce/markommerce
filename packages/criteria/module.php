<?php

declare(strict_types=1);

use Markommerce\Criteria\Contracts\PaginationStrategyInterface;
use Markommerce\Criteria\Contracts\RowCounterInterface;
use Markommerce\Criteria\Counter\ExactRowCounter;
use Markommerce\Criteria\Strategy\KeysetPaginationStrategy;

return [
    'bindings' => [
        PaginationStrategyInterface::class => KeysetPaginationStrategy::class,
        RowCounterInterface::class         => ExactRowCounter::class,
    ],
];
